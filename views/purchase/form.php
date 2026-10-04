<?php

/** @var string $csrfToken */
/** @var \App\Entity\PurchaseOrder|null $po (always null — create only, no edit) */
/** @var list<\App\Entity\Supplier> $suppliers */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var list<\App\Entity\Product> $products */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$oldItemProductIds = $old['item_product_id'] ?? [''];
$oldItemQtys = $old['item_qty_ordered'] ?? [''];
$oldItemPrices = $old['item_purchase_price'] ?? [''];
$rowCount = max(count($oldItemProductIds), 1);
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <span>Master Data</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/purchase-orders">Purchase Orders</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">Create Purchase Order</span>
    </nav>

    <div class="page-header__title-row">
        <h1 class="page-header__title">New Purchase Order</h1>
        <span class="badge badge--info" style="padding:4px 10px;letter-spacing:0.04em;font-size:11px;">Create Mode</span>
    </div>

    <p class="page-header__subtitle">
        Create a new purchase order to record product procurement from a supplier and track incoming goods shipments.
    </p>
</div>

<?php if (count($errors) > 0): ?>
<div class="alert alert--error" role="alert" aria-live="assertive" style="margin-bottom:var(--space-4);">
    <svg width="20" height="20" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
    </svg>
    <div>
        <strong>Changes could not be saved</strong>
        <ul style="margin: 6px 0 0 18px; padding:0;">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<form method="post" action="/purchase-orders" id="po-form" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="product-form-grid">
        <div class="product-form-section">
            <h2 class="product-form-section__title">Order Details</h2>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="supplier_id">Supplier <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Supplier information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The supplier from whom the products will be purchased. The supplier must be created first in Master Data &rarr; Suppliers before they can be selected here. Selecting the correct supplier ensures that the purchase order is linked to the right vendor for tracking and reporting purposes.</span></button>
                </div>
                <select class="input" id="supplier_id" name="supplier_id" required style="min-height:44px;" aria-required="true">
                    <option value="">— Please Select A Supplier —</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $supplier->id ?>" <?= (string) ($old['supplier_id'] ?? '') === (string) $supplier->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($supplier->name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="destination_warehouse_id">Destination Warehouse <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Destination warehouse information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The warehouse where the ordered products will be received and stored upon arrival. When a Goods Receipt (GR) is created for this purchase order, the stock will be added to the warehouse specified here. The warehouse must be created first in Master Data &rarr; Warehouses.</span></button>
                </div>
                <select class="input" id="destination_warehouse_id" name="destination_warehouse_id" required style="min-height:44px;" aria-required="true">
                    <option value="">— Please Select A Destination Warehouse —</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= $warehouse->id ?>" <?= (string) ($old['destination_warehouse_id'] ?? '') === (string) $warehouse->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($warehouse->name, ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($warehouse->location)): ?> — <?= htmlspecialchars($warehouse->location, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="order_date">Order Date <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Order date information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The date when this purchase order is issued or recorded in the system. This date will be used as the official PO date for all related documents and reports. Defaults to today's date. The date must be in a valid YYYY-MM-DD format.</span></button>
                </div>
                <input class="input" type="date" id="order_date" name="order_date" required
                       value="<?= htmlspecialchars((string) ($old['order_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Select The Order Date Here...">
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="note">Note</label>
                    <button type="button" class="field-info-icon" aria-label="Note information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">An optional field to add any additional notes, special instructions, or remarks related to this purchase order. For example: delivery instructions, payment terms, or any specific requirements for the supplier. Maximum 500 characters.</span></button>
                </div>
                <textarea class="input" id="note" name="note" rows="3" maxlength="500"
                          placeholder="Please Enter Any Additional Notes Or Special Instructions Here..."><?= htmlspecialchars((string) ($old['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <div class="product-form-section product-form-section--full-width">
            <h2 class="product-form-section__title">Line Items</h2>

            <div class="table-wrap">
                <table class="table" id="po-items-table">
                    <thead>
                    <tr>
                        <th style="min-width:240px;">
                            Product
                            <button type="button" class="field-info-icon" aria-label="Product column information" style="margin-left:4px;">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <span class="field-tooltip">The product being ordered. Select the product from the dropdown list. Each product row will automatically inherit its default purchase price from the product master data, but the price can be overridden manually for this specific order if needed.</span></button>
                        </th>
                        <th style="min-width:140px;">
                            Qty Ordered
                            <button type="button" class="field-info-icon" aria-label="Quantity ordered information" style="margin-left:4px;">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <span class="field-tooltip">The quantity of the selected product being ordered. Must be a whole number greater than or equal to 1. This quantity will be compared against the received quantity when creating a Goods Receipt (GR) to track partial deliveries.</span></button>
                        </th>
                        <th style="min-width:160px;">
                            Purchase Price (Rp)
                            <button type="button" class="field-info-icon" aria-label="Purchase price column information" style="margin-left:4px;">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <span class="field-tooltip">The purchase price per unit in Indonesian Rupiah (Rp) for this specific order. If left blank, the system will automatically use the default purchase price from the product master data. This price is used to calculate the total value of the purchase order.</span></button>
                        </th>
                        <th style="width:80px;"></th>
                    </tr>
                    </thead>
                    <tbody id="po-items-body">
                    <?php for ($i = 0; $i < $rowCount; $i++): ?>
                        <tr class="po-item-row">
                            <td>
                                <select class="input" name="item_product_id[]" style="min-height:44px;" aria-label="Select product">
                                    <option value="">— Please Select A Product —</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product->id ?>" data-price="<?= htmlspecialchars($product->purchasePrice, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= (string) ($oldItemProductIds[$i] ?? '') === (string) $product->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($product->sku . ' — ' . $product->name, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input class="input" type="number" min="1" step="1" name="item_qty_ordered[]"
                                       value="<?= htmlspecialchars((string) ($oldItemQtys[$i] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Please Enter The Quantity Here..."
                                       aria-label="Quantity ordered">
                            </td>
                            <td>
                                <input class="input" type="number" min="0" step="0.01" name="item_purchase_price[]"
                                       value="<?= htmlspecialchars((string) ($oldItemPrices[$i] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Please Enter The Purchase Price Here..."
                                       aria-label="Purchase price">
                            </td>
                            <td>
                                <button type="button" class="btn btn--tertiary po-item-remove" aria-label="Remove this line item">Remove</button>
                            </td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn--secondary" id="po-item-add" style="margin-top:var(--space-3);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:6px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Line Item
            </button>
        </div>
    </div>

    <!-- Sticky Form Action Footer -->
    <div class="product-form-footer">
        <a class="btn btn--secondary" href="/purchase-orders" style="border-color:#E1E4E8;color:#5B6472;">
            Cancel / Back
        </a>
        <div class="product-form-footer__actions">
            <button type="submit" class="btn btn--primary btn--submit" id="po-submit-btn" aria-busy="false">
                <span class="btn__label">Save Purchase Order</span>
                <span class="btn__spinner" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</form>
