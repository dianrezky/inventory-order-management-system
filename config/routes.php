<?php

/**
 * Module Configuration - Routes
 *
 * Defines all application routes following DMS documentation standard.
 * Structure: route-name => [
 *     'type' => 'literal' | 'segment',
 *     'options' => [
 *         'route'       => '/path/{param}',
 *         'method'      => ['GET', 'POST', ...],
 *         'constraints' => ['param' => 'regex_pattern'],
 *         'defaults'    => [
 *             'controller' => Controller::class,
 *             'action'    => 'methodNameAction',
 *         ],
 *     ],
 *     'may_terminate' => true,
 *     'child_routes'   => [...], // sibling actions grouped under the same resource
 * ]
 *
 * Note: Load global config first for constants (REGEX_PATTERN, REGEX_NUMBER, etc.)
 */

require_once __DIR__ . '/global.php';

use App\Controller\AuthController;
use App\Controller\CategoryController;
use App\Controller\CustomerController;
use App\Controller\DashboardController;
use App\Controller\NotificationController;
use App\Controller\ProductApiController;
use App\Controller\ProductController;
use App\Controller\ProfileController;
use App\Controller\PurchaseOrderController;
use App\Controller\ReportController;
use App\Controller\SalesDashboardController;
use App\Controller\SalesOrderController;
use App\Controller\StockLedgerController;
use App\Controller\SupplierController;
use App\Controller\UserController;
use App\Controller\WarehouseController;

// Builds the standard 8-action CRUD child_routes block shared by the master-data
// resources (user/product/warehouse/supplier/customer): index+store share the bare
// resource path (split by method), the rest are literal/segment sub-paths.
$buildCrudChildRoutes = function (string $base, string $controller): array {
    return [
        'store' => [
            'type' => ROUTE_TYPE_LITERAL,
            'options' => [
                'route' => $base,
                'method' => ['POST'],
                'defaults' => ['controller' => $controller, 'action' => 'storeAction'],
            ],
        ],
        // The list page's filter/pagination/sort form posts here (not to $base,
        // which POST already routes to storeAction above) so search state never
        // lands in the address bar — same indexAction as the bare GET route,
        // just reading filters from $_POST via BaseController::requestParam().
        'search' => [
            'type' => ROUTE_TYPE_LITERAL,
            'options' => [
                'route' => $base . '/search',
                'method' => ['POST'],
                'defaults' => ['controller' => $controller, 'action' => 'indexAction'],
            ],
        ],
        'create-form' => [
            'type' => ROUTE_TYPE_LITERAL,
            'options' => [
                'route' => $base . '/create',
                'method' => ['GET'],
                'defaults' => ['controller' => $controller, 'action' => 'createFormAction'],
            ],
        ],
        'show' => [
            'type' => ROUTE_TYPE_SEGMENT,
            'options' => [
                'route' => $base . '/{id}',
                'method' => ['GET'],
                'constraints' => ['id' => REGEX_ID_TOKEN],
                'defaults' => ['controller' => $controller, 'action' => 'showAction'],
            ],
        ],
        'edit-form' => [
            'type' => ROUTE_TYPE_SEGMENT,
            'options' => [
                'route' => $base . '/{id}/edit',
                'method' => ['GET'],
                'constraints' => ['id' => REGEX_ID_TOKEN],
                'defaults' => ['controller' => $controller, 'action' => 'editFormAction'],
            ],
        ],
        'update' => [
            'type' => ROUTE_TYPE_SEGMENT,
            'options' => [
                'route' => $base . '/{id}/update',
                'method' => ['POST'],
                'constraints' => ['id' => REGEX_ID_TOKEN],
                'defaults' => ['controller' => $controller, 'action' => 'updateAction'],
            ],
        ],
        'deactivate' => [
            'type' => ROUTE_TYPE_SEGMENT,
            'options' => [
                'route' => $base . '/{id}/deactivate',
                'method' => ['POST'],
                'constraints' => ['id' => REGEX_ID_TOKEN],
                'defaults' => ['controller' => $controller, 'action' => 'deactivateAction'],
            ],
        ],
        'activate' => [
            'type' => ROUTE_TYPE_SEGMENT,
            'options' => [
                'route' => $base . '/{id}/activate',
                'method' => ['POST'],
                'constraints' => ['id' => REGEX_ID_TOKEN],
                'defaults' => ['controller' => $controller, 'action' => 'activateAction'],
            ],
        ],
    ];
};

