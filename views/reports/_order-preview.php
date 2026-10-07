<?php

// Preview of the order export (same columns/rows as the CSV). Filled by
// public/assets/js/reports.js from POST /reports/preview/orders when the
// #order-preview-btn button is clicked.
?>
<div class="report-table-card order-preview" id="order-preview" hidden>
    <div class="report-table-toolbar">
        <span class="report-filter-card__title">Export Preview</span>
        <span class="report-table-toolbar__counter" id="order-preview-counter" aria-live="polite"></span>
    </div>
    <div class="table-wrap">
        <table class="table" aria-label="Order export preview">
            <thead id="order-preview-head"></thead>
            <tbody id="order-preview-body"></tbody>
        </table>
    </div>
    <div class="report-pagination">
        <span class="report-pagination__info" id="order-preview-info"></span>
        <nav class="pagination" aria-label="Preview pagination">
            <button type="button" class="btn btn--secondary btn--sm" id="order-preview-prev" style="min-height:44px;">&laquo; Prev</button>
            <button type="button" class="btn btn--secondary btn--sm" id="order-preview-next" style="min-height:44px;">Next &raquo;</button>
        </nav>
    </div>
</div>
