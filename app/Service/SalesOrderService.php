<?php

namespace App\Service;

use App\Core\FakeTransactionManager;
use App\Core\Result;
use App\Core\TransactionManagerInterface;
use App\Entity\SalesOrder;
use App\Repository\Interface\CustomerRepositoryInterface;
use App\Repository\Interface\ProductRepositoryInterface;
use App\Repository\Interface\SalesOrderItemRepositoryInterface;
use App\Repository\Interface\SalesOrderRepositoryInterface;
use App\Repository\Interface\WarehouseRepositoryInterface;
use App\Service\Exception\DomainException;

// Sales Order lifecycle; Segregation of Duties (a sales user cannot approve their own order) is enforced via SalesOrderPolicy.
class SalesOrderService
{
    public const MESSAGE_FAILED_FUNCTION = Result::MESSAGE_FAILED_FUNCTION;
    public const MESSAGE_NOT_FOUND      = 'Record not found.';

    private $salesOrderRepository;
    private $salesOrderItemRepository;
    private $customerRepository;
    private $warehouseRepository;
    private $productRepository;
    private $policy;
    private $eventLogService;
    private $transactionManager;

    // $eventLogService is optional (nullable, default null) — see AuthService
    // for why: existing tests construct this Service directly without it.
    // $transactionManager is likewise optional: it defaults to a no-op
    // FakeTransactionManager so direct test construction keeps working, while
    // Container always wires the real Database so create() is atomic in
    // production (the sales_orders header and its line items commit together).
    public function __construct(
        SalesOrderRepositoryInterface $salesOrderRepository,
        SalesOrderItemRepositoryInterface $salesOrderItemRepository,
        CustomerRepositoryInterface $customerRepository,
        WarehouseRepositoryInterface $warehouseRepository,
        ProductRepositoryInterface $productRepository,
        SalesOrderPolicy $policy,
        EventLogService $eventLogService = null,
        TransactionManagerInterface $transactionManager = null
    ) {
        $this->salesOrderRepository = $salesOrderRepository;
        $this->salesOrderItemRepository = $salesOrderItemRepository;
        $this->customerRepository = $customerRepository;
        $this->warehouseRepository = $warehouseRepository;
        $this->productRepository = $productRepository;
        $this->policy = $policy;
        $this->eventLogService = $eventLogService;
        $this->transactionManager = $transactionManager ?? new FakeTransactionManager();
    }

    private function logEvent($actorId, $action, $id, $description)
    {
        if ($this->eventLogService !== null) {
            $this->eventLogService->record($actorId, $action, 'SalesOrder', $id, $description);
        }
    }

