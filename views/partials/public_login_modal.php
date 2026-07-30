<?php

declare(strict_types=1);

/**
 * @var string $csrfToken
 * @var bool $turnstileEnabled
 * @var string $turnstileSiteKey
 */
$csrfToken = (string) ($csrfToken ?? '');
$turnstileEnabled = (bool) ($turnstileEnabled ?? false);
$turnstileSiteKey = trim((string) ($turnstileSiteKey ?? ''));
?>
<div
    x-show="loginOpen"
    x-cloak
    class="modal-backdrop login-modal-backdrop"
    @keydown.escape.window="closeLogin()"
>
    <div style="position:absolute;inset:0;" @click="closeLogin()"></div>
    <div class="modal login-modal relative" role="dialog" aria-modal="true" aria-labelledby="login-modal-title" @click.stop>
        <button type="button" class="modal-close" @click="closeLogin()" aria-label="<?= htmlspecialchars(__('portal_close'), ENT_QUOTES, 'UTF-8') ?>">✕</button>
        <div class="login-modal-brand">
            <?php require __DIR__ . '/brand_icon.php'; ?>
            <h2 id="login-modal-title"><?= htmlspecialchars(__('login_heading'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(__('login_subheading'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <form method="post" action="/login" class="login-modal-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect" value="/">
            <label class="login-field">
                <span><?= htmlspecialchars(__('login_username_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input
                    type="text"
                    name="identifier"
                    autocomplete="username"
                    required
                    placeholder="<?= htmlspecialchars(__('login_username_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                >
            </label>
            <label class="login-field">
                <span><?= htmlspecialchars(__('login_password_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    placeholder="••••••••"
                >
            </label>
            <?php if ($turnstileEnabled && $turnstileSiteKey !== ''): ?>
                <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8') ?>" data-theme="light"></div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" style="width:100%;"><?= htmlspecialchars(__('login_submit'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </div>
</div>
<?php if ($turnstileEnabled && $turnstileSiteKey !== ''): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
