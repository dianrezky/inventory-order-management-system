<?php

namespace App\Service;

use App\Core\FakeTransactionManager;
use App\Core\Result;
use App\Core\TransactionManagerInterface;
use App\Entity\PurchaseOrder;
use App\Repository\Interface\ProductRepositoryInterface;
use App\Repository\Interface\PurchaseOrderItemRepositoryInterface;
use App\Repository\Interface\PurchaseOrderRepositoryInterface;
use App\Repository\Interface\SupplierRepositoryInterface;
use App\Repository\Interface\WarehouseRepositoryInterface;

class PurchaseOrderService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;
    public const MESSAGE_NOT_FOUND      = 'Record not found.';

    private $purchaseOrderRepository;
    private $purchaseOrderItemRepository;
    private $supplierRepository;
    private $warehouseRepository;
    private $productRepository;
    private $eventLogService;
    private $transactionManager;

    // $eventLogService is optional (nullable, default null) — see AuthService
    // for why: existing tests construct this Service directly without it.
    // $transactionManager is likewise optional: it defaults to a no-op
    // FakeTransactionManager so direct test construction keeps working, while
    // Container always wires the real Database so create() is atomic in
    // production (header + line items commit together or not at all).
    public function __construct(
        PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        PurchaseOrderItemRepositoryInterface $purchaseOrderItemRepository,
        SupplierRepositoryInterface $supplierRepository,
        WarehouseRepositoryInterface $warehouseRepository,
        ProductRepositoryInterface $productRepository,
        EventLogService $eventLogService = null,
        TransactionManagerInterface $transactionManager = null
    ) {
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->purchaseOrderItemRepository = $purchaseOrderItemRepository;
        $this->supplierRepository = $supplierRepository;
        $this->warehouseRepository = $warehouseRepository;
        $this->productRepository = $productRepository;
        $this->eventLogService = $eventLogService;
        $this->transactionManager = $transactionManager ?? new FakeTransactionManager();
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'PurchaseOrder', $id, $description);
        }
    }

    public function listPurchaseOrders($status = null)
    {
        $findResult = $this->purchaseOrderRepository->findAll($status);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function countAll($status = null, $search = null, $warehouseIds = null, $orderNumber = null, $supplierName = null)
    {
        $countResult = $this->purchaseOrderRepository->countAll($status, $search, $warehouseIds, $orderNumber, $supplierName);

        return $countResult->code === Result::CODE_SUCCESS ? $countResult->data : 0;
    }

    public function findForExport($from, $to, $warehouseId = null)
    {
        // $from/$to are YYYY-MM-DD; returns every purchase order in range with no pagination,
        // optionally restricted to one destination warehouse.
        $findResult = $this->purchaseOrderRepository->findForExport($from, $to, $warehouseId);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findAll($status = null, $limit = 0, $offset = 0, $search = null, $sortDirection = 'desc', $warehouseIds = null, $orderNumber = null, $supplierName = null)
    {
        $findResult = $this->purchaseOrderRepository->findAll($status, $limit, $offset, $search, $sortDirection, $warehouseIds, $orderNumber, $supplierName);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->purchaseOrderRepository->findById($id);
        if ($findResult->code !== Result::CODE_SUCCESS || $findResult->data === null) {
            return null;
        }

        $itemsResult = $this->purchaseOrderItemRepository->findByPurchaseOrderId($id);
        $items = $itemsResult->code === Result::CODE_SUCCESS ? $itemsResult->data : [];

        return $findResult->data->withItems($items);
    }

    public function create($input, $items, $createdByUserId)
    {
        $result = new Result();

        try {
            $supplierId   = (int) ($input['supplier_id'] ?? 0);
            $warehouseId   = (int) ($input['destination_warehouse_id'] ?? 0);
            $orderDate     = trim((string) ($input['order_date'] ?? ''));
            $note          = trim((string) ($input['note'] ?? ''));

            // The first failing step wins; the atomic persist runs only after
            // every pre-flight check has passed, and $result is populated on success.
            $errorResult = null;
            $supplierResult = $this->supplierRepository->findById($supplierId);
            if ($supplierResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $supplierResult;
            }

            $warehouseResult = null;
            if ($errorResult === null) {
                $warehouseResult = $this->warehouseRepository->findById($warehouseId);
                if ($warehouseResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $warehouseResult;
                }
            }

            if ($errorResult === null) {
                $headerInfo = $this->headerInvalidInfo($supplierResult->data, $warehouseResult->data, $orderDate);
                if ($headerInfo !== '') {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = $headerInfo;
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            $normalizedItems = null;
            if ($errorResult === null) {
                $normalizedItems = $this->normalizeAndValidateItems($items);
                if ($normalizedItems instanceof Result) {
                    $errorResult = $normalizedItems;
                } elseif (count($normalizedItems) === 0) {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = 'A purchase order needs at least one line item.';
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            if ($errorResult === null) {
                // Header + line items must persist atomically: a mid-loop item
                // insert failure must not leave a header with partial items.
                $this->transactionManager->beginTransaction();

                $createResult = $this->purchaseOrderRepository->create([
                    'supplier_id'              => $supplierId,
                    'destination_warehouse_id' => $warehouseId,
                    'status'                  => PurchaseOrder::STATUS_DRAFT,
                    'order_date'              => $orderDate,
                    'note'                    => $note === '' ? null : $note,
                    'created_by'              => $createdByUserId,
                ]);
                $itemsResult = null;
                if ($createResult->code !== Result::CODE_SUCCESS) {
                    $this->transactionManager->rollBack();
                    $errorResult = $createResult;
                } else {
                    $itemsResult = $this->createItems($createResult->data, $normalizedItems);
                }

                if ($errorResult === null && $itemsResult instanceof Result) {
                    $this->transactionManager->rollBack();
                    $errorResult = $itemsResult;
                } elseif ($errorResult === null) {
                    $this->transactionManager->commit();

                    $purchaseOrder = $this->findById($createResult->data);
                    if ($purchaseOrder === null) {
                        $result->code = Result::CODE_VALIDATION;
                        $result->info = 'Could not create the record. Please try again.';
                        $result->data = null;
                    } else {
                        $result->code = Result::CODE_SUCCESS;
                        $result->info = 'The purchase order has been created.';
                        $result->data = $purchaseOrder;

                        $this->logEvent($createdByUserId, 'create', $purchaseOrder->id, "Created PO #{$purchaseOrder->id} (Draft)");
                    }
                }
            }

            if ($errorResult !== null) {
                return $errorResult;
            }
        } catch (\Throwable $e) {
            // rollBack() is safe even if no transaction is open (Database guards
            // on inTransaction(); FakeTransactionManager is a no-op).
            $this->transactionManager->rollBack();
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function submit($id, $actorId = null)
    {
        $result = new Result();

        try {
            // The first failing step wins; $result is populated only on success.
            $errorResult = null;
            $purchaseOrder = $this->requireExisting($id);
            if ($purchaseOrder instanceof Result) {
                $errorResult = $purchaseOrder;
            } elseif ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Only a Draft purchase order can be submitted.';
                $result->data = null;
                $errorResult = $result;
            } elseif (count($purchaseOrder->items) === 0) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'A purchase order needs at least one line item.';
                $result->data = null;
                $errorResult = $result;
            }

            if ($errorResult === null) {
                $updateResult = $this->purchaseOrderRepository->updateStatus($id, PurchaseOrder::STATUS_ORDERED);
                if ($updateResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $updateResult;
                } else {
                    $refreshed = $this->findById($id);
                    if ($refreshed === null) {
                        $result->code = Result::CODE_VALIDATION;
                        $result->info = self::MESSAGE_NOT_FOUND;
                        $result->data = null;
                    } else {
                        $result->code = Result::CODE_SUCCESS;
                        $result->info = 'The purchase order has been submitted to the supplier.';
                        $result->data = $refreshed;

                        $this->logEvent($actorId, 'submit', $id, "Submitted PO #{$id} to supplier");
                    }
                }
            }

            if ($errorResult !== null) {
                return $errorResult;
            }
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function cancel($id, $actorId = null)
    {
        $result = new Result();

        try {
            $purchaseOrder = $this->requireExisting($id);
            if ($purchaseOrder instanceof Result) {
                return $purchaseOrder;
            }

            if (!$purchaseOrder->canBeCancelled()) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'This purchase order can no longer be cancelled — it has already been fully received or is already cancelled.';
                $result->data = null;

                return $result;
            }

            $updateResult = $this->purchaseOrderRepository->updateStatus($id, PurchaseOrder::STATUS_CANCELLED);
            if ($updateResult->code !== Result::CODE_SUCCESS) {
                return $updateResult;
            }

            $refreshed = $this->findById($id);

            if ($refreshed === null) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = self::MESSAGE_NOT_FOUND;
                $result->data = null;
            } else {
                $result->code = Result::CODE_SUCCESS;
                $result->info = 'The purchase order has been cancelled.';
                $result->data = $refreshed;

                $this->logEvent($actorId, 'cancel', $id, "Cancelled PO #{$id}");
            }
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    public function recomputeStatus($id)
    {
        $result = new Result();

        try {
            $purchaseOrder = $this->requireExisting($id);
            if ($purchaseOrder instanceof Result) {
                return $purchaseOrder;
            }

            $newStatus = $this->deriveStatusFromReceipts($purchaseOrder);
            if ($newStatus !== $purchaseOrder->status) {
                $updateResult = $this->purchaseOrderRepository->updateStatus($id, $newStatus);
                if ($updateResult->code !== Result::CODE_SUCCESS) {
                    return $updateResult;
                }
            }

            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The purchase order status has been recalculated.';
            $result->data = $newStatus;
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Helpers

    private function headerInvalidInfo($supplier, $warehouse, $orderDate)
    {
        $info = '';
        if ($supplier === null || !$supplier->isActive) {
            $info = 'Please select a valid, active supplier.';
        } elseif ($warehouse === null || !$warehouse->isActive) {
            $info = 'Please select a valid, active warehouse.';
        } elseif ($orderDate === '' || \DateTime::createFromFormat('Y-m-d', $orderDate) === false) {
            $info = 'Please enter a valid order date.';
        }

        return $info;
    }

    private function requireExisting($id)
    {
        $purchaseOrder = $this->findById($id);
        if ($purchaseOrder === null) {
            $r = new Result();
            $r->code = Result::CODE_VALIDATION;
            $r->info = self::MESSAGE_NOT_FOUND;
            $r->data = null;

            return $r;
        }

        return $purchaseOrder;
    }

    // Returns null on success, or a failure Result the caller hands straight back.
    private function createItems($purchaseOrderId, $items)
    {
        foreach ($items as $item) {
            $createResult = $this->purchaseOrderItemRepository->create([
                'purchase_order_id' => $purchaseOrderId,
                'product_id'        => $item['product_id'],
                'qty_ordered'       => $item['qty_ordered'],
                'purchase_price'   => $item['purchase_price'],
            ]);

            if ($createResult->code !== Result::CODE_SUCCESS) {
                return $createResult;
            }
        }

        return null;
    }

    private function deriveStatusFromReceipts($purchaseOrder)
    {
        $items  = $purchaseOrder->items;
        $status = $purchaseOrder->status;

        $allFull     = true;
        $anyReceived = false;

        foreach ($items as $item) {
            if ($item->qtyReceived > 0) {
                $anyReceived = true;
            }
            if (!$item->isFullyReceived()) {
                $allFull = false;
            }
        }

        if (count($items) === 0) {
            // no change
        } elseif ($allFull) {
            $status = PurchaseOrder::STATUS_RECEIVED;
        } elseif ($anyReceived) {
            $status = PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
        }

        return $status;
    }

    private function normalizeAndValidateItems($items)
    {
        // Returns the normalized items array on success, or a failure Result
        $normalized = [];
        $errorResult = null;

        foreach ($items as $item) {
            $productId        = (int) ($item['product_id'] ?? 0);
            $quantityOrdered  = (int) ($item['qty_ordered'] ?? 0);
            $purchasePrice    = (string) ($item['purchase_price'] ?? '0');

            if ($productId <= 0 && $quantityOrdered <= 0) {
                continue;
            }

            $productResult = $this->productRepository->findById($productId);
            if ($productResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $productResult;
                break;
            }

            $product = $productResult->data;
            $lineError = null;
            if ($product === null || !$product->isActive) {
                $lineError = 'Please select a valid, active product for every line.';
            } elseif ($quantityOrdered <= 0) {
                $lineError = 'Ordered quantity must be greater than zero.';
            } elseif (!is_numeric($purchasePrice) || (float) $purchasePrice < 0) {
                $lineError = 'Please enter a valid, non-negative price.';
            }

            if ($lineError !== null) {
                $errorResult = new Result();
                $errorResult->code = Result::CODE_VALIDATION;
                $errorResult->info = $lineError;
                $errorResult->data = null;
                break;
            }

            $normalized[] = [
                'product_id'      => $productId,
                'qty_ordered'     => $quantityOrdered,
                'purchase_price' => number_format((float) $purchasePrice, 2, '.', ''),
            ];
        }

        if ($errorResult !== null) {
            return $errorResult;
        }

        return $normalized;
    }
}