return [
    // ============================================================
    // AUTHENTICATION ROUTES
    // ============================================================
    'login' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/login',
            'method' => ['GET', 'POST'],
            'defaults' => ['controller' => AuthController::class, 'action' => 'showLoginAction'],
        ],
        'may_terminate' => true,
        'child_routes' => [
            'submit' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/login',
                    'method' => ['POST'],
                    'defaults' => ['controller' => AuthController::class, 'action' => 'loginAction'],
                ],
            ],
        ],
    ],

    'logout' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/logout',
            'method' => ['POST'],
            'defaults' => ['controller' => AuthController::class, 'action' => 'logoutAction'],
        ],
        'may_terminate' => true,
    ],

    // ============================================================
    // DASHBOARD ROUTES
    // ============================================================
    'dashboard' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/dashboard',
            'method' => ['GET'],
            'defaults' => ['controller' => DashboardController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
    ],
    'sales-dashboard' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/sales-dashboard',
            // POST carries the period selector so it never lands in the address bar.
            'method' => ['GET', 'POST'],
            'defaults' => ['controller' => SalesDashboardController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
    ],
    'notifications_mark_all_read' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/notifications/mark-all-read',
            'method' => ['POST'],
            'defaults' => ['controller' => NotificationController::class, 'action' => 'markAllReadAction'],
        ],
        'may_terminate' => true,
    ],

    // ============================================================
    // USER MANAGEMENT ROUTES
    // ============================================================
    'user' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/users',
            'method' => ['GET'],
            'defaults' => ['controller' => UserController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => $buildCrudChildRoutes('/users', UserController::class),
    ],

    // ============================================================
    // PRODUCT MANAGEMENT ROUTES
    // ============================================================
    'product' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/products',
            'method' => ['GET'],
            'defaults' => ['controller' => ProductController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => $buildCrudChildRoutes('/products', ProductController::class),
    ],

    // ============================================================
    // WAREHOUSE MANAGEMENT ROUTES
    // ============================================================
    'warehouse' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/warehouses',
            'method' => ['GET'],
            'defaults' => ['controller' => WarehouseController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => $buildCrudChildRoutes('/warehouses', WarehouseController::class) + [
            // Stock-breakdown pagination on the detail page POSTs here so
            // stock_page never lands in the address bar.
            'stock-page' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/warehouses/{id}/stock',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => WarehouseController::class, 'action' => 'showAction'],
                ],
            ],
        ],
    ],

    // ============================================================
    // CATEGORY MANAGEMENT ROUTES
    // ============================================================
    'category' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/categories',
            'method' => ['GET'],
            'defaults' => ['controller' => CategoryController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => $buildCrudChildRoutes('/categories', CategoryController::class) + [
            // Categories-only additions on top of the shared CRUD block:
            // CSV export (read-only, GET) and a real hard delete (distinct
            // from the shared 'deactivate'/'activate' soft-toggle routes).
            'export' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/categories/export',
                    // POST: the Export CSV button submits the filter form itself
                    // (formaction/formmethod override) so the export always
                    // reflects the currently-applied filters without putting
                    // them in the URL.
                    'method' => ['GET', 'POST'],
                    'defaults' => ['controller' => CategoryController::class, 'action' => 'exportAction'],
                ],
            ],
            'delete' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/categories/{id}/delete',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => CategoryController::class, 'action' => 'deleteAction'],
                ],
            ],
        ],
    ],

    // ============================================================
    // SUPPLIER MANAGEMENT ROUTES
    // ============================================================
    'supplier' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/suppliers',
            'method' => ['GET'],
            'defaults' => ['controller' => SupplierController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => $buildCrudChildRoutes('/suppliers', SupplierController::class),
    ],

    // ============================================================
    // CUSTOMER MANAGEMENT ROUTES
    // ============================================================
    'customer' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/customers',
            'method' => ['GET'],
            'defaults' => ['controller' => CustomerController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => $buildCrudChildRoutes('/customers', CustomerController::class),
    ],

    // ============================================================
    // PURCHASE ORDER ROUTES
    // (own shape: no edit/update/deactivate/activate; has submit/cancel/receive)
    // ============================================================
    'purchase-order' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/purchase-orders',
            'method' => ['GET'],
            'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => [
            'store' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/purchase-orders',
                    'method' => ['POST'],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'storeAction'],
                ],
            ],
            'search' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/purchase-orders/search',
                    'method' => ['POST'],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'indexAction'],
                ],
            ],
            'create-form' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/purchase-orders/create',
                    'method' => ['GET'],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'createFormAction'],
                ],
            ],
            'show' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/purchase-orders/{id}',
                    'method' => ['GET'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'showAction'],
                ],
            ],
            'submit' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/purchase-orders/{id}/submit',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'submitAction'],
                ],
            ],
            'cancel' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/purchase-orders/{id}/cancel',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'cancelAction'],
                ],
            ],
            'receive-form' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/purchase-orders/{id}/receive',
                    'method' => ['GET'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'receiveFormAction'],
                ],
            ],
            'receive' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/purchase-orders/{id}/receive',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => PurchaseOrderController::class, 'action' => 'receiveAction'],
                ],
            ],
        ],
    ],

    // ============================================================
    // SALES ORDER ROUTES
    // (own shape: no edit/update/deactivate/activate; has submit/approve/reject/cancel/issue)
    // ============================================================
    'sales-order' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/sales-orders',
            'method' => ['GET'],
            'defaults' => ['controller' => SalesOrderController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
        'child_routes' => [
            'store' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/sales-orders',
                    'method' => ['POST'],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'storeAction'],
                ],
            ],
            'search' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/sales-orders/search',
                    'method' => ['POST'],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'indexAction'],
                ],
            ],
            'create-form' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/sales-orders/create',
                    'method' => ['GET'],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'createFormAction'],
                ],
            ],
            'show' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/sales-orders/{id}',
                    'method' => ['GET'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'showAction'],
                ],
            ],
            'submit' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/sales-orders/{id}/submit',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'submitAction'],
                ],
            ],
            'approve' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/sales-orders/{id}/approve',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'approveAction'],
                ],
            ],
            'reject' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/sales-orders/{id}/reject',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'rejectAction'],
                ],
            ],
            'cancel' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/sales-orders/{id}/cancel',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'cancelAction'],
                ],
            ],
            'issue' => [
                'type' => ROUTE_TYPE_SEGMENT,
                'options' => [
                    'route' => '/sales-orders/{id}/issue',
                    'method' => ['POST'],
                    'constraints' => ['id' => REGEX_ID_TOKEN],
                    'defaults' => ['controller' => SalesOrderController::class, 'action' => 'issueAction'],
                ],
            ],
        ],
    ],

    // ============================================================
    // REPORT ROUTES
    // ============================================================
    'report' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/reports',
            'method' => ['GET'],
            'defaults' => ['controller' => ReportController::class, 'action' => 'showFormAction'],
        ],
        'may_terminate' => true,
        'child_routes' => [
            // Filter/sort/pagination/report-type state is POSTed here so it
            // never lands in the address bar (same convention as {base}/search).
            'search' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/reports/search',
                    'method' => ['POST'],
                    'defaults' => ['controller' => ReportController::class, 'action' => 'showFormAction'],
                ],
            ],
            'export-stock-ledger' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/reports/export/stock-ledger',
                    'method' => ['POST'],
                    'defaults' => ['controller' => ReportController::class, 'action' => 'exportStockLedgerAction'],
                ],
            ],
            'export-orders' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/reports/export/orders',
                    'method' => ['POST'],
                    'defaults' => ['controller' => ReportController::class, 'action' => 'exportOrdersAction'],
                ],
            ],
        ],
    ],

    // ============================================================
    // STOCK LEDGER ROUTES
    // ============================================================
    'stock-ledger' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/stock-ledger',
            // POST: the filter/sort/pagination AJAX calls (stock-ledger.js) — kept
            // on GET too for the page's own initial full-page load.
            'method' => ['GET', 'POST'],
            'defaults' => ['controller' => StockLedgerController::class, 'action' => 'indexAction'],
        ],
        'may_terminate' => true,
    ],

    // ============================================================
    // PROFILE ROUTES
    // ============================================================
    'profile' => [
        'type' => ROUTE_TYPE_LITERAL,
        'options' => [
            'route' => '/my-profile',
            'method' => ['GET'],
            'defaults' => ['controller' => ProfileController::class, 'action' => 'showAction'],
        ],
        'may_terminate' => true,
        'child_routes' => [
            'update' => [
                'type' => ROUTE_TYPE_LITERAL,
                'options' => [
                    'route' => '/my-profile',
                    'method' => ['POST'],
                    'defaults' => ['controller' => ProfileController::class, 'action' => 'updateAction'],
                ],
            ],
        ],
    ],

    // ============================================================
    // API ROUTES
    // ============================================================
    'api-product-availability' => [
        'type' => ROUTE_TYPE_SEGMENT,
        'options' => [
            'route' => '/api/products/{sku}/availability',
            'method' => ['GET'],
            // Any SKU-shaped token (may start with a digit) must reach the
            // controller, so an unknown SKU gets the JSON 404 (API-01.05) rather
            // than the router's HTML 404.
            'constraints' => ['sku' => REGEX_CHARACTER],
            'defaults' => ['controller' => ProductApiController::class, 'action' => 'getAvailabilityAction'],
        ],
        'may_terminate' => true,
    ],
];
