<?php
/** @var string|null $message */
/** @var string|null $error */
/** @var string $email */
?>
<div class="login-page">
    <div class="login-page__card">

        <div class="login-page__brand">
            <div class="login-page__logo">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
            </div>
            <h1 class="login-page__title">Forgot your password?</h1>
            <p class="login-page__subtitle">Enter your email address and we will send you a link to choose a new one.</p>
        </div>

        <div class="login-page__form-card">
            <?php if ($message !== null): ?>
                <div class="alert alert--success" role="status">
                    <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <div class="alert alert--error" role="alert">
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="/forgot-password" novalidate>
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
                        maxlength="255"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="email"
                    >
                </div>
                <button type="submit" class="btn btn--primary btn--block">Send reset link</button>
                <p class="login-demo__hint"><a href="/login">Back to sign in</a></p>
            </form>
        </div>

    </div>
</div>
