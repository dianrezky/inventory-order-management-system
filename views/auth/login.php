<?php
/** @var string|null $error */
/** @var string $email */
/** @var string|null $notice */
$notice = $notice ?? null;
?>
<div class="login-page">
    <div class="login-page__card">

        <!-- Application Branding -->
        <div class="login-page__brand">
            <div class="login-page__logo">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
            </div>
            <h1 class="login-page__title">Inventory &amp; Order Management</h1>
            <p class="login-page__subtitle">Sign in to manage inventory and orders</p>
        </div>

        <!-- Form Card -->
        <div class="login-page__form-card">
            <?php if ($notice !== null): ?>
                <div class="alert alert--success" role="status">
                    <span><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <div class="alert alert--error" role="alert">
                    <svg aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="/login" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-field">
                    <label class="form-field__label" for="email">Email Address</label>
                    <input
                        class="input"
                        type="email"
                        id="email"
                        name="email"
                        required
                        autofocus
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="admin@example.com"
                        autocomplete="email"
                    >
                </div>
                <div class="form-field">
                    <label class="form-field__label" for="password">Password</label>
                    <input
                        class="input"
                        type="password"
                        id="password"
                        name="password"
                        required
                        placeholder="Enter your password"
                        autocomplete="current-password"
                    >
                </div>
                <button type="submit" class="btn btn--primary btn--block">
                    Sign In
                </button>
                <p class="login-demo__hint"><a href="/forgot-password">Forgot your password?</a></p>
            </form>
        </div>

        <!-- Demo accounts: one per role, for quick access during evaluation -->
        <div class="login-demo">
            <p class="login-demo__title">Demo accounts (1 per role)</p>
            <div class="login-demo__list">
                <button type="button" class="login-demo__item" data-email="admin@example.com" data-password="admin123">
                    <span class="login-demo__role badge badge--primary">Admin</span>
                    <span class="login-demo__creds">
                        <span class="login-demo__email">admin@example.com</span>
                        <span class="login-demo__password">admin123</span>
                    </span>
                </button>
                <button type="button" class="login-demo__item" data-email="sales1@example.com" data-password="sales123">
                    <span class="login-demo__role badge badge--info">Sales</span>
                    <span class="login-demo__creds">
                        <span class="login-demo__email">sales1@example.com</span>
                        <span class="login-demo__password">sales123</span>
                    </span>
                </button>
                <button type="button" class="login-demo__item" data-email="warehouse@example.com" data-password="wh123">
                    <span class="login-demo__role badge badge--neutral">Warehouse Staff</span>
                    <span class="login-demo__creds">
                        <span class="login-demo__email">warehouse@example.com</span>
                        <span class="login-demo__password">wh123</span>
                    </span>
                </button>
            </div>
            <p class="login-demo__hint">Click an account to fill the form, then Sign In.</p>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var emailField = document.getElementById('email');
    var passwordField = document.getElementById('password');
    document.querySelectorAll('.login-demo__item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!emailField || !passwordField) return;
            emailField.value = btn.dataset.email || '';
            passwordField.value = btn.dataset.password || '';
            passwordField.focus();
        });
    });
});
</script>
