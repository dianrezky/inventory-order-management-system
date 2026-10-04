<?php

/** @var string $csrfToken */
/** @var \App\Entity\Warehouse|null $warehouse */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $warehouse !== null;
$title = $isEdit ? 'Edit Warehouse' : 'New Warehouse';
$action = $isEdit ? '/warehouses/' . $idObfuscator->encode($warehouse->id) . '/update' : '/warehouses';
$val = static function (string $field, string $default = '') use ($old, $warehouse, $isEdit): string {
    if (array_key_exists($field, $old)) {
        return (string) $old[$field];
    }
    if ($isEdit && $warehouse !== null) {
        return match ($field) {
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'location' => $warehouse->location ?? '',
            default => $default,
        };
    }

    return $default;
};
?>
<div class="page-header">
    <h1 class="page-header__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
</div>

<div class="card">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">
            <div class="form-field">
                <label class="form-field__label" for="code" data-i18n="warehouses.code">Code</label>
                <input class="input" type="text" id="code" name="code" required value="<?= htmlspecialchars($val('code'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="name" data-i18n="warehouses.name">Name</label>
                <input class="input" type="text" id="name" name="name" required value="<?= htmlspecialchars($val('name'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="location" data-i18n="warehouses.location">Location</label>
                <input class="input" type="text" id="location" name="location" value="<?= htmlspecialchars($val('location'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary" data-i18n="common.save">Save</button>
            <a class="btn btn--secondary" href="/warehouses" data-i18n="common.cancel">Cancel</a>
        </div>
    </form>
</div>
