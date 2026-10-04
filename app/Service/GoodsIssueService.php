<?php

namespace App\Service;

use App\Core\Result;
use App\Core\TransactionManagerInterface;
use App\Entity\SalesOrder;
use App\Repository\Interface\ProductStockRepositoryInterface;
use App\Repository\Interface\SalesOrderItemRepositoryInterface;
use App\Repository\Interface\SalesOrderRepositoryInterface;
use App\Repository\Interface\StockLedgerRepositoryInterface;
use App\Service\Exception\DomainException;
use App\Service\Exception\InsufficientStockException;
use App\Service\Exception\InvalidStateException;

// Fulfils an approved Sales Order: stock deduction and Stock Ledger entry in one transaction (ARCH-02 — concurrent goods issues MUST NOT oversell).
class GoodsIssueService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    private $transactionManager;
    private $salesOrderRepository;
    private $salesOrderItemRepository;
    private $productStockRepository;
    private $stockLedgerRepository;
    private $policy;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService
    // for why: existing tests construct this Service directly without it.
    public function __construct(
        TransactionManagerInterface $transactionManager,
        SalesOrderRepositoryInterface $salesOrderRepository,
        SalesOrderItemRepositoryInterface $salesOrderItemRepository,
        ProductStockRepositoryInterface $productStockRepository,
        StockLedgerRepositoryInterface $stockLedgerRepository,
        SalesOrderPolicy $policy,
        EventLogService $eventLogService = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->salesOrderRepository = $salesOrderRepository;
        $this->salesOrderItemRepository = $salesOrderItemRepository;
        $this->productStockRepository = $productStockRepository;
        $this->stockLedgerRepository = $stockLedgerRepository;
        $this->policy = $policy;
        $this->eventLogService = $eventLogService;
    }

    public function issue($salesOrderId, $actorUserId)
    {
        $result = new Result();

        try {
            $findResult = $this->salesOrderRepository->findById($salesOrderId);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $salesOrder = $findResult->data;
            if ($salesOrder === null) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Record not found.';
                $result->data = null;

                return $result;
            }

            $this->policy->assertCanIssue($salesOrder);

            $this->transactionManager->beginTransaction();

            $issuance = $this->executeIssuanceTransaction($salesOrder, $actorUserId);

            if ($issuance instanceof Result) {
                $this->transactionManager->rollBack();

                return $issuance;
            }

            $this->transactionManager->commit();

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The goods issue has been recorded and stock updated.';
            $result->data = $issuance;

            // Logged after commit() — never inside the transaction that owns
            // the actual stock/ledger write (see EventLogService header comment).
            if ($this->eventLogService !== null) {
                $this->eventLogService->record($actorUserId, 'issue', 'SalesOrder', $salesOrderId, "Recorded goods issue for SO #{$salesOrderId}");
            }
        } catch (\Throwable $e) {
            $this->transactionManager->rollBack();

            if ($e instanceof DomainException) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = $e->getMessage();
                $result->data = null;
            } else {
                error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
                $result->code = Result::CODE_INTERNAL;
                $result->info = self::MESSAGE_FAILED_FUNCTION;
                $result->data = null;
            }
        }

        return $result;
    }

    // Helpers

    private function executeIssuanceTransaction($salesOrder, $actorUserId)
    {
        // Runs inside the transaction opened by issue(): returns the fulfilled SalesOrder on success or a validation
        // Result when stock is insufficient (or any repo call fails), and issue() picks rollBack() vs commit() from
        // which came back. Any other \Throwable propagates to issue()'s catch, which owns the generic translation.
        try {
            // $outcome is the first failing Result, or the fulfilled SalesOrder on
            // success; issue() picks rollBack() vs commit() from which came back.
            $outcome = null;

            // ARCH-02: lock the SO row FIRST and re-check its status from that
            // locking read. The Approved check in issue() ran unlocked, so two
            // concurrent issues of the same SO (or an issue racing a cancel) both
            // passed it; the second then blocked here and, without this re-check,
            // deducted stock and wrote Issue ledger rows a second time.
            $lockResult = $this->salesOrderRepository->lockForUpdate($salesOrder->id);
            if ($lockResult->code !== Result::CODE_SUCCESS) {
                $outcome = $lockResult;
            }

            $items = [];
            if ($outcome === null) {
                $lockedStatus = $lockResult->data[0]['status'] ?? null;
                if ($lockedStatus !== SalesOrder::STATUS_APPROVED) {
                    throw new InvalidStateException('Goods can only be issued for an approved sales order.');
                }

                $itemsResult = $this->salesOrderItemRepository->findBySalesOrderIdWithProduct($salesOrder->id);
                if ($itemsResult->code !== Result::CODE_SUCCESS) {
                    $outcome = $itemsResult;
                } else {
                    $items = $itemsResult->data;
                }
            }

            if ($outcome === null) {
                $outcome = $this->applyIssuanceLines($items, $salesOrder, $actorUserId);
            }

            if ($outcome === null) {
                $updateStatus = $this->salesOrderRepository->updateStatus($salesOrder->id, SalesOrder::STATUS_FULFILLED, [
                    'issued_by' => $actorUserId,
                    'issued_at' => date('Y-m-d H:i:s'),
                ]);
                if ($updateStatus->code !== Result::CODE_SUCCESS) {
                    $outcome = $updateStatus;
                }
            }

            if ($outcome === null) {
                $refreshedResult = $this->salesOrderRepository->findById($salesOrder->id);
                if ($refreshedResult->code !== Result::CODE_SUCCESS) {
                    $outcome = $refreshedResult;
                } else {
                    $outcome = $refreshedResult->data->withItems($items);
                }
            }

            return $outcome;
        } catch (InsufficientStockException $e) {
            $result = new Result();
            $result->code = Result::CODE_VALIDATION;
            $result->info = $e->getMessage();
            $result->data = null;

            return $result;
        }
    }

    // Deducts stock and writes an Issue ledger row for each line, inside the
    // transaction opened by issue(). Returns null on success, or the first
    // failing repository Result. Throws InsufficientStockException (caught by
    // executeIssuanceTransaction) when a line cannot be covered by stock on hand.
    private function applyIssuanceLines($items, $salesOrder, $actorUserId)
    {
        $failure = null;

        foreach ($items as $item) {
            // ARCH-02: row lock per product/warehouse before the check-then-deduct, so two concurrent issues cannot oversell
            $stockResult = $this->productStockRepository->lockForUpdate(
                $item->productId,
                $salesOrder->sourceWarehouseId,
            );
            if ($stockResult->code !== Result::CODE_SUCCESS) {
                $failure = $stockResult;
                break;
            }

            $stock = $stockResult->data;

            if ($stock === null || $stock->quantity < $item->qty) {
                throw new InsufficientStockException('Insufficient stock: there is not enough stock on hand to issue this sales order.');
            }

            $incrementResult = $this->productStockRepository->incrementQuantity(
                $item->productId,
                $salesOrder->sourceWarehouseId,
                -$item->qty,
            );
            if ($incrementResult->code !== Result::CODE_SUCCESS) {
                $failure = $incrementResult;
                break;
            }

            $insertResult = $this->stockLedgerRepository->insert([
                'product_id' => $item->productId,
                'warehouse_id' => $salesOrder->sourceWarehouseId,
                'type' => 'Issue',
                'qty' => -$item->qty,
                'ref_type' => 'SO',
                'ref_id' => $salesOrder->id,
                'done_by_user_id' => $actorUserId,
                'done_at' => date('Y-m-d H:i:s'),
            ]);
            if ($insertResult->code !== Result::CODE_SUCCESS) {
                $failure = $insertResult;
                break;
            }
        }

        return $failure;
    }
}
