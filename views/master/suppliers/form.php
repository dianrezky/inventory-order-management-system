<?php

/** @var string $csrfToken */
/** @var \App\Entity\Supplier|null $supplier */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $supplier !== null;
$title = $isEdit ? 'Edit Supplier' : 'New Supplier';
$action = $isEdit ? '/suppliers/' . $idObfuscator->encode($supplier->id) . '/update' : '/suppliers';
$val = static function (string $field, string $default = '') use ($old, $supplier, $isEdit): string {
    if (array_key_exists($field, $old)) {
        return (string) $old[$field];
    }
    if ($isEdit && $supplier !== null) {
        return match ($field) {
            'name' => $supplier->name,
            'contact_person' => $supplier->contactPerson ?? '',
            'phone' => $supplier->phone ?? '',
            'email' => $supplier->email ?? '',
            'address' => $supplier->address ?? '',
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
                <label class="form-field__label" for="name" data-i18n="suppliers.name">Name</label>
                <input class="input" type="text" id="name" name="name" required value="<?= htmlspecialchars($val('name'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="contact_person" data-i18n="suppliers.contact_person">Contact Person</label>
                <input class="input" type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($val('contact_person'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="phone" data-i18n="suppliers.phone">Phone</label>
                <input class="input" type="text" id="phone" name="phone" value="<?= htmlspecialchars($val('phone'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="email" data-i18n="suppliers.email">Email</label>
                <input class="input" type="email" id="email" name="email" value="<?= htmlspecialchars($val('email'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="address" data-i18n="suppliers.address">Address</label>
                <input class="input" type="text" id="address" name="address" value="<?= htmlspecialchars($val('address'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary" data-i18n="common.save">Save</button>
            <a class="btn btn--secondary" href="/suppliers" data-i18n="common.cancel">Cancel</a>
        </div>
    </form>
</div>
