<?php

namespace App\Service;

use App\Core\Result;
use App\Repository\Interface\SalesOrderRepositoryInterface;

/**
 * Sales-specific dashboard KPIs.
 * - KPI cards: revenue, order counts by status, pending approval, ready for issue
 * - Pipeline: status breakdown with count + percentage
 * - Top Customers: top 5 by revenue
 * - Recent Orders: latest SOs for table display
 * - Revenue trend: current period vs prior period
 */
class SalesDashboardService
{
    private $salesOrderRepository;

    public function __construct(SalesOrderRepositoryInterface $salesOrderRepository)
    {
        $this->salesOrderRepository = $salesOrderRepository;
    }

    /**
     * Returns all sales dashboard stats for the given user.
     *
     * @param int|null $userId  Scopes to a specific sales user; null = all users (admin/manager)
     * @param string   $period  'today' | 'week' | 'month' | 'all' (default 'month')
     */
    public function getStats(?int $userId, string $period = 'month'): array
    {
        [$dateFrom, $dateTo] = $this->dateRangeForPeriod($period);
        [$prevFrom, $prevTo] = $this->previousPeriodRange($period);

        // Counts, pipeline and rates follow the selected period too — previously
        // only revenue/top customers/recent orders did, and the rest stayed all-time.
        $byStatus = $this->countByStatus($userId, $dateFrom, $dateTo);
        $totalOrders = (int) array_sum($byStatus);

        $currentRevenue = $this->safeRevenue($userId, $dateFrom, $dateTo);
        $prevRevenue = $this->safeRevenue($userId, $prevFrom, $prevTo);
        $revenueTrend = $this->computeTrend($prevRevenue, $currentRevenue);

        $topCustomers = $this->topCustomers($userId, 5, $dateFrom, $dateTo);
        $recentOrders = $this->recentOrders($userId, 15, $dateFrom, $dateTo);

        // Pipeline: status breakdown with percentage of total
        $pipeline = [];
        $statusDefs = [
            'Draft' => ['label' => 'DRAFT', 'color' => '#5B6472'],
            'PendingApproval' => ['label' => 'PENDING APPROVAL', 'color' => '#B45309'],
            'Approved' => ['label' => 'APPROVED', 'color' => '#2563EB'],
            'Fulfilled' => ['label' => 'FULFILLED', 'color' => '#15803D'],
            'Cancelled' => ['label' => 'CANCELLED', 'color' => '#DC2626'],
        ];
        foreach ($statusDefs as $status => $def) {
            $count = (int) ($byStatus[$status] ?? 0);
            $pct = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
            $pipeline[] = [
                'status' => $status,
                'label' => $def['label'],
                'color' => $def['color'],
                'count' => $count,
                'percent' => $pct,
            ];
        }

        return [
            // KPI cards
            'total_revenue' => $currentRevenue,
            'revenue_trend_pct' => $revenueTrend,
            'total_orders' => $totalOrders,
            'pending_approval' => (int) ($byStatus['PendingApproval'] ?? 0),
            'ready_for_issue' => (int) ($byStatus['Approved'] ?? 0),

            // Pipeline
            'pipeline' => $pipeline,

            // Top customers
            'top_customers' => $topCustomers,

            // Recent orders for table
            'recent_orders' => $recentOrders,
        ];
    }

    private function countByStatus(?int $userId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $result = $this->salesOrderRepository->countByStatus($userId, $dateFrom, $dateTo);

        return $result->code === Result::CODE_SUCCESS ? ($result->data ?? []) : [];
    }

    private function safeRevenue(?int $userId, ?string $from, ?string $to): float
    {
        $result = $this->salesOrderRepository->totalRevenue($userId, $from, $to);

        return $result->code === Result::CODE_SUCCESS ? (float) ($result->data ?? 0) : 0.0;
    }

    private function topCustomers(?int $userId, int $limit, ?string $dateFrom, ?string $dateTo): array
    {
        $result = $this->salesOrderRepository->getTopCustomers($userId, $limit, $dateFrom, $dateTo);

        if ($result->code !== Result::CODE_SUCCESS) {
            return [];
        }

        $rows = $result->data ?? [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'customer_name' => $row['customer_name'] ?? '-',
                'total_value' => (float) ($row['total_value'] ?? 0),
                'order_count' => (int) ($row['order_count'] ?? 0),
            ];
        }

        return $out;
    }

    private function recentOrders(?int $userId, int $limit, ?string $dateFrom, ?string $dateTo): array
    {
        $filters = ['sort' => 'desc'];
        if ($dateFrom !== null) {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $filters['date_to'] = $dateTo;
        }

        $result = $this->salesOrderRepository->findAll($userId, $filters, $limit, 0);

        if ($result->code !== Result::CODE_SUCCESS) {
            return [];
        }

        $orders = $result->data ?? [];
        $out = [];
        foreach ($orders as $o) {
            $out[] = [
                'id' => $o->id,
                'so_number' => 'SO-' . str_pad((string) $o->id, 4, '0', STR_PAD_LEFT),
                'customer_name' => $o->customerName ?? '-',
                'order_date' => $o->orderDate ?? '',
                'items_count' => (int) ($o->itemsCount ?? 0),
                'total_value' => (float) ($o->totalValue ?? 0),
                'status' => $o->status ?? 'Draft',
            ];
        }

        return $out;
    }

    private function computeTrend(float $previous, float $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function dateRangeForPeriod(string $period): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'));
        $today = $now->format('Y-m-d');

        return match ($period) {
            'today' => [$today, $today],
            'week' => [$now->modify('monday this week')->format('Y-m-d'), $today],
            'month' => [$now->format('Y-m-01'), $today],
            default => [null, null],
        };
    }

    private function previousPeriodRange(string $period): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'));

        // The current period is always to-date (see dateRangeForPeriod), so the
        // comparison period must cover the SAME elapsed span ending at the
        // equivalent point one period earlier — otherwise the trend compares a
        // partial current window against a full prior window and is biased
        // negative early in the week/month.
        return match ($period) {
            'today' => [
                $now->modify('-1 day')->format('Y-m-d'),
                $now->modify('-1 day')->format('Y-m-d'),
            ],
            // Monday last week .. same weekday last week (current window shifted back 7 days).
            'week' => [
                $now->modify('monday this week')->modify('-7 days')->format('Y-m-d'),
                $now->modify('-7 days')->format('Y-m-d'),
            ],
            // First day of last month .. same day-of-month last month (clamped).
            'month' => $this->sameDayLastMonthRange($now),
            default => [null, null],
        };
    }

    // First-of-previous-month .. same day-of-month in the previous month, clamped
    // to that month's length so a day past its end (e.g. 31 Mar -> 28/29 Feb) does
    // not overflow into the wrong month.
    private function sameDayLastMonthRange(\DateTimeImmutable $now): array
    {
        $firstPrev = $now->modify('first day of previous month');
        $daysInPrev = (int) $firstPrev->format('t');
        $dayOfMonth = min((int) $now->format('j'), $daysInPrev);
        $prevEnd = $firstPrev->modify('+' . ($dayOfMonth - 1) . ' days');

        return [$firstPrev->format('Y-m-d'), $prevEnd->format('Y-m-d')];
    }
}
