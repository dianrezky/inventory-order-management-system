<?php

// Stock ledger <tr> rows. Shared by the full page render and the XHR
// pagination/filter response so both paths emit identical markup.
// Expects: $entries (array of ledger rows as associative arrays).
?>
<?php if ($entries === []): ?>
    <tr>
        <td colspan="9" class="text--center" style="padding:32px; color:var(--color-text-secondary)">
            No stock movements recorded yet.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($entries as $e):
        $type = (string) ($e['type'] ?? '');

        if ($type === 'Receipt') {
            $typeClass = 'badge--active';
        } elseif ($type === 'Issue') {
            $typeClass = 'badge--error';
        } elseif ($type === 'Adjustment') {
            $typeClass = 'badge--warning';
        } else {
            $typeClass = '';
        }

        // Derived from the actual stored qty sign, not the movement type —
        // Issue rows are always negative (GoodsIssueService), but Adjustment
        // rows can legitimately be either (StockLedgerService::recordAdjustment()
        // accepts any signed delta), so a type-based guess would mislabel a
        // negative (decrease) Adjustment as a "+" gain.
        $qtyValue = (int) ($e['qty'] ?? 0);
        $qtyPrefix = $qtyValue < 0 ? '−' : '+';
        $qtyAfter = (int) ($e['qty_after'] ?? 0);
        $qtyBefore = $qtyAfter - $qtyValue;

        $refType = (string) ($e['ref_type'] ?? '');
        $refId = (int) ($e['ref_id'] ?? 0);
        $refRoutePrefix = ['PO' => 'purchase-orders', 'SO' => 'sales-orders'][$refType] ?? null;

        if ($refRoutePrefix !== null && $refId !== 0) {
            $refLabel = '<a href="/' . $refRoutePrefix . '/' . $idObfuscator->encode($refId) . '">'
                . htmlspecialchars($refType . ' #' . $refId, ENT_QUOTES, 'UTF-8') . '</a>';
        } else {
            $refLabel = htmlspecialchars((string) ($e['note'] ?? '—'), ENT_QUOTES, 'UTF-8');
        }

        $doneAtRaw = (string) ($e['done_at'] ?? '');

        if ($doneAtRaw !== '') {
            $doneAt = date('d M Y H:i', (int) strtotime($doneAtRaw));
        } else {
            $doneAt = '—';
        }
    ?>
        <tr>
            <td><span class="font-mono" style="font-size:12px;"><?= htmlspecialchars((string) $e['sku'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= htmlspecialchars((string) $e['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($e['warehouse_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge <?= $typeClass ?>"><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= $qtyPrefix ?><?= abs((int) $e['qty']) ?></td>
            <td class="font-mono" style="white-space:nowrap;"><?= $qtyBefore ?> → <?= $qtyAfter ?></td>
            <td><?= $refLabel ?></td>
            <td><?= htmlspecialchars((string) ($e['user_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $doneAt ?></td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
