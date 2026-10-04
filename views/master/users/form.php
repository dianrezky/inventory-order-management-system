<?php

/** @var string $csrfToken */
/** @var \App\Entity\User|null $user */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $user !== null;
$title = $isEdit ? 'Edit User' : 'New User';
$action = $isEdit ? '/users/' . $idObfuscator->encode($user->id) . '/update' : '/users';
$val = static function (string $field, string $default = '') use ($old, $user, $isEdit): string {
    if (array_key_exists($field, $old)) {
        return (string) $old[$field];
    }
    if ($isEdit && $user !== null) {
        return match ($field) {
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
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
                <label class="form-field__label" for="name" data-i18n="users.name">Name</label>
                <input class="input" type="text" id="name" name="name" required value="<?= htmlspecialchars($val('name'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="email" data-i18n="users.email">Email</label>
                <input class="input" type="email" id="email" name="email" required value="<?= htmlspecialchars($val('email'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-field">
                <label class="form-field__label" for="role" data-i18n="users.role">Role</label>
                <select class="input" id="role" name="role" required>
                    <?php foreach (['Admin', 'Sales', 'WarehouseStaff'] as $roleOption): ?>
                        <option value="<?= $roleOption ?>" <?= $val('role') === $roleOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars(\App\Entity\Role::labelFor($roleOption), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label class="form-field__label" for="password" data-i18n="users.password">Password</label>
                <input class="input" type="password" id="password" name="password" <?= $isEdit ? '' : 'required' ?>>
                <?php if ($isEdit): ?>
                    <p class="form-field__helper" data-i18n="users.password_hint">Leave blank to keep the current password.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary" data-i18n="common.save">Save</button>
            <a class="btn btn--secondary" href="/users" data-i18n="common.cancel">Cancel</a>
        </div>
    </form>
</div>
