<?php

namespace App\Controller;

use App\Core\Result;

class StockLedgerController extends BaseController
{
    // ================================================================
    // ROUTE CONSTANTS
    // ================================================================
    public const ROUTE_STOCK_LEDGER = '/stock-ledger';

    // ================================================================
    // TEMPLATE CONSTANTS
    // ================================================================
    public const TEMPLATE_STOCK_LEDGER = 'inventory/stock-ledger';
    public const TEMPLATE_PARTIAL_ROWS = 'inventory/_stock-ledger-rows';

    // ================================================================
    // CONFIG CONSTANTS
    // ================================================================
    public const PER_PAGE = 25;

    // ================================================================
    // MESSAGE CONSTANTS
    // ================================================================
    public const MESSAGE_QUERY_FAILED = 'query_failed';

    public function indexAction()
    {
        $authError = $this->requirePermission('stock_ledger.view');
        if ($authError !== null) {
            return $authError;
        }

        $page = max(1, (int) ($this->requestParam('page') ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        // movement_type: array from multi-select, or scalar
        $mtRaw = $this->requestParam('movement_type');
        $movementTypes = null;
        if (is_array($mtRaw)) {
            $movementTypes = array_values(array_filter($mtRaw, static fn ($v) => in_array($v, ['Receipt', 'Issue', 'Adjustment'], true)));
            if (count($movementTypes) === 0) { $movementTypes = null; }
        } elseif (is_string($mtRaw) && $mtRaw !== '') {
            $movementTypes = [$mtRaw];
        }

        // warehouse_id: array from multi-select, or scalar
        $whRaw = $this->requestParam('warehouse_id');
        $warehouseIds = null;
        if (is_array($whRaw)) {
            $ids = [];
            foreach ($whRaw as $v) {
                $id = filter_var($v, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0) { $ids[] = $id; }
            }
            $warehouseIds = count($ids) > 0 ? $ids : null;
        } elseif (is_string($whRaw) && $whRaw !== '') {
            $id = filter_var($whRaw, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) { $warehouseIds = [$id]; }
        }

        $filters = [
            'sku' => trim((string) ($this->requestParam('sku') ?? '')),
            'product_name' => trim((string) ($this->requestParam('product_name') ?? '')),
            'movement_type' => $movementTypes,
            'warehouse_id' => $warehouseIds,
            'sort_col' => trim((string) ($this->requestParam('sort_col') ?? 'done_at')),
            'sort_dir' => strtolower(trim((string) ($this->requestParam('sort_dir') ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC',
        ];

        $result = $this->container->getStockLedgerService()->findFiltered($filters, self::PER_PAGE, $offset);

        if ($result->code !== Result::CODE_SUCCESS) {
            // A JSON-only error response is correct for the AJAX filter/sort/
            // pagination calls (stock-ledger.js reads result.error), but this
            // same action also serves the page's own initial GET load — for
            // that request a bare JSON body would replace the entire page
            // with no layout and no way back, exactly the dead-end this
            // controller must never produce. Render the normal page with an
            // error banner and an empty table instead.
            if ($this->isXhr()) {
                return $this->json(['error' => self::MESSAGE_QUERY_FAILED, 'message' => $this->t($result->info)], 500);
            }

            return $this->view(self::TEMPLATE_STOCK_LEDGER, [
                'entries' => [],
                'total' => 0,
                'page' => 1,
                'totalPages' => 1,
                'perPage' => self::PER_PAGE,
                'filters' => $filters,
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'queryError' => $this->t($result->info),
            ]);
        }

        $entries = $result->data['entries'];
        $total = $result->data['total'];
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($this->isXhr()) {
            $response = $this->json([
                // idObfuscator isn't auto-injected here the way view() does it —
                // the partial links PO/SO references and needs it directly.
                'tbody' => $this->renderPartial(self::TEMPLATE_PARTIAL_ROWS, [
                    'entries' => $entries,
                    'idObfuscator' => $this->container->getIdObfuscator(),
                ]),
                'page' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
            ]);
        } else {
            $response = $this->view(self::TEMPLATE_STOCK_LEDGER, [
                'entries' => $entries,
                'total' => $total,
                'page' => $page,
                'totalPages' => $totalPages,
                'perPage' => self::PER_PAGE,
                'filters' => $filters,
                'warehouses' => $this->container->getWarehouseService()->listActiveWarehouses(),
                'queryError' => null,
            ]);
        }

        return $response;
    }

    private function renderPartial(string $view, array $data = []): string
    {
        $viewsPath = (string) $this->container->config('views.path');

        return (function () use ($viewsPath, $view, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            require $viewsPath . '/' . $view . '.php';
            return ob_get_clean();
        })();
    }
}
