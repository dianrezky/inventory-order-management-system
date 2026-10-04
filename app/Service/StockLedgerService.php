<?php

namespace App\Service;

use App\Core\Result;
use App\Core\TransactionManagerInterface;
use App\Repository\Interface\ProductStockRepositoryInterface;
use App\Repository\Interface\StockLedgerRepositoryInterface;

// LEDGER-01. All DB access goes through StockLedgerRepository (ARCH-01: only repositories may touch the database).
class StockLedgerService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;

    private $stockLedgerRepository;
    private $productStockRepository;
    private $transactionManager;

    // The stock repository and transaction manager are only needed by
    // recordInitialStock(); they're optional so read-only callers/tests can
    // keep constructing the service with the ledger repository alone.
    public function __construct(
        StockLedgerRepositoryInterface $stockLedgerRepository,
        ProductStockRepositoryInterface $productStockRepository = null,
        TransactionManagerInterface $transactionManager = null
    ) {
        $this->stockLedgerRepository = $stockLedgerRepository;
        $this->productStockRepository = $productStockRepository;
        $this->transactionManager = $transactionManager;
    }

    // DATA-02/03: a new product's opening stock and its ledger row are written
    // in ONE transaction, so a product_stocks quantity can never exist without
    // the Adjustment ledger entry that accounts for it (it used to be two
    // independent calls from the controller, with failures only logged).
    public function recordInitialStock($productId, $warehouseId, $quantity, $actorUserId)
    {
        $result = new Result();

        try {
            $this->transactionManager->beginTransaction();

            $createResult = $this->productStockRepository->create($productId, $warehouseId, $quantity);
            $ledgerResult = null;
            if ($createResult->code === Result::CODE_SUCCESS) {
                $ledgerResult = $this->recordAdjustment($productId, $warehouseId, $quantity, $actorUserId, 'INITIAL_SETUP');
            }

            if ($ledgerResult !== null && $ledgerResult->code === Result::CODE_SUCCESS) {
                $this->transactionManager->commit();
                $result->code = Result::CODE_SUCCESS;
                $result->info = 'Initial stock recorded.';
                $result->data = null;
            } else {
                $this->transactionManager->rollBack();
                $result->code = Result::CODE_INTERNAL;
                $result->info = self::MESSAGE_FAILED_FUNCTION;
                $result->data = null;
            }
        } catch (\Throwable $e) {
            $this->transactionManager->rollBack();
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    /**
     * Records a manual adjustment entry in the stock ledger.
     * Used for the product-create "initial stock allocation" hook and any
     * future walk-in inventory corrections. Type is fixed to 'Adjustment';
     * ref_type='Adjustment'; the human-readable reason goes into $note.
     */
    public function recordAdjustment(int $productId, int $warehouseId, int $quantityDelta, int $actorUserId, string $note = '')
    {
        return $this->stockLedgerRepository->insert([
            'product_id'      => $productId,
            'warehouse_id'    => $warehouseId,
            'type'            => 'Adjustment',
            'qty'             => $quantityDelta,
            'ref_type'        => 'Adjustment',
            'ref_id'          => null,
            'note'            => $note,
            'done_by_user_id' => $actorUserId,
        ]);
    }

    public function findFiltered($filters, $limit, $offset)
    {
        // $filters accepts sku, product_name, movement_type, warehouse_id, sort_col, sort_dir
        $result = new Result();

        try {
            $findResult = $this->stockLedgerRepository->findFiltered($filters, $limit, $offset);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $page = $findResult->data;

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to load stock ledger';
            $result->data = [
                'entries' => $page[0],
                'total' => (int) $page[1],
            ];
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function findForExport($filters)
    {
        // $filters carries date_from/date_to as YYYY-MM-DD and an optional
        // warehouse_id; returns every matching row in range with no pagination
        $result = new Result();

        $from = trim((string) ($filters['date_from'] ?? ''), " \t\n\r\x0B");
        $to = trim((string) ($filters['date_to'] ?? ''), " \t\n\r\x0B");
        $warehouseIdRaw = $filters['warehouse_id'] ?? null;
        $warehouseId = ($warehouseIdRaw !== null && $warehouseIdRaw !== '' && (int) $warehouseIdRaw > 0) ? (int) $warehouseIdRaw : null;
        $categoryIdRaw = $filters['category_id'] ?? null;
        $categoryId = ($categoryIdRaw !== null && $categoryIdRaw !== '' && (int) $categoryIdRaw > 0) ? (int) $categoryIdRaw : null;
        $search = trim((string) ($filters['q'] ?? ''));

        if ($from === '' || $to === '') {
            $result->code = Result::CODE_VALIDATION;
            $result->info = 'Please select both a start date and an end date.';
            $result->data = null;

            return $result;
        }

        try {
            ReportDateRangePolicy::assertValid($from, $to);
            $findResult = $this->stockLedgerRepository->findForExport($from, $to, $warehouseId, $categoryId, $search);
            if ($findResult->code !== Result::CODE_SUCCESS) {
                return $findResult;
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'Success to export stock ledger';
            $result->data = $findResult->data;
        } catch (\App\Service\Exception\DomainException $e) {
            $result->code = Result::CODE_VALIDATION;
            $result->info = $e->getMessage();
            $result->data = null;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Returns the most recent ledger entry for a product+warehouse pair (or null
    // if none exists). Used by the product detail page to show "Last Physical
    // Movement" alongside each warehouse row.
    public function getLastMovement($productId, $warehouseId)
    {
        $listResult = $this->stockLedgerRepository->listByProductWarehouse($productId, $warehouseId);
        if ($listResult->code !== Result::CODE_SUCCESS || empty($listResult->data)) {
            return null;
        }

        // listByProductWarehouse is ordered DESC by done_at; first row is the most recent.
        return $listResult->data[0];
    }

    // Returns the audit-trail entries linked to a specific order reference
    // (ref_type='PO'|'SO' + ref_id=<order_id>), ordered DESC by done_at.
    // Used by the PO/SO detail "Audit log" timeline.
    public function findByReference($refType, $refId, $limit = 50)
    {
        $result = $this->stockLedgerRepository->findByReference($refType, $refId, $limit);

        return $result->code === Result::CODE_SUCCESS ? $result->data : [];
    }
}
