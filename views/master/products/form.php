<?php

/** @var string $csrfToken */
/** @var \App\Entity\Product|null $product */
/** @var list<\App\Entity\Category> $categories */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $product !== null;
$mode = $isEdit ? 'Edit Product' : 'Add New Product';
$breadcrumbCurrent = $isEdit ? 'Edit ' . $product->sku : 'Create Product';
$action = $isEdit
    ? '/products/' . $idObfuscator->encode($product->id) . '/update'
    : '/products';

// Pull existing field values: prefer $old (after validation failure), else the entity
$val = static function (string $field, string $default = '') use ($old, $product, $isEdit): string {
    if (array_key_exists($field, $old)) {
        return (string) $old[$field];
    }
    if ($isEdit && $product !== null) {
        return (string) match ($field) {
            'sku' => $product->sku,
            'barcode' => $product->barcode ?? '',
            'name' => $product->name,
            'description' => $product->description ?? '',
            'category_id' => (string) $product->categoryId,
            'unit' => $product->unit,
            'purchase_price' => (string) (int) $product->purchasePrice,
            'sale_price' => (string) (int) $product->salePrice,
            'reorder_point' => (string) $product->reorderPoint,
            'initial_warehouse_id' => '',
            'initial_qty' => '0',
            default => $default,
        };
    }
    return $default;
};

// A failed save re-renders with $old, which must win over the stored value.
$isActive = array_key_exists('is_active', $old)
    ? !in_array((string) $old['is_active'], ['0', 'false'], true)
    : (!$isEdit || (bool) $product->isActive);

