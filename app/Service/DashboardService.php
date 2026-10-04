<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\ProductRepositoryInterface;
use App\Repository\Interface\ProductStockRepositoryInterface;
use App\Repository\Interface\PurchaseOrderRepositoryInterface;
use App\Repository\Interface\SalesOrderRepositoryInterface;
use App\Repository\Interface\EventLogRepositoryInterface;

class DashboardService
{
    private $productRepository;
    private $productStockRepository;
    private $purchaseOrderRepository;
    private $salesOrderRepository;
    private $eventLogRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductStockRepositoryInterface $productStockRepository,
        PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        SalesOrderRepositoryInterface $salesOrderRepository,
        EventLogRepositoryInterface $eventLogRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productStockRepository = $productStockRepository;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->salesOrderRepository = $salesOrderRepository;
        $this->eventLogRepository = $eventLogRepository;
    }

    // Uncapped KPI; falls back to the (capped) alert-list size if the count query fails
    private function countCriticalStock($fallback)
    {
        $countResult = $this->productStockRepository->countCriticalStock();
        if ($countResult->code === Result::CODE_SUCCESS) {
            return (int) $countResult->data;
        }

        return $fallback;
    }

    public function getAdminStats(): array
    {
        // Low stock at product level (total across warehouses) — Brief DASH-01
        $criticalResult = $this->productStockRepository->findCriticalStock(10);
        $criticalRows = $criticalResult->code === Result::CODE_SUCCESS ? ($criticalResult->data ?? []) : [];

        $lowStockWithStock = [];
        foreach ($criticalRows as $row) {
            $stockQty = (int) ($row['quantity'] ?? 0);
            $reorderPt = (int) ($row['reorder_point'] ?? 0);
            $lowStockWithStock[] = [
                'id' => $row['product_id'],
                'sku' => $row['sku'],
                'name' => $row['product_name'],
                'total_stock' => $stockQty,
                'reorder_point' => $reorderPt,
                'is_out' => $stockQty === 0,
                'is_critical' => $stockQty > 0 && $stockQty < $reorderPt,
            ];
        }

        // Recent transactions: 5 recent POs + 5 recent SOs
        $recentPO = $this->purchaseOrderRepository->findAll(
            null, 5, 0, null, 'desc', null
        );
        $recentPORows = $recentPO->code === Result::CODE_SUCCESS ? ($recentPO->data ?? []) : [];

        $recentSO = $this->salesOrderRepository->findAll(null, ['sort' => 'desc'], 5, 0);
        $recentSORows = $recentSO->code === Result::CODE_SUCCESS ? ($recentSO->data ?? []) : [];

        $recentTransactions = [];
        foreach ($recentPORows as $po) {
            $recentTransactions[] = [
                'id' => $po->id,
                'type' => 'Purchase',
                'ref' => 'PO-' . str_pad((string) $po->id, 4, '0', STR_PAD_LEFT),
                'partner' => $po->supplierName ?? '-',
                'date' => $po->orderDate ?? '',
                'status' => $po->status,
                'total' => $po->totalValue ?? 0,
            ];
        }
        foreach ($recentSORows as $so) {
            $recentTransactions[] = [
                'id' => $so->id,
                'type' => 'Sales',
                'ref' => 'SO-' . str_pad((string) $so->id, 4, '0', STR_PAD_LEFT),
                'partner' => $so->customerName ?? '-',
                'date' => $so->orderDate ?? '',
                'status' => $so->status,
                'total' => $so->totalValue ?? 0,
            ];
        }
        usort($recentTransactions, static fn ($a, $b) => strcmp($b['date'], $a['date']));
        $recentTransactions = array_slice($recentTransactions, 0, 10);

        // Audit trail: last 5 events
        // Format: [Waktu] — [Nama User] — [Aktivitas]
        $auditResult = $this->eventLogRepository->findAll([], 5, 0);
        $auditRows = $auditResult->code === Result::CODE_SUCCESS ? ($auditResult->data ?? []) : [];
        $auditTrail = [];
        foreach ($auditRows as $log) {
            $timeStr = $log->createdAt instanceof \DateTimeInterface ? $log->createdAt->format('H:i') : '';
            $auditTrail[] = [
                'time' => $timeStr,
                'actor' => $log->userName ?? 'System',
                'action' => $log->description,   // human-readable activity description
            ];
        }

        return [
            'inventory_value' => $this->totalInventoryValue(),
            'total_products' => $this->countProducts(),
            'low_stock_count' => $this->countCriticalStock(count($criticalRows)),
            'low_stock_products' => $lowStockWithStock,
            'po_by_status' => $this->countPurchaseOrdersByStatus(),
            'so_by_status' => $this->countSalesOrdersByStatus(null),
            'recent_transactions' => $recentTransactions,
            'audit_trail' => $auditTrail,
        ];
    }

    public function getSalesStats($userId): array
    {
        return $this->countSalesOrdersByStatus($userId);
    }

    public function getWarehouseStats(): array
    {
        $poCounts = $this->countPurchaseOrdersByStatus();
        $receiptQueue = ($poCounts['Ordered'] ?? 0) + ($poCounts['PartiallyReceived'] ?? 0);

        $soCounts = $this->countSalesOrdersByStatus(null);
        $issueQueue = $soCounts['Approved'] ?? 0;

        $criticalResult = $this->productStockRepository->findCriticalStock(10);
        $criticalRows = $criticalResult->code === Result::CODE_SUCCESS ? ($criticalResult->data ?? []) : [];

        $lowStockWithStock = [];
        foreach ($criticalRows as $row) {
            $stockQty = (int) ($row['quantity'] ?? 0);
            $reorderPt = (int) ($row['reorder_point'] ?? 0);
            $lowStockWithStock[] = [
                'id' => $row['product_id'],
                'sku' => $row['sku'],
                'name' => $row['product_name'],
                'total_stock' => $stockQty,
                'reorder_point' => $reorderPt,
                'is_out' => $stockQty === 0,
                'is_critical' => $stockQty > 0 && $stockQty < $reorderPt,
            ];
        }

        // The queues must be actionable: WarehouseStaff has no Sales Orders menu
        // (sales_orders.menu), so this list is its only path to an Approved SO's
        // "Process Goods Issue" button (docs/roles/ROLE-WAREHOUSE-STAFF.md SOP-W4).
        // Oldest first — that's the order the warehouse should work through them.
        $issueOrdersResult = $this->salesOrderRepository->findAll(null, ['status' => ['Approved'], 'sort' => 'asc'], 10, 0);
        $issueOrders = $issueOrdersResult->code === Result::CODE_SUCCESS ? ($issueOrdersResult->data ?? []) : [];

        $receiptOrdersResult = $this->purchaseOrderRepository->findAll(['Ordered', 'PartiallyReceived'], 10, 0, null, 'asc', null);
        $receiptOrders = $receiptOrdersResult->code === Result::CODE_SUCCESS ? ($receiptOrdersResult->data ?? []) : [];

        return [
            'po_receipt_queue' => $receiptQueue,
            'so_issue_queue' => $issueQueue,
            'po_receipt_orders' => $receiptOrders,
            'so_issue_orders' => $issueOrders,
            'low_stock_count' => $this->countCriticalStock(count($criticalRows)),
            'low_stock_products' => $lowStockWithStock,
        ];
    }

    private function countProducts(): int
    {
        $countResult = $this->productRepository->countAll();

        return $countResult->code === Result::CODE_SUCCESS ? (int) ($countResult->data ?? 0) : 0;
    }

    private function totalInventoryValue(): string
    {
        $valueResult = $this->productStockRepository->totalInventoryValue();

        return $valueResult->code === Result::CODE_SUCCESS ? (string) ($valueResult->data ?? '0') : '0';
    }

    private function countPurchaseOrdersByStatus(): array
    {
        $countResult = $this->purchaseOrderRepository->countByStatus();

        return $countResult->code === Result::CODE_SUCCESS ? ($countResult->data ?? []) : [];
    }

    private function countSalesOrdersByStatus($userId): array
    {
        $countResult = $this->salesOrderRepository->countByStatus($userId);

        return $countResult->code === Result::CODE_SUCCESS ? ($countResult->data ?? []) : [];
    }
}
