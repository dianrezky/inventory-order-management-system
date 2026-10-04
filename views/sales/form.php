<?php

/** @var \App\Entity\SalesOrder|null $so */
/** @var list<\App\Entity\Customer> $customers */
/** @var list<\App\Entity\Warehouse> $warehouses */
/** @var list<\App\Entity\Product> $products */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$errors ??= [];
$old ??= [];
$so ??= null;

$getOld = static fn (string $key, mixed $default = ''): mixed => $old[$key] ?? $default;

// Expose product list for sales-orders.js (loaded defer after this block)
$soProductListJson = json_encode(array_map(
    static fn (\App\Entity\Product $p): array => [
        'id'         => $p->id,
        'name'       => $p->name,
        'sku'        => $p->sku,
        'unit'       => $p->unit,
        'sale_price' => $p->salePrice,
    ],
    $products
));

// Line items the user already entered, so a server-side validation failure
// re-renders them instead of wiping the table back to one blank row.
$oldProductIds = (array) ($old['item_product_id'] ?? []);
$oldQtys = (array) ($old['item_qty'] ?? []);
$oldPrices = (array) ($old['item_sale_price'] ?? []);
$soOldItems = [];
foreach (array_values($oldProductIds) as $i => $productId) {
    $soOldItems[] = [
        'product_id' => (string) $productId,
        'qty'        => (string) (array_values($oldQtys)[$i] ?? ''),
        'sale_price' => (string) (array_values($oldPrices)[$i] ?? ''),
    ];
}
$soOldItemsJson = json_encode($soOldItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script>window._soProductList = <?= $soProductListJson ?>;</script>
<script>window._soOldItems = <?= $soOldItemsJson ?>;</script>
<div class="page-header">
    <div class="page-header__title-row">
        <a class="btn btn--tertiary" href="/sales-orders">&larr; Back</a>
        <h1 class="page-header__title" data-i18n="sales_orders.create_title">New Sales Order</h1>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert--error" role="alert">
        <?php foreach ($errors as $err): ?>
            <p><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/sales-orders" id="so-form" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="card">
        <div class="form-field">
            <label class="form-field__label" for="order_date" data-i18n="sales_orders.order_date">Order Date</label>
            <input type="date" id="order_date" name="order_date" class="input"
                   value="<?= htmlspecialchars($getOld('order_date', date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-grid">
            <div class="form-field">
                <label class="form-field__label" for="customer_id" data-i18n="sales_orders.customer">Customer</label>
                <select id="customer_id" name="customer_id" class="input" required>
                    <option value="">— Select —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c->id ?>" <?= (int) $getOld('customer_id') === $c->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c->name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label class="form-field__label" for="source_warehouse_id" data-i18n="sales_orders.source_warehouse">Source Warehouse</label>
                <select id="source_warehouse_id" name="source_warehouse_id" class="input" required>
                    <option value="">— Select —</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= $w->id ?>" <?= (int) $getOld('source_warehouse_id') === $w->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($w->name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field form-grid__full">
                <label class="form-field__label" for="note" data-i18n="sales_orders.note">Note</label>
                <textarea id="note" name="note" class="textarea" rows="2"><?= htmlspecialchars($getOld('note'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>
    </div>

    <fieldset class="fieldset">
        <legend class="fieldset__legend" data-i18n="sales_orders.items">Line Items</legend>

        <div class="table-wrap">
            <table class="table" id="items-table">
                <thead>
                <tr>
                    <th data-i18n="products.name">Name</th>
                    <th data-i18n="products.sku">SKU</th>
                    <th data-i18n="products.unit">Unit</th>
                    <th class="text--right" data-i18n="sales_orders.qty">Qty</th>
                    <th class="text--right" data-i18n="products.sale_price">Sale Price</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="items-body">
                </tbody>
            </table>
        </div>

        <button type="button" class="btn btn--secondary" id="add-item-btn" data-i18n="sales_orders.add_item">Add Line</button>
    </fieldset>

    <div class="form-actions form-actions--top-gap">
        <a class="btn btn--secondary" href="/sales-orders" data-i18n="common.cancel">Cancel</a>
        <button type="submit" class="btn btn--primary" data-i18n="common.save">Save</button>
    </div>
</form>
