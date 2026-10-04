<?php

/** @var string $csrfToken */
/** @var \App\Entity\PurchaseOrder $po */
/** @var list<string> $errors */
?>
<div class="page-header">
    <h1 class="page-header__title" data-i18n="purchase_orders.receive_goods">Receive Goods — #<?= $po->id ?></h1>
</div>

<div class="card">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form method="post" action="/purchase-orders/<?= $idObfuscator->encode($po->id) ?>/receive" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th data-i18n="purchase_orders.product">Product</th>
                    <th data-i18n="purchase_orders.qty_ordered">Qty Ordered</th>
                    <th data-i18n="purchase_orders.qty_received">Qty Received</th>
                    <th data-i18n="purchase_orders.qty_remaining">Qty Remaining</th>
                    <th data-i18n="purchase_orders.qty_now">Receive Now</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($po->items as $item): ?>
                    <?php $remaining = $item->qtyRemaining(); ?>
                    <tr>
                        <td><?= htmlspecialchars(($item->productSku ?? '') . ' — ' . ($item->productName ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $item->qtyOrdered ?> <?= htmlspecialchars($item->productUnit ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $item->qtyReceived ?></td>
                        <td><?= $remaining ?></td>
                        <td>
                            <?php if ($remaining > 0): ?>
                                <input class="input input--sm" type="number" min="0" max="<?= $remaining ?>" step="1"
                                       name="qty_now[<?= $item->id ?>]" value="0"
                                       aria-label="Quantity to receive">
                            <?php else: ?>
                                <span class="text--muted" data-i18n="purchase_orders.fully_received">Fully received</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="form-field__helper" data-i18n="purchase_orders.receive_hint">Enter the quantity received now for each line. This cannot exceed the remaining quantity, and updates stock immediately and permanently once confirmed.</p>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary" data-i18n="purchase_orders.confirm_receipt">Confirm Receipt</button>
            <a class="btn btn--secondary" href="/purchase-orders/<?= $idObfuscator->encode($po->id) ?>" data-i18n="common.cancel">Cancel</a>
        </div>
    </form>
</div>
