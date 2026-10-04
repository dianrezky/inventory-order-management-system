// Dashboard auto-refreshes every 60s unless data-auto-refresh-interval overrides it.
(function () {
    'use strict';

    const REFRESH_INTERVAL_MS = 60000;
    let timerId = null;

    function scheduleRefresh() {
        if (timerId) clearTimeout(timerId);
        const el = document.querySelector('[data-auto-refresh]');
        if (!el) return;
        const interval = Number.parseInt(el.dataset.autoRefreshInterval || String(REFRESH_INTERVAL_MS), 10);
        timerId = setTimeout(function () {
            const refreshBtn = document.querySelector('[data-dashboard-refresh]');
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing…';
            }
            window.location.reload();
        }, interval);
    }

    App.ready(function () {
        // Manual refresh button
        const refreshBtn = document.querySelector('[data-dashboard-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing…';
                window.location.reload();
            });
        }

        // Auto-refresh
        if (document.querySelector('[data-auto-refresh]')) {
            scheduleRefresh();
        }

        // Notify if low-stock count is > 0
        const lowStockBadge = document.querySelector('[data-low-stock-count]');
        if (lowStockBadge) {
            const count = Number.parseInt(lowStockBadge.dataset.lowStockCount, 10);
            if (count > 0) {
                App.toast(count + ' product(s) are below reorder point.', 'warning', 8000);
            }
        }

        // Stock value trend indicator (simple visual, no real data)
        const invCard = document.querySelector('.stat-card__value');
        if (invCard?.textContent.trim() === 'Rp 0') {
            // No inventory data yet — no alert needed
        }
    });

    window.DashboardPage = { scheduleRefresh: scheduleRefresh };
})();
