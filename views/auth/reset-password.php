<?php
/** @var string|null $error */
/** @var string $token */
?>
<div class="login-page">
    <div class="login-page__card">

        <div class="login-page__brand">
            <div class="login-page__logo">
                <svg aria-hidden="true"><use href="/assets/img/icons.svg#icon-package"></use></svg>
            </div>
            <h1 class="login-page__title">Choose a new password</h1>
            <p class="login-page__subtitle">Use at least 6 characters.</p>
        </div>

        <div class="login-page__form-card">
            <?php if ($error !== null): ?>
                <div class="alert alert--error" role="alert">
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <div class="alert alert--error" role="alert" id="reset-missing-token" style="display:none">
                <span>This password reset link is incomplete. Open the link from your email again, or <a href="/forgot-password">request a new one</a>.</span>
            </div>
            <noscript>
                <div class="alert alert--error" role="alert">
                    <span>JavaScript is required to use the reset link. Please enable it and open the link again.</span>
                </div>
            </noscript>

            <form method="post" action="/reset-password" id="reset-form" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" id="reset-token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-field">
                    <label class="form-field__label" for="password">New password</label>
                    <input class="input" type="password" id="password" name="password" required minlength="6" autofocus autocomplete="new-password">
                </div>
                <div class="form-field">
                    <label class="form-field__label" for="password_confirmation">Confirm new password</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation" required minlength="6" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn--primary btn--block">Change password</button>
                <p class="login-demo__hint"><a href="/login">Back to sign in</a></p>
            </form>
        </div>

    </div>
</div>

<script>
// The token travels in the URL fragment (#token=...), which browsers never send to the server, so it stays out of
// access logs and Referer headers. Move it into the form and strip it from the address bar.
document.addEventListener('DOMContentLoaded', function () {
    var field = document.getElementById('reset-token');
    var form = document.getElementById('reset-form');
    var missing = document.getElementById('reset-missing-token');
    var match = /(?:^#|&)token=([0-9a-f]{64})(?:&|$)/.exec(window.location.hash);
    if (match) {
        field.value = match[1];
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname);
        }
    }
    if (!field.value) {
        form.style.display = 'none';
        missing.style.display = '';
    }
});
</script>
