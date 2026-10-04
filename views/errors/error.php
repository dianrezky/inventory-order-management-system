<?php

/** @var int $statusCode */
/** @var string $message */
/** @var bool $isLoggedIn */

$titles = [
    400 => 'Bad Request',
    403 => 'Access Denied',
    404 => 'Page Not Found',
];
$icons = [
    400 => 'icon-alert-triangle',
    403 => 'icon-lock',
    404 => 'icon-inbox',
];
$title = $titles[$statusCode] ?? 'Something Went Wrong';
$icon = $icons[$statusCode] ?? 'icon-alert-circle';
$homeHref = $isLoggedIn ? '/dashboard' : '/login';
$homeLabel = $isLoggedIn ? 'Go to Dashboard' : 'Go to Login';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — Inventory &amp; Order Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: var(--color-surface-container, #F5F6F8);
        }
        .error-page-card {
            max-width: 420px;
            width: 90%;
            padding: var(--space-6, 32px);
            border-radius: var(--radius-md, 12px);
            background: var(--color-surface-container-lowest, #fff);
            border: 1px solid var(--color-outline-variant, #e5e7eb);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .error-page-card__icon {
            width: 64px;
            height: 64px;
            margin: 0 auto var(--space-4, 16px);
            border-radius: 50%;
            background: #fee2e2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-page-card__code {
            font-family: var(--font-family-mono, monospace);
            font-size: 12px;
            letter-spacing: 0.08em;
            color: var(--color-on-surface-variant, #5b6472);
            text-transform: uppercase;
        }
        .error-page-card__title {
            margin: 4px 0 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--color-on-surface, #111);
        }
        .error-page-card__message {
            margin: 0 0 24px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--color-on-surface-variant, #5b6472);
        }
    </style>
</head>
<body>
    <div class="error-page-card">
        <div class="error-page-card__icon">
            <svg width="30" height="30" aria-hidden="true"><use href="/assets/img/icons.svg#<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></use></svg>
        </div>
        <div class="error-page-card__code"><?= (int) $statusCode ?></div>
        <h1 class="error-page-card__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="error-page-card__message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn--primary" href="<?= htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($homeLabel, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</body>
</html>