// Mirror the monetary input format: store as integer (no decimals), display with
// thousands separator in the live preview via JS, and submit as a plain integer.
$fmtInt = static fn (string $v): string =>
    number_format((int) preg_replace('/\D/', '', $v), 0, ',', '.');
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <span>Master Data</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/products">Products</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= htmlspecialchars($breadcrumbCurrent, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>

    <div class="page-header__title-row">
        <h1 class="page-header__title"><?= $isEdit ? 'Edit Product' : 'Add New Product' ?><?= $isEdit ? ': ' . htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') : '' ?></h1>
        <span class="badge <?= $isEdit ? 'badge--primary' : 'badge--info' ?>" style="padding:4px 10px;letter-spacing:0.04em;font-size:11px;">
            <?= $isEdit ? 'Edit Mode' : 'Create Mode' ?>
        </span>
    </div>

    <p class="page-header__subtitle">
        Configure catalog identity, inventory thresholds, units of measure, and pricing.
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
<script>
// Plain DOMContentLoaded (not App.ready()): this is a static, parser-inserted
// <script> that's part of the page's own initial HTML, unlike the per-page
// scripts (products.js, ...) the layout injects dynamically after the fact —
// so app.js (loaded later in the same document) is always ready by the time
// DOMContentLoaded actually fires here.
document.addEventListener('DOMContentLoaded', function () {
    App.report('failure', 'Changes Could Not Be Saved', <?= json_encode(implode(' ', $errors)) ?>);
});
</script>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" novalidate aria-busy="false">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="product-form-grid">
        <!-- Section 1: Basic Information -->
        <div class="product-form-section">
            <h2 class="product-form-section__title">Basic Information</h2>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="sku">SKU Code <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="SKU Code information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">SKU (Stock Keeping Unit) is a unique code used to identify and distinguish each product within the inventory system. Each product must have a distinct SKU. Maximum 30 characters. Use uppercase letters, numbers, and hyphens only.</span></button>
                </div>
                <input class="input" type="text" id="sku" name="sku" required maxlength="30"
                       value="<?= htmlspecialchars($val('sku'), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter The SKU Code Here..."
                       autocomplete="off" style="text-transform:uppercase;"
                       aria-required="true">
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="barcode">Barcode / EAN</label>
                    <button type="button" class="field-info-icon" aria-label="Barcode information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The product barcode number following EAN-13 or UPC-A standards. This field is optional and is used for integration with warehouse barcode scanners. Leave blank if the product does not have a barcode.</span></button>
                </div>
                <input class="input" type="text" id="barcode" name="barcode" maxlength="50"
                       value="<?= htmlspecialchars($val('barcode'), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter The Barcode Number Here..."
                       autocomplete="off">
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="name">Product Name <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Product name information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The product name displayed in the catalog and sales documents. Use a clear and descriptive name that is easy to identify. Minimum 3 characters, maximum 150 characters.</span></button>
                </div>
                <input class="input" type="text" id="name" name="name" required minlength="3" maxlength="150"
                       value="<?= htmlspecialchars($val('name'), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter The Product Name Here..."
                       aria-required="true">
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="description">Description</label>
                    <button type="button" class="field-info-icon" aria-label="Description information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">Detailed product description covering physical specifications (dimensions, material, weight), recommended storage conditions, handling instructions, and other relevant information to help users fully understand the product. This field is optional but highly recommended for products with technical specifications. Maximum 500 characters.</span></button>
                </div>
                <textarea class="input" id="description" name="description" rows="4" maxlength="500"
                          placeholder="Please Enter The Product Description Here. Include Physical Specifications, Storage Conditions, And Handling Instructions..."><?= htmlspecialchars($val('description'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <!-- Section 3 + 4 (right column): Status, Categorization, Initial Stock -->
        <div style="display:flex;flex-direction:column;gap:var(--space-4);">
            <!-- Section 3: Status & Categorization -->
            <div class="product-form-section">
                <h2 class="product-form-section__title">Status &amp; Categorization</h2>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="category_id">Category <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                        <button type="button" class="field-info-icon" aria-label="Category information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">Category is used to group products by type or function, such as Electronics, Mechanical Parts, Consumables, etc. Grouping facilitates search, reporting, and inventory analysis. Categories must be created first in Master Data &rarr; Categories before they can be selected here.</span></button>
                    </div>
                    <select class="input" id="category_id" name="category_id" required style="min-height:44px;" aria-required="true">
                        <option value="">— Please Select A Category —</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category->id ?>" <?= $val('category_id') === (string) $category->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="unit">Unit of Measure <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                        <button type="button" class="field-info-icon" aria-label="Unit of measure information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">The unit of measurement used to count and track the quantity of the product in inventory. This unit will be used consistently throughout the system including in purchase orders, sales orders, and stock reports. Choose the most common unit for this product, such as: Pcs (smallest unit), Box, Pack, Kg, Liter, Roll, or Set.</span></button>
                    </div>
                    <?php
                    // Stored units are free text (seed data uses lowercase "pcs", plus "sheet", "ream"…).
                    // Match case-insensitively and keep the stored spelling, and always offer the
                    // current unit — otherwise nothing is preselected, the select posts "" and every
                    // edit fails with "Unit of measure is required."
                    $currentUnit = trim($val('unit'));
                    $unitChoices = ['Pcs', 'Box', 'Pack', 'Kg', 'Liter', 'Roll', 'Set'];
                    $currentUnitListed = false;
                    foreach ($unitChoices as $u) {
                        if (strcasecmp($u, $currentUnit) === 0) {
                            $currentUnitListed = true;
                        }
                    }
                    ?>
                    <select class="input" id="unit" name="unit" required style="min-height:44px;" aria-required="true">
                        <option value="">— Please Select A Unit —</option>
                        <?php if ($currentUnit !== '' && !$currentUnitListed): ?>
                        <option value="<?= htmlspecialchars($currentUnit, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($currentUnit, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endif; ?>
                        <?php foreach ($unitChoices as $u): ?>
                        <?php $isCurrent = strcasecmp($u, $currentUnit) === 0; ?>
                        <option value="<?= htmlspecialchars($isCurrent ? $currentUnit : $u, ENT_QUOTES, 'UTF-8') ?>" <?= $isCurrent ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <span class="form-field__label">Product Status</span>
                    <div class="status-toggle" role="radiogroup" aria-label="Product status">
                        <label class="status-toggle__option <?= $isActive ? 'is-selected' : '' ?>">
                            <input type="radio" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> aria-label="Active">
                            <span class="status-toggle__icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <span class="status-toggle__text">
                                <strong>Active</strong>
                                <span>Available for orders</span>
                            </span>
                        </label>
                        <label class="status-toggle__option <?= !$isActive ? 'is-selected' : '' ?>">
                            <input type="radio" name="is_active" value="0" <?= !$isActive ? 'checked' : '' ?> aria-label="Inactive">
                            <span class="status-toggle__icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            </span>
                            <span class="status-toggle__text">
                                <strong>Inactive</strong>
                                <span>Hidden from new orders</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
            <!-- Section 4: Initial Stock Allocation (Create only) -->
            <div class="product-form-section">
                <h2 class="product-form-section__title">Initial Stock &amp; Notes</h2>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="initial_warehouse_id">Warehouse Location</label>
                        <button type="button" class="field-info-icon" aria-label="Warehouse location information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">The warehouse location where the initial product stock will be stored. If a warehouse is selected, the initial stock will be recorded directly in that warehouse. If there is no initial stock, select the option "— No initial allocation —". Warehouses must be created first in Master Data &rarr; Warehouses.</span></button>
                    </div>
                    <select class="input" id="initial_warehouse_id" name="initial_warehouse_id" style="min-height:44px;">
                        <option value="">— No initial allocation —</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= $warehouse->id ?>" <?= $val('initial_warehouse_id') === (string) $warehouse->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($warehouse->name, ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($warehouse->location)): ?> — <?= htmlspecialchars($warehouse->location, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="initial_qty">Initial Quantity</label>
                        <button type="button" class="field-info-icon" aria-label="Initial quantity information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">The initial stock quantity of the product to be allocated to the selected warehouse. If a value greater than 0 (zero) is entered, the system will automatically record an INITIAL_SETUP transaction in the Stock Ledger, indicating that this stock is an initial entry for a newly added product, not a result of purchase or sales.</span></button>
                    </div>
                    <input class="input" type="number" min="0" step="1" id="initial_qty" name="initial_qty"
                           value="<?= htmlspecialchars($val('initial_qty', '0'), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Please Enter The Initial Quantity Here...">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Section 2: Pricing & Inventory Control (full width below split) -->
        <div class="product-form-section product-form-section--full-width">
            <h2 class="product-form-section__title">Pricing &amp; Inventory Control</h2>

            <div class="product-form-grid product-form-grid--3col">
<div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="purchase_price_display">Base Purchase Price (Rp) <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Purchase price information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The base purchase price per unit of the product in Indonesian Rupiah (Rp). This price is used as a reference when creating Purchase Orders (PO) or when calculating COGS (Cost of Goods Sold). The entered value must be a whole number without decimals. The system will automatically format with periods as thousand separators when displayed.</span></button>
                </div>
                <div class="currency-input">
                    <span class="currency-input__prefix" aria-hidden="true">Rp</span>
                    <input class="input currency-input__field" type="text" inputmode="numeric" id="purchase_price_display" required
                           value="<?= htmlspecialchars(number_format((int) preg_replace('/\D/', '', $val('purchase_price', '0')), 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Please Enter The Purchase Price Here..."
                           aria-required="true">
                </div>
                <input type="hidden" name="purchase_price" id="purchase_price" value="<?= htmlspecialchars(preg_replace('/\D/', '', $val('purchase_price', '0')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="sale_price_display">Selling Price (Rp) <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Selling price information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The selling price per unit of the product in Indonesian Rupiah (Rp). This price will be used as the default price when creating Sales Orders (SO). The selling price must always be greater than or equal to the purchase price to prevent losses. The system will automatically format with periods as thousand separators.</span></button>
                </div>
                <div class="currency-input">
                    <span class="currency-input__prefix" aria-hidden="true">Rp</span>
                    <input class="input currency-input__field" type="text" inputmode="numeric" id="sale_price_display" required
                           value="<?= htmlspecialchars(number_format((int) preg_replace('/\D/', '', $val('sale_price', '0')), 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Please Enter The Selling Price Here..."
                           aria-required="true">
                </div>
                <input type="hidden" name="sale_price" id="sale_price" value="<?= htmlspecialchars(preg_replace('/\D/', '', $val('sale_price', '0')), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-field">
                <div class="form-field__label-row">
                    <label class="form-field__label" for="reorder_point">Minimum Stock Threshold <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                    <button type="button" class="field-info-icon" aria-label="Reorder point information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The minimum stock level that determines when the system should trigger a LOW_STOCK warning. When the actual stock quantity drops to or falls below this value, the system will activate an alert to remind users to reorder. This value must be greater than 0 (zero). Example: if the reorder point is 20, the alert will trigger when the stock is 20 or less.</span></button>
                </div>
                <input class="input" type="number" min="0" step="1" id="reorder_point" name="reorder_point" required
                       value="<?= htmlspecialchars(preg_replace('/\D/', '', $val('reorder_point', '10')), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Please Enter The Reorder Point Here..."
                       aria-required="true">
            </div>
            </div>
        </div>

        <!-- Section 5: Product Image (preserved from prior form) -->
        <div class="product-form-section product-form-section--full-width">
            <h2 class="product-form-section__title">Product Image</h2>
            <div class="form-field">
                <div class="form-field__label-row">
                    <span class="form-field__label" style="display:block;">Product Image</span>
                    <button type="button" class="field-info-icon" aria-label="Product image information">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span class="field-tooltip">The product image displayed in the catalog and reports. This field is optional. Supported formats: JPEG, PNG, or WebP. Maximum file size is 2MB. Large images will slow down page loading. It is recommended to use 800x800 pixels resolution with WebP format for optimal quality and file size.</span></button>
                </div>
                <?php if ($isEdit && $product->imagePath !== null): ?>
                <img class="table__thumb" style="margin-bottom: var(--space-2);" src="<?= htmlspecialchars($product->imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php endif; ?>
                <input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Product image" style="height:auto;padding:var(--space-2);">
            </div>
        </div>
    </div>

    <!-- Sticky Form Action Footer -->
    <div class="product-form-footer">
        <a class="btn btn--secondary" href="/products" style="border-color:#E1E4E8;color:#5B6472;">
            Cancel / Back
        </a>
        <div class="product-form-footer__actions">
            <button type="reset" class="btn btn--secondary" style="border-color:#E1E4E8;color:#1A1D23;">
                Reset Form
            </button>
            <button type="submit" class="btn btn--primary btn--submit" id="product-submit-btn" aria-busy="false">
                <span class="btn__label">Save Product</span>
                <span class="btn__spinner" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Live currency formatter for Rp inputs
    function bindCurrencyInput(displayId, hiddenId) {
        var display = document.getElementById(displayId);
        var hidden = document.getElementById(hiddenId);
        if (!display || !hidden) return;

        display.addEventListener('input', function () {
            var raw = display.value.replace(/\D/g, '');
            display.value = raw ? new Intl.NumberFormat('id-ID').format(parseInt(raw, 10)) : '';
            hidden.value = raw;
        });
        display.addEventListener('blur', function () {
            var raw = display.value.replace(/\D/g, '');
            display.value = raw ? new Intl.NumberFormat('id-ID').format(parseInt(raw, 10)) : '0';
            hidden.value = raw;
        });
    }
    bindCurrencyInput('purchase_price_display', 'purchase_price');
    bindCurrencyInput('sale_price_display', 'sale_price');

    // Submit-button loading state (16_ACTION_LOADING per spec §3.6)
    var form = document.querySelector('form.product-form-grid')
            || document.querySelector('form[enctype="multipart/form-data"]');
    var submitBtn = document.getElementById('product-submit-btn');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.disabled = true;
            var label = submitBtn.querySelector('.btn__label');
            if (label) label.textContent = 'Saving Product...';
        });
    }

    // Status toggle visual switching
    document.querySelectorAll('.status-toggle__option').forEach(function (option) {
        var radio = option.querySelector('input[type="radio"]');
        if (!radio) return;
        radio.addEventListener('change', function () {
            document.querySelectorAll('.status-toggle__option').forEach(function (o) {
                o.classList.remove('is-selected');
            });
            option.classList.add('is-selected');
        });
    });
});
</script>
