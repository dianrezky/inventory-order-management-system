<?php

/** @var \App\Entity\User $profileUser */
/** @var list<string> $profileErrors */
/** @var array<string,mixed> $profileOld */

$val = static function (string $field, string $default = '') use ($profileOld, $profileUser): string {
    if (is_array($profileOld) && array_key_exists($field, $profileOld)) {
        return (string) $profileOld[$field];
    }
    return match ($field) {
        'name' => $profileUser->name,
        'email' => $profileUser->email,
        default => $default,
    };
};
?>
<div class="page-header">
    <nav class="page-header__breadcrumb" aria-label="Breadcrumb">
        <a href="/dashboard">IOMS</a>
        <span class="page-header__breadcrumb-sep">/</span>
        <span>Account</span>
        <span class="page-header__breadcrumb-sep">/</span>
        <span class="page-header__breadcrumb-current">My Profile</span>
    </nav>
    <div class="page-header__title-row">
        <h1 class="page-header__title">My Profile</h1>
    </div>
    <p class="page-header__subtitle">View and update your account information.</p>
</div>

<?php if (!empty($profileUpdated)): ?>
    <div class="alert alert--success" role="alert">
        <svg aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;width:16px;height:16px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        Profile updated successfully.
    </div>
<?php endif; ?>

<?php if (!empty($profileErrors)): ?>
    <div class="alert alert--error" role="alert">
        <svg width="20" height="20" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <div>
            <?php foreach ($profileErrors as $err): ?>
            <span><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></span><br>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <!-- Avatar display -->
    <div class="profile-header">
        <?php
        $parts = explode(' ', (string) $profileUser->name);
        $initials = strtoupper(substr($parts[0] ?? '', 0, 1)) . strtoupper(substr($parts[1] ?? '', 0, 1));
        ?>
        <div class="avatar-initials">
            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div>
            <p style="font-size:18px;font-weight:600;margin:0;"><?= htmlspecialchars((string) $profileUser->name, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0;color:var(--color-text-secondary);font-size:14px;">
                <?= htmlspecialchars((string) $profileUser->role->label(), ENT_QUOTES, 'UTF-8') ?>
                &nbsp;&bull;&nbsp; Joined <?= $profileUser->createdAt ? $profileUser->createdAt->format('M Y') : '—' ?>
            </p>
        </div>
    </div>

    <form method="post" action="/my-profile" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <div class="product-form-grid">
            <div class="product-form-section">
                <h2 class="product-form-section__title">Account Information</h2>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="profile-name">Full Name <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                        <button type="button" class="field-info-icon" aria-label="Full name information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">Your full name as displayed across the system. This name will appear on your profile, in activity logs, and on documents you create. Please use your real name for proper identification.</span></button>
                    </div>
                    <input class="input" type="text" id="profile-name" name="name" required minlength="3" maxlength="100"
                           value="<?= htmlspecialchars($val('name'), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Please Enter Your Full Name Here..."
                           aria-required="true">
                </div>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="profile-email">Email Address <span aria-hidden="true" style="color:#DC2626;">*</span></label>
                        <button type="button" class="field-info-icon" aria-label="Email address information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">Your active email address used for system notifications, password recovery, and communication with other users. Must be a valid email format. Contact an administrator if you need to change your email address.</span></button>
                    </div>
                    <input class="input" type="email" id="profile-email" name="email" required maxlength="150"
                           value="<?= htmlspecialchars($val('email'), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Please Enter Your Email Address Here..."
                           aria-required="true">
                </div>

                <div class="form-field">
                    <div class="form-field__label-row">
                        <label class="form-field__label" for="profile-role">Role</label>
                        <button type="button" class="field-info-icon" aria-label="Role information">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="field-tooltip">Your assigned role determines your access permissions and capabilities within the system. Roles include Administrator, Operational Admin, Inventory Manager, Warehouse Staff, and Sales. To change your role, please contact a system Administrator.</span></button>
                    </div>
                    <input class="input" type="text" id="profile-role" value="<?= htmlspecialchars((string) $profileUser->role->label(), ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
            </div>
        </div>

        <div class="product-form-footer">
            <a class="btn btn--secondary" href="/dashboard" style="border-color:#E1E4E8;color:#5B6472;">
                Cancel / Back
            </a>
            <div class="product-form-footer__actions">
                <button type="submit" class="btn btn--primary btn--submit" id="profile-submit-btn" aria-busy="false">
                    <span class="btn__label">Save Changes</span>
                    <span class="btn__spinner" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[action="/my-profile"]');
    var submitBtn = document.getElementById('profile-submit-btn');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.disabled = true;
            var label = submitBtn.querySelector('.btn__label');
            if (label) label.textContent = 'Saving Profile...';
        });
    }
});
</script>