    public function listSalesOrders($userId = null, $filters = [], $limit = 0, $offset = 0)
    {
        $findResult = $this->salesOrderRepository->findAll($userId, $filters, $limit, $offset);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function countSalesOrders($userId = null, $filters = [])
    {
        $countResult = $this->salesOrderRepository->countAll($userId, $filters);

        return $countResult->code === Result::CODE_SUCCESS ? $countResult->data : 0;
    }

    public function findForExport($from, $to, $userId = null, $warehouseId = null)
    {
        // $from/$to are YYYY-MM-DD; $userId restricts the export to one sales owner (BR-018);
        // $warehouseId restricts it to one source warehouse.
        $findResult = $this->salesOrderRepository->findForExport($from, $to, $userId, $warehouseId);

        return $findResult->code === Result::CODE_SUCCESS ? $findResult->data : [];
    }

    public function findById($id)
    {
        $findResult = $this->salesOrderRepository->findById($id);
        if ($findResult->code !== Result::CODE_SUCCESS || $findResult->data === null) {
            return null;
        }

        $itemsResult = $this->salesOrderItemRepository->findBySalesOrderIdWithProduct($id);
        $items = $itemsResult->code === Result::CODE_SUCCESS ? $itemsResult->data : [];

        return $findResult->data->withItems($items);
    }

    public function create($input, $items, $createdByUserId)
    {
        $result = new Result();

        try {
            $customerId   = (int) ($input['customer_id'] ?? 0);
            $warehouseId  = (int) ($input['source_warehouse_id'] ?? 0);
            $orderDate    = trim((string) ($input['order_date'] ?? ''));
            $note         = trim((string) ($input['note'] ?? ''));

            // The first failing step wins; the atomic persist runs only after
            // every pre-flight check has passed, and $result is populated on success.
            $errorResult = null;
            $customerResult = $this->customerRepository->findById($customerId);
            if ($customerResult->code !== Result::CODE_SUCCESS) {
                $errorResult = $customerResult;
            }

            $warehouseResult = null;
            if ($errorResult === null) {
                $warehouseResult = $this->warehouseRepository->findById($warehouseId);
                if ($warehouseResult->code !== Result::CODE_SUCCESS) {
                    $errorResult = $warehouseResult;
                }
            }

            if ($errorResult === null) {
                $headerInfo = $this->headerInvalidInfo($customerResult->data, $warehouseResult->data, $orderDate);
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
                    $result->info = 'A sales order needs at least one line item.';
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            if ($errorResult === null) {
                // Header + line items must persist atomically: an item insert
                // failure must not leave a header with partial (or zero) items.
                $this->transactionManager->beginTransaction();

                $createResult = $this->salesOrderRepository->create([
                    'customer_id'           => $customerId,
                    'source_warehouse_id'   => $warehouseId,
                    'status'                => SalesOrder::STATUS_DRAFT,
                    'order_date'            => $orderDate,
                    'note'                  => $note === '' ? null : $note,
                    'created_by'            => $createdByUserId,
                ], $normalizedItems);
                if ($createResult->code !== Result::CODE_SUCCESS) {
                    $this->transactionManager->rollBack();
                    $errorResult = $createResult;
                } else {
                    $this->transactionManager->commit();

                    $salesOrder = $this->findById($createResult->data);
                    if ($salesOrder === null) {
                        $result->code = Result::CODE_VALIDATION;
                        $result->info = 'Could not create the record. Please try again.';
                        $result->data = null;
                    } else {
                        $result->code = Result::CODE_SUCCESS;
                        $result->info = 'The sales order has been created.';
                        $result->data = $salesOrder;

                        $this->logEvent($createdByUserId, 'create', $salesOrder->id, "Created SO #{$salesOrder->id} (Draft)");
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

    public function submitForApproval($id, $actorId)
    {
        $result = new Result();

        try {
            // The first failing step wins; $result is populated only on success.
            $errorResult = null;
            $salesOrder = $this->requireExisting($id);
            if ($salesOrder instanceof Result) {
                $errorResult = $salesOrder;
            } elseif ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Only a Draft sales order can be submitted for approval.';
                $result->data = null;
                $errorResult = $result;
            } elseif ($salesOrder->createdBy !== $actorId) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Only the sales person who created this order can submit it.';
                $result->data = null;
                $errorResult = $result;
            }

            if ($errorResult === null) {
                $updateResult = $this->salesOrderRepository->updateStatus($id, SalesOrder::STATUS_PENDING_APPROVAL);
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
                        $result->info = 'The sales order has been submitted for approval.';
                        $result->data = $refreshed;

                        $this->logEvent($actorId, 'submit', $id, "Submitted SO #{$id} for approval");
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

    public function approve($id, $actorId, $isActorAdmin)
    {
        $result = new Result();

        try {
            // The first failing step wins; $result is populated only on success.
            // SOD-01 is enforced by $this->policy->assertCanDecide() before any
            // status change — an unauthorized actor never reaches the update.
            $errorResult = null;
            $salesOrder = $this->requireExisting($id);
            if ($salesOrder instanceof Result) {
                $errorResult = $salesOrder;
            }

            if ($errorResult === null) {
                try {
                    $this->policy->assertCanDecide($isActorAdmin);
                } catch (DomainException $ex) {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = $ex->getMessage();
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            if ($errorResult === null && $salesOrder->status !== SalesOrder::STATUS_PENDING_APPROVAL) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Only a Pending Approval sales order can be approved.';
                $result->data = null;
                $errorResult = $result;
            }

            if ($errorResult === null) {
                $updateResult = $this->salesOrderRepository->updateStatus($id, SalesOrder::STATUS_APPROVED, [
                    'approved_by' => $actorId,
                    'approved_at' => date('Y-m-d H:i:s'),
                ]);
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
                        $result->info = 'The sales order has been approved.';
                        $result->data = $refreshed;

                        $this->logEvent($actorId, 'approve', $id, "Approved SO #{$id}");
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

    public function reject($id, $actorId, $isActorAdmin, $reason = null)
    {
        $result = new Result();

        try {
            // The first failing step wins; $result is populated only on success.
            // SOD-01 is enforced by $this->policy->assertCanDecide() before any
            // status change — an unauthorized actor never reaches the update.
            $errorResult = null;
            $salesOrder = $this->requireExisting($id);
            if ($salesOrder instanceof Result) {
                $errorResult = $salesOrder;
            }

            if ($errorResult === null) {
                try {
                    $this->policy->assertCanDecide($isActorAdmin);
                } catch (DomainException $ex) {
                    $result->code = Result::CODE_VALIDATION;
                    $result->info = $ex->getMessage();
                    $result->data = null;
                    $errorResult = $result;
                }
            }

            if ($errorResult === null && $salesOrder->status !== SalesOrder::STATUS_PENDING_APPROVAL) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = 'Only a Pending Approval sales order can be rejected.';
                $result->data = null;
                $errorResult = $result;
            }

            if ($errorResult === null) {
                $extras = [];
                if ($reason !== null && $reason !== '') {
                    $extras['cancellation_reason'] = trim($reason);
                }

                $updateResult = $this->salesOrderRepository->updateStatus($id, SalesOrder::STATUS_CANCELLED, $extras);
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
                        $result->info = 'The sales order has been rejected.';
                        $result->data = $refreshed;

                        $this->logEvent($actorId, 'reject', $id, "Rejected SO #{$id}" . ($reason ? " ({$reason})" : ''));
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

    public function cancel($id, $actorId, $isAdmin)
    {
        $result = new Result();

        try {
            $salesOrder = $this->requireExisting($id);
            if ($salesOrder instanceof Result) {
                return $salesOrder;
            }

            try {
                $this->policy->assertCanCancel($salesOrder, $actorId, $isAdmin);
            } catch (DomainException $ex) {
                $result->code = Result::CODE_VALIDATION;
                $result->info = $ex->getMessage();
                $result->data = null;

                return $result;
            }

            $updateResult = $this->salesOrderRepository->updateStatus($id, SalesOrder::STATUS_CANCELLED);
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
                $result->info = 'The sales order has been cancelled.';
                $result->data = $refreshed;

                $this->logEvent($actorId, 'cancel', $id, "Cancelled SO #{$id}");
            }
        } catch (\Throwable $e) {
            error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
            $result->code = Result::CODE_INTERNAL;
            $result->info = self::MESSAGE_FAILED_FUNCTION;
            $result->data = null;
        }

        return $result;
    }

    // Helpers

    private function headerInvalidInfo($customer, $warehouse, $orderDate)
    {
        $info = '';
        if ($customer === null || !$customer->isActive) {
            $info = 'Please select a valid, active customer.';
        } elseif ($warehouse === null || !$warehouse->isActive) {
            $info = 'Please select a valid, active warehouse.';
        } elseif ($orderDate === '' || \DateTime::createFromFormat('Y-m-d', $orderDate) === false) {
            $info = 'Please enter a valid order date.';
        }

        return $info;
    }

    private function requireExisting($id)
    {
        $salesOrder = $this->findById($id);
        if ($salesOrder === null) {
            $r = new Result();
            $r->code = Result::CODE_VALIDATION;
            $r->info = self::MESSAGE_NOT_FOUND;
            $r->data = null;

            return $r;
        }

        return $salesOrder;
    }

    private function normalizeAndValidateItems($items)
    {
        // Returns the normalized items array on success, or a failure Result
        $normalized = [];
        $errorResult = null;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity  = (int) ($item['qty'] ?? 0);
            $salePrice = (string) ($item['sale_price'] ?? '0');

            if ($productId <= 0 && $quantity <= 0) {
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
            } elseif ($quantity <= 0) {
                $lineError = 'Ordered quantity must be greater than zero.';
            } elseif (!is_numeric($salePrice) || (float) $salePrice < 0) {
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
                'product_id' => $productId,
                'qty'        => $quantity,
                'sale_price' => number_format((float) $salePrice, 2, '.', ''),
            ];
        }

        if ($errorResult !== null) {
            return $errorResult;
        }

        return $normalized;
    }
}
