<?php

/** @var \App\Entity\Category|null $category */
/** @var list<string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $category !== null;
$title = $isEdit ? 'Edit Category' : 'Create Category';
$action = $isEdit ? '/categories/' . $category->id . '/update' : '/categories';
$val = static function (string $field, string $default = '') use ($old, $category, $isEdit): string {
    if (array_key_exists($field, $old)) {
        return (string) $old[$field];
    }
    if ($isEdit && $category !== null) {
        return match ($field) {
            'name' => $category->name,
            'description' => $category->description ?? '',
            default => $default,
        };
    }
    return $default;
};
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <a href="/categories">Categories</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current"><?= $isEdit ? 'Edit' : 'Create' ?></span>
    </nav>
    <h1 class="page-header__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
</div>

<div class="card">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-field">
            <label for="name" class="form-field__label">Name</label>
            <input class="input" type="text" id="name" name="name"
                   value="<?= htmlspecialchars($val('name'), ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
        </div>

        <div class="form-field">
            <label for="description" class="form-field__label">Description</label>
            <textarea class="input" id="description" name="description" rows="4" maxlength="500"><?= htmlspecialchars($val('description'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $isEdit ? 'Save changes' : 'Create category' ?></button>
            <a class="btn btn--secondary" href="/categories">Cancel</a>
        </div>
    </form>
</div>
