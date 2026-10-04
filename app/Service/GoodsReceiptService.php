<?php

namespace App\Service;

use App\Core\Result;
use App\Core\TransactionManagerInterface;
use App\Entity\PurchaseOrder;
use App\Entity\StockLedgerEntry;
use App\Repository\Interface\ProductStockRepositoryInterface;
use App\Repository\Interface\PurchaseOrderItemRepositoryInterface;
use App\Repository\Interface\PurchaseOrderRepositoryInterface;
use App\Repository\Interface\StockLedgerRepositoryInterface;
use App\Service\Exception\DomainException;
use App\Service\Exception\InvalidStateException;

// Receives goods against a Purchase Order: stock increase and Stock Ledger entry in one transaction.
class GoodsReceiptService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    private $transactionManager;
    private $purchaseOrderRepository;
    private $purchaseOrderItemRepository;
    private $productStockRepository;
    private $stockLedgerRepository;
    private $purchaseOrderService;
    private $eventLogService;

    // $eventLogService is optional (nullable, default null) — see AuthService
    // for why: existing tests construct this Service directly without it.
    public function __construct(
        TransactionManagerInterface $transactionManager,
        PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        PurchaseOrderItemRepositoryInterface $purchaseOrderItemRepository,
        ProductStockRepositoryInterface $productStockRepository,
        StockLedgerRepositoryInterface $stockLedgerRepository,
        PurchaseOrderService $purchaseOrderService,
        EventLogService $eventLogService = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->purchaseOrderItemRepository = $purchaseOrderItemRepository;
        $this->productStockRepository = $productStockRepository;
        $this->stockLedgerRepository = $stockLedgerRepository;
        $this->purchaseOrderService = $purchaseOrderService;
        $this->eventLogService = $eventLogService;
    }

    public function process($purchaseOrderId, $receiptLines, $userId)
    {
        $result = new Result();

        try {
            // The first failing step wins; the atomic stock write runs only after
            // every pre-flight check passes, and $result is populated on success.
            $errorResult = null;
            $findResult = $this->purchaseOrderRepository->findById($purchaseOrderId);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $findResult;
            }

            $purchaseOrder = null;
            if ($errorResult === null) {
                $purchaseOrder = $findResult->data;

                $headerInfo = '';
                if ($purchaseOrder === null) {
                    $headerInfo = 'Record not found.';
                } elseif (!$purchaseOrder->canReceiveGoods()) {
                    $headerInfo = 'Goods can only be received for an Ordered or Partially Received purchase order.';
                }

                if ($headerInfo !== '') {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = $headerInfo;
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            $itemsById = [];
            if ($errorResult === null) {
                $itemsResult = $this->purchaseOrderItemRepository->findByPurchaseOrderId($purchaseOrderId);
                if ($itemsResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $itemsResult;
                } else {
                    foreach ($itemsResult->data as $item) {
                        $itemsById[$item->id] = $item;
                    }
                }
            }

            $lines = null;
            if ($errorResult === null) {
                $lines = $this->buildValidatedLines($receiptLines, $itemsById, $purchaseOrderId);
                if ($lines instanceof Result) {
                    $errorResult = $lines;
                } elseif ($lines === []) {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = 'Enter a quantity for at least one line item.';
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            if ($errorResult === null) {
                $refreshed = $this->applyLinesInTransaction(
                    $lines,
                    $purchaseOrder->destinationWarehouseId,
                    $purchaseOrderId,
                    $userId
                );

                if ($refreshed instanceof Result) {
                    $errorResult = $refreshed;
                } elseif ($refreshed === null) {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = 'Record not found.';
                    $result->data = null;
                    $errorResult = $result;
                } else {
                    $finalItemsResult = $this->purchaseOrderItemRepository->findByPurchaseOrderId($purchaseOrderId);
                    $finalItems = $finalItemsResult->code === Result::CODE_SUCCESS ? $finalItemsResult->data : [];

                    $result->code = Result::CODE_SUCCESS;
                    $result->info = 'The goods receipt has been recorded and stock updated.';
                    $result->data = $refreshed->withItems($finalItems);

                    // Logged after commit() — never inside the transaction that owns
                    // the actual stock/ledger write (see EventLogService header comment).
                    if ($this->eventLogService !== null) {
                        $this->eventLogService->record($userId, 'receive', 'PurchaseOrder', $purchaseOrderId, "Recorded goods receipt for PO #{$purchaseOrderId}");
                    }
                }
            }

            if ($errorResult !== null) {
                return $errorResult;
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

    // Returns the refreshed PurchaseOrder on success, or a failure Result the
    // caller hands straight back; process()'s catch owns rollback if anything
    // here throws instead of returning a Result.
    private function applyLinesInTransaction($lines, $warehouseId, $purchaseOrderId, $userId)
    {
        $this->transactionManager->beginTransaction();

        // Lock the PO row and re-check its status from that locking read: the
        // canReceiveGoods() check in process() ran unlocked, so a receipt racing
        // a cancel could otherwise re-open a Cancelled PO via recomputeStatus().
        // Concurrent receipts of the same PO also serialise here.
        // $outcome holds the first failing Result; on success the refreshed PO is
        // returned. Either way the single exit picks rollBack() vs commit().
        $outcome = null;
        $resultData = null;

        $poLockResult = $this->purchaseOrderRepository->lockForUpdate($purchaseOrderId);
        if ($poLockResult->code !== Result::CODE_SUCCESS) {
            $outcome = $poLockResult;
        } else {
            $lockedStatus = $poLockResult->data['status'] ?? null;
            if (!in_array($lockedStatus, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw new InvalidStateException('Goods can only be received for an Ordered or Partially Received purchase order.');
            }
        }

        if ($outcome === null) {
            // Sort ascending by productId before acquiring locks so that two concurrent
            // transactions touching the same products always lock in the same order,
            // eliminating the circular-wait condition that causes InnoDB deadlocks.
            // See ADR-002 §Multi-item deadlock prevention.
            usort($lines, static fn($a, $b) => $a['item']->productId <=> $b['item']->productId);

            foreach ($lines as $line) {
                $lineResult = $this->applyReceiptLine($line['item'], $line['qty'], $warehouseId, $purchaseOrderId, $userId);
                if ($lineResult instanceof Result) {
                    $outcome = $lineResult;
                    break;
                }
            }
        }

        if ($outcome === null) {
            $recomputeResult = $this->purchaseOrderService->recomputeStatus($purchaseOrderId);
            if ($recomputeResult->code !== Result::CODE_SUCCESS) {
                $outcome = $recomputeResult;
            }
        }

        if ($outcome === null) {
            $findResult = $this->purchaseOrderRepository->findById($purchaseOrderId);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                $outcome = $findResult;
            } else {
                $resultData = $findResult->data;
            }
        }

        if ($outcome !== null) {
            $this->transactionManager->rollBack();

            return $outcome;
        }

        $this->transactionManager->commit();

        return $resultData;
    }

    private function buildValidatedLines($receiptLines, $itemsById, $purchaseOrderId)
    {
        $lines = [];

        foreach ($receiptLines as $itemId => $quantityReceived) {
            $itemId = (int) $itemId;
            $quantityReceived = (int) $quantityReceived;

            if ($quantityReceived <= 0) {
                continue;
            }

            $item = $itemsById[$itemId] ?? null;
            if ($item === null || $item->purchaseOrderId !== $purchaseOrderId) {
                $result = new Result();
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Invalid purchase order line item.';
                $result->data = null;

                return $result;
            }

            if ($quantityReceived > $item->qtyRemaining()) {
                $result = new Result();
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Quantity received cannot exceed the quantity still outstanding for this line.';
                $result->data = null;

                return $result;
            }

            $lines[] = ['item' => $item, 'qty' => $quantityReceived];
        }

        return $lines;
    }

    // Returns null on success, or a failure Result the caller rolls back and hands straight back (BR-009).
    private function applyReceiptLine($item, $quantityReceived, $warehouseId, $purchaseOrderId, $userId)
    {
        // Each stock mutation runs only while the previous ones succeeded; the
        // first failing repository Result is returned for the caller to roll back.
        $failure = null;

        // Locking read: the received quantity is current and can't change until
        // commit, so the over-receipt check below holds under concurrency.
        $freshItemResult = $this->purchaseOrderItemRepository->findByIdForUpdate($item->id);
        if ($freshItemResult->code !== Result::CODE_SUCCESS) {
            $failure = $freshItemResult;
        } else {
            $freshItem = $freshItemResult->data;
            if ($freshItem === null || $quantityReceived > $freshItem->qtyRemaining()) {
                throw new InvalidStateException('Quantity received cannot exceed the quantity still outstanding for this line.');
            }
        }

        $lockResult = null;
        if ($failure === null) {
            $lockResult = $this->productStockRepository->lockForUpdate($item->productId, $warehouseId);
            if ($lockResult->code !== Result::CODE_SUCCESS) {
                $failure = $lockResult;
            }
        }

        if ($failure === null && $lockResult->data === null) {
            $createResult = $this->productStockRepository->create($item->productId, $warehouseId, 0);
            if ($createResult->code !== Result::CODE_SUCCESS) {
                $failure = $createResult;
            } else {
                $lockResult = $this->productStockRepository->lockForUpdate($item->productId, $warehouseId);
                if ($lockResult->code !== Result::CODE_SUCCESS) {
                    $failure = $lockResult;
                }
            }
        }

        if ($failure === null) {
            $incrementResult = $this->productStockRepository->incrementQuantity($item->productId, $warehouseId, $quantityReceived);
            if ($incrementResult->code !== Result::CODE_SUCCESS) {
                $failure = $incrementResult;
            }
        }

        if ($failure === null) {
            $insertResult = $this->stockLedgerRepository->insert([
                'product_id' => $item->productId,
                'warehouse_id' => $warehouseId,
                'type' => StockLedgerEntry::TYPE_RECEIPT,
                'qty' => $quantityReceived,
                'ref_type' => StockLedgerEntry::REF_PO,
                'ref_id' => $purchaseOrderId,
                'note' => null,
                'done_by_user_id' => $userId,
            ]);
            if ($insertResult->code !== Result::CODE_SUCCESS) {
                $failure = $insertResult;
            }
        }

        if ($failure === null) {
            $incrementReceivedResult = $this->purchaseOrderItemRepository->incrementQtyReceived($item->id, $quantityReceived);
            if ($incrementReceivedResult->code !== Result::CODE_SUCCESS) {
                $failure = $incrementReceivedResult;
            }
        }

        return $failure;
    }
}
