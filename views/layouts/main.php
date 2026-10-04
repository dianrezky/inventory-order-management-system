<?php
// $content, $t and $currentUser are extracted into scope by BaseController::render().

/** @var string $content */
/** @var \App\Entity\User|null $currentUser */
/** @var string|null $pageTitle */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$req = trim($requestPath, '/');

$isActive = static function(string $path) use ($req): bool {
    $p = trim($path, '/');
    return $req === $p || str_starts_with($req, $p . '/');
};

$isLoggedIn  = $currentUser !== null;

// Sidebar visibility is driven by role_permissions (via PermissionService), not
// hardcoded Role comparisons — this is the single source of truth also read by
// each controller's requirePermission()/requirePermissionWithCsrf() guard, so a
// menu item can never drift out of sync with what its controller actually allows.
$grantedPermissions = $grantedPermissions ?? [];
$hasPermission = static function(string $key) use ($grantedPermissions): bool {
    return in_array($key, $grantedPermissions, true);
};

// Avatar initials from first + last name
$avatarInitials = '';
if ($currentUser !== null) {
    $parts = explode(' ', $currentUser->name);
    $avatarInitials = strtoupper(substr($parts[0] ?? '', 0, 1))
                 . strtoupper(substr($parts[1] ?? '', 0, 1));
}

// Role label
$roleLabel = $currentUser !== null ? $currentUser->role->label() : '';

// Header notification bell — same visibility rule as BaseController::view()
// applies when populating the data (Admin + WarehouseStaff only); this just
// decides whether the markup renders at all for everyone else (Sales).
$canSeeNotifications = $currentUser !== null
    && ($currentUser->role === \App\Entity\Role::Admin || $currentUser->role === \App\Entity\Role::WarehouseStaff);
$notificationsUnread = $notificationsUnread ?? [];
$notificationsUnreadCount = $notificationsUnreadCount ?? 0;

// Page title
$headerTitle = $pageTitle ?? 'Inventory & Order Management';

// Cache-busting query string for static assets (CSS/JS), derived from the
// newest file mtime under public/assets. Without this, browsers can keep
// serving a stale cached main.css/main.js after a deploy/fix (e.g. the
// account-menu dropdown fix) until the user manually hard-refreshes.
$assetVersion = static function(): string {
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    $latest = 0;
    $assetsDir = __DIR__ . '/../../public/assets';
    if (is_dir($assetsDir)) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($assetsDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $mtime = $file->getMTime();
                if ($mtime > $latest) {
                    $latest = $mtime;
                }
            }
        }
    }
    return (string) ($latest ?: time());
};
$assetVer = $assetVersion();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Google Fonts: Inter + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <!-- App stylesheets (cache-busted so fixes ship without a manual hard-refresh) -->
    <link rel="stylesheet" href="/assets/css/tokens.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/assets/css/components.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body data-page="<?= htmlspecialchars($req, ENT_QUOTES, 'UTF-8') ?>">

<!-- LOGIN SCREEN (no sidebar shell) -->
<?php if ($currentUser === null): ?>
<header class="app-header">
    <a class="app-header__brand" href="/">
        <svg width="20" height="20" aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
        <span>Inventory &amp; Order Management</span>
    </a>
    <div class="app-header__actions">
    </div>
</header>
<main class="app-main">
    <?= $content ?>
</main>

<!-- AUTHENTICATED APP SHELL -->
<?php else: ?>
<div class="app-shell">

    <!-- SIDEBAR -->
    <aside class="app-sidebar" id="app-sidebar" role="navigation" aria-label="Main navigation">

        <!-- Brand header -->
        <div class="app-sidebar__brand">
            <div class="app-sidebar__brand-inner">
                <div class="app-sidebar__logo" aria-hidden="true">IO</div>
                <div class="app-sidebar__brand-text">
                    <span class="app-sidebar__brand-name">Inventory &amp; Order Management</span>
                    <span class="app-sidebar__brand-sub">Enterprise Ops v1.0</span>
                </div>
            </div>
            <!-- Collapse toggle -->
            <button type="button" class="app-sidebar__toggle" id="sidebar-toggle-btn" aria-label="Toggle sidebar" title="Toggle sidebar">
                <svg width="16" height="16" aria-hidden="true"><use href="/assets/img/icons.svg#icon-menu"></use></svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="app-sidebar__nav" aria-label="Primary">

            <!-- Dashboard -->
            <div class="nav-group">
                <a class="nav-link nav-link--tall <?= $isActive('dashboard') ? 'is-active' : '' ?>" href="/dashboard">
                    <span class="nav-link__icon">
                        <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-layout-dashboard"></use></svg>
                    </span>
                    <span class="nav-link__text">Dashboard</span>
                </a>
            </div>

            <!-- Master Data -->
            <div class="nav-group">
                <div class="nav-group__title">Master Data</div>
                <a class="nav-link <?= $isActive('products') ? 'is-active' : '' ?>" href="/products">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg></span>
                    <span class="nav-link__text">Products</span>
                </a>
                <?php if ($hasPermission('master_data.menu')): ?>
                <a class="nav-link <?= $isActive('categories') ? 'is-active' : '' ?>" href="/categories">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-tag"></use></svg></span>
                    <span class="nav-link__text">Categories</span>
                </a>
                <a class="nav-link <?= $isActive('warehouses') ? 'is-active' : '' ?>" href="/warehouses">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-warehouse"></use></svg></span>
                    <span class="nav-link__text">Warehouses</span>
                </a>
                <a class="nav-link <?= $isActive('suppliers') ? 'is-active' : '' ?>" href="/suppliers">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-truck"></use></svg></span>
                    <span class="nav-link__text">Suppliers</span>
                </a>
                <a class="nav-link <?= $isActive('customers') ? 'is-active' : '' ?>" href="/customers">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-users"></use></svg></span>
                    <span class="nav-link__text">Customers</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- Procurement -->
            <?php if ($hasPermission('purchase_orders.manage')): ?>
            <div class="nav-group">
                <div class="nav-group__title">Procurement</div>
                <a class="nav-link <?= $isActive('purchase-orders') ? 'is-active' : '' ?>" href="/purchase-orders">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-file-text"></use></svg></span>
                    <span class="nav-link__text">Purchase Orders</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Sales -->
            <?php if ($hasPermission('sales_orders.menu')): ?>
            <div class="nav-group">
                <div class="nav-group__title">Sales</div>
                <a class="nav-link <?= $isActive('sales-orders') ? 'is-active' : '' ?>" href="/sales-orders">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-shopping-cart"></use></svg></span>
                    <span class="nav-link__text">Sales Orders</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Inventory -->
            <?php if ($hasPermission('stock_ledger.view')): ?>
            <div class="nav-group">
                <div class="nav-group__title">Inventory</div>
                <a class="nav-link <?= $isActive('stock-ledger') ? 'is-active' : '' ?>" href="/stock-ledger">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-list"></use></svg></span>
                    <span class="nav-link__text">Stock Ledger</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Reports -->
            <?php if ($hasPermission('reports.stock_ledger.view') || $hasPermission('reports.sales_orders.view') || $hasPermission('reports.purchase_orders.view')): ?>
            <div class="nav-group">
                <div class="nav-group__title">Reports</div>
                <a class="nav-link <?= $isActive('reports') ? 'is-active' : '' ?>" href="/reports">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-bar-chart"></use></svg></span>
                    <span class="nav-link__text">Reports</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Administration -->
            <?php if ($hasPermission('users.manage')): ?>
            <div class="nav-group">
                <div class="nav-group__title">Administration</div>
                <a class="nav-link <?= $isActive('users') ? 'is-active' : '' ?>" href="/users">
                    <span class="nav-link__icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-users"></use></svg></span>
                    <span class="nav-link__text">Users</span>
                </a>
            </div>
            <?php endif; ?>

        </nav>

    </aside>

    <!-- Mobile scrim -->
    <div class="app-sidebar-scrim" id="sidebar-scrim" role="button" tabindex="0" aria-label="Close menu" onclick="toggleSidebar()" onkeydown="if(event.key==='Enter'||event.key===' '||event.key==='Escape'){event.preventDefault();toggleSidebar();}"></div>

    <!-- MAIN AREA -->
    <div class="app-main">

        <!-- Top header bar -->
        <header class="app-header">
            <!-- Hamburger (mobile) -->
            <button type="button" class="icon-btn icon-btn--icon-only" onclick="toggleSidebar()" aria-label="Open navigation" id="mobile-menu-btn">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-menu"></use></svg>
            </button>

            <!-- Breadcrumb -->
            <nav class="app-header__breadcrumb" aria-label="Breadcrumb">
                <a href="/dashboard">Inventory &amp; Order Management</a>
                <span class="app-header__breadcrumb-sep" aria-hidden="true">/</span>
                <span class="app-header__breadcrumb-current"><?= htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8') ?></span>
            </nav>

            <!-- Right actions: notifications + user account menu -->
            <div class="app-header__actions">
                <!-- Notification bell (low-stock alerts; Admin & WarehouseStaff only) -->
                <?php if ($canSeeNotifications): ?>
                <div class="notif-menu" id="notif-menu">
                    <button type="button" class="icon-btn icon-btn--icon-only notif-menu__trigger" id="notif-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="notif-menu-dropdown" aria-label="Notifications">
                        <svg width="18" height="18" aria-hidden="true"><use href="/assets/img/icons.svg#icon-bell"></use></svg>
                        <?php if ($notificationsUnreadCount > 0): ?>
                            <span class="notif-menu__badge" aria-hidden="true"><?= $notificationsUnreadCount > 99 ? '99+' : (int) $notificationsUnreadCount ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notif-menu__dropdown" id="notif-menu-dropdown" aria-labelledby="notif-menu-btn" hidden>
                        <div class="notif-menu__header">
                            <span class="notif-menu__title">Notifications</span>
                            <?php if ($notificationsUnreadCount > 0): ?>
                                <span class="badge badge--error"><?= (int) $notificationsUnreadCount ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (count($notificationsUnread) > 0): ?>
                            <ul class="notification-list notif-menu__list">
                                <?php foreach ($notificationsUnread as $n): ?>
                                    <li class="notification-list__item">
                                        <span class="notification-list__icon">
                                            <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-alert-triangle"></use></svg>
                                        </span>
                                        <span class="notification-list__message"><?= htmlspecialchars($n->message, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="notification-list__time"><?= htmlspecialchars($n->createdAt?->format('Y-m-d H:i') ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <form class="notif-menu__footer" method="post" action="/notifications/mark-all-read">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn--secondary">Mark all as read</button>
                            </form>
                        <?php else: ?>
                            <p class="notif-menu__empty">You're all caught up — no unread notifications.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User account menu (click avatar/name to open) -->
                <div class="account-menu" id="account-menu">
                    <button type="button" class="user-info user-info--trigger" id="account-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="account-menu-dropdown">
                        <div class="user-info__avatar" aria-hidden="true">
                            <?= htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="user-info__meta">
                            <span class="user-info__name"><?= htmlspecialchars($currentUser->name, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="user-info__role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="account-menu__chevron" aria-hidden="true">
                            <svg width="14" height="14" aria-hidden="true"><use href="/assets/img/icons.svg#icon-chevron-down"></use></svg>
                        </span>
                    </button>

                    <div class="account-menu__dropdown" id="account-menu-dropdown" role="menu" aria-labelledby="account-menu-btn" hidden>
                        <a class="account-menu__item" href="/my-profile" role="menuitem">
                            <span class="account-menu__item-icon"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-user"></use></svg></span>
                            <span>Edit Profile</span>
                        </a>
                        <div class="account-menu__divider" role="separator"></div>
                        <form class="account-menu__item-form" method="post" action="/logout" role="presentation">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="account-menu__item account-menu__item--danger" role="menuitem">
                                <span class="account-menu__item-icon account-menu__item-icon--danger"><svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-log-out"></use></svg></span>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page content -->
        <div class="app-content">
            <?= $content ?>
        </div>

    </div>

</div><!-- /.app-shell -->
<?php endif; ?>

<!-- Shared app JS (window.App: toast, confirm, AJAX helper, CSRF token
     helper, the MultiSelect widget behind _multi-select.php, and more).
     Every page-specific script (products.js, categories.js, ...) calls
     App.* and was silently failing wherever it did, because this tag was
     never here — plain, unminified script tag (not the dynamic per-page
     loader below) so it's guaranteed to run and populate window.App
     before any later script, including the dynamically-injected
     per-page one, which only runs after DOMContentLoaded anyway. -->
<script src="/assets/js/app.js?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>"></script>

<!-- Sidebar toggle JS -->
<script>
function toggleSidebar() {
    var sidebar = document.getElementById('app-sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('is-open');
}

document.addEventListener('DOMContentLoaded', function() {
    // Close sidebar on link click (mobile)
    var sidebarLinks = document.querySelectorAll('.app-sidebar a');
    sidebarLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            var sidebar = document.getElementById('app-sidebar');
            if (sidebar && sidebar.classList.contains('is-open')) {
                sidebar.classList.remove('is-open');
            }
        });
    });

    // Desktop sidebar collapse/expand
    var toggleBtn = document.getElementById('sidebar-toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            var sidebar = document.getElementById('app-sidebar');
            if (!sidebar) return;
            sidebar.classList.toggle('is-collapsed');
            var isCollapsed = sidebar.classList.contains('is-collapsed');
            sidebar.style.width = isCollapsed ? '64px' : '256px';
            toggleBtn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        });
    }

    // Header dropdowns (account menu, notifications): click trigger to
    // toggle, click outside or Escape to close. Both menus share this same
    // open/close behavior, so it's factored into one helper instead of
    // duplicating it per menu. Each trigger's click handler stops the event
    // from bubbling to document (so opening a menu doesn't immediately
    // trigger the "click outside" listener for THAT SAME menu) — but with
    // more than one dropdown, that also stops the click from ever reaching
    // the other menu's "click outside" listener, so opening menu A would
    // never close menu B on its own. headerDropdowns tracks every instance
    // so open() can explicitly close all the others first.
    var headerDropdowns = [];

    function setupHeaderDropdown(rootId, btnId, dropdownId) {
        var root = document.getElementById(rootId);
        var btn = document.getElementById(btnId);
        var dropdown = document.getElementById(dropdownId);
        if (!root || !btn || !dropdown) return null;

        function close() {
            root.classList.remove('is-open');
            dropdown.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        }

        function open() {
            headerDropdowns.forEach(function(other) {
                if (other.close !== close) other.close();
            });
            root.classList.add('is-open');
            dropdown.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
        }

        btn.addEventListener('click', function(event) {
            event.stopPropagation();
            if (root.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        // Close when clicking anywhere outside this menu
        document.addEventListener('click', function(event) {
            if (!root.contains(event.target)) {
                close();
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                close();
                btn.focus();
            }
        });

        var instance = { open: open, close: close };
        headerDropdowns.push(instance);
        return instance;
    }

    // Account menu (top-right profile) dropdown
    setupHeaderDropdown('account-menu', 'account-menu-btn', 'account-menu-dropdown');

    // Notifications bell dropdown
    setupHeaderDropdown('notif-menu', 'notif-menu-btn', 'notif-menu-dropdown');
});
</script>

<!-- Page-specific JS auto-loader -->
<script>
(function () {
    'use strict';
    // The current page is resolved from the data-page attribute on <body>.
    var page = document.body ? document.body.dataset.page : '';
    var pageScripts = {
        'dashboard':              '/assets/js/dashboard.js',
        'products':               '/assets/js/products.js',
        'products/create':        '/assets/js/products.js',
        'categories':             '/assets/js/categories.js',
        'categories/create':      '/assets/js/categories.js',
        'warehouses':            '/assets/js/warehouses.js',
        'warehouses/create':      '/assets/js/warehouses.js',
        'suppliers':              '/assets/js/suppliers.js',
        'suppliers/create':       '/assets/js/suppliers.js',
        'customers':             '/assets/js/customers.js',
        'customers/create':       '/assets/js/customers.js',
        'users':                  '/assets/js/users.js',
        'users/create':           '/assets/js/users.js',
        'purchase-orders':       '/assets/js/purchase-orders.js',
        'purchase-orders/create': '/assets/js/purchase-orders.js',
        'sales-orders':          '/assets/js/sales-orders.js',
        'sales-orders/create':   '/assets/js/sales-orders.js',
        'reports':               '/assets/js/reports.js',
        'stock-ledger':          '/assets/js/stock-ledger.js',
        'my-profile':            '/assets/js/users.js',  // reuses users.js pattern
    };
    // Match exact path or prefix (e.g. "products/123" → "products")
    var key = page;
    if (!pageScripts[key]) {
        var segments = page.split('/');
        key = segments[0];
    }
    var src = pageScripts[key];
    if (src) {
        var s = document.createElement('script');
        s.src = src + '?v=<?= htmlspecialchars($assetVer, ENT_QUOTES, 'UTF-8') ?>';
        s.defer = true;
        document.head.appendChild(s);
    }
})();
</script>
</body>
</html>
