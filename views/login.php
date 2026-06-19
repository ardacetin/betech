<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $locale
 * @var array<string, mixed> $loginConfig
 * @var string $errorMessage
 * @var string $redirectTarget
 * @var string $csrfToken
 */

$providers = $loginConfig['providers'] ?? [];
$showLocal = (bool) ($providers['local'] ?? false);
$showLdap = (bool) ($providers['ldap'] ?? false);
$showGoogle = (bool) ($loginConfig['has_google_sso'] ?? false);
$showMicrosoft = (bool) ($loginConfig['has_microsoft_sso'] ?? false);
$showCredentialForm = $showLocal || $showLdap;
$showAdminToggle = $showLocal && $showLdap;
$initialView = $showLdap ? 'default' : 'admin';
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' · ' . $appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-full bg-[#f7f7f8] text-zinc-900 antialiased">
<div
    class="flex min-h-full flex-col items-center justify-center px-4 py-12"
    x-data="{
        view: '<?= htmlspecialchars($initialView, ENT_QUOTES, 'UTF-8') ?>',
        showLdap: <?= $showLdap ? 'true' : 'false' ?>,
        showLocal: <?= $showLocal ? 'true' : 'false' ?>,
        showAdminToggle: <?= $showAdminToggle ? 'true' : 'false' ?>,
        loginMode() {
            if (this.view === 'admin') {
                return 'local';
            }

            return this.showLdap ? 'ldap' : 'local';
        }
    }"
>
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-900 text-base font-semibold text-white shadow-sm">B</div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('login_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-2 text-sm text-zinc-500"><?= htmlspecialchars(__('login_subheading'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="w-full max-w-[420px] rounded-2xl border border-zinc-200/80 bg-white p-8 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_16px_40px_rgba(0,0,0,0.06)]">
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($showCredentialForm): ?>
            <form method="post" action="/login" class="space-y-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="mode" :value="loginMode()">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8') ?>">

                <div x-show="view === 'default' && showLdap" x-cloak>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('login_username_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input
                            type="text"
                            name="identifier"
                            autocomplete="username"
                            :disabled="view !== 'default' || !showLdap"
                            :required="view === 'default' && showLdap"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10 disabled:hidden"
                            placeholder="<?= htmlspecialchars(__('login_username_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </label>
                </div>

                <div x-show="view === 'admin' && showLocal" x-cloak>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('login_email_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input
                            type="email"
                            name="identifier"
                            autocomplete="email"
                            :disabled="view !== 'admin' || !showLocal"
                            :required="view === 'admin' && showLocal"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10 disabled:hidden"
                            placeholder="<?= htmlspecialchars(__('login_email_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('login_password_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"
                        placeholder="••••••••"
                    >
                </label>

                <button
                    type="submit"
                    class="mt-2 w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900/20"
                >
                    <?= htmlspecialchars(__('login_submit'), ENT_QUOTES, 'UTF-8') ?>
                </button>

                <p x-show="view === 'default' && showAdminToggle" x-cloak class="pt-1 text-center">
                    <button
                        type="button"
                        @click="view = 'admin'"
                        class="text-xs font-medium text-zinc-500 transition hover:text-zinc-800"
                    >
                        <?= htmlspecialchars(__('login_admin_entry'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </p>

                <p x-show="view === 'admin' && showAdminToggle" x-cloak class="pt-1 text-center">
                    <button
                        type="button"
                        @click="view = 'default'"
                        class="text-xs font-medium text-zinc-500 transition hover:text-zinc-800"
                    >
                        <?= htmlspecialchars(__('login_back_to_user'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </p>
            </form>
        <?php endif; ?>

        <?php if ($showCredentialForm && ($showGoogle || $showMicrosoft)): ?>
            <div class="my-6 flex items-center gap-3">
                <div class="h-px flex-1 bg-zinc-200"></div>
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('login_or_divider'), ENT_QUOTES, 'UTF-8') ?></span>
                <div class="h-px flex-1 bg-zinc-200"></div>
            </div>
        <?php endif; ?>

        <div class="space-y-3">
            <?php if ($showGoogle): ?>
                <a
                    href="/auth/oauth/google"
                    class="flex w-full items-center justify-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm font-medium text-zinc-800 transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-900/10"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <?= htmlspecialchars(__('login_google_button'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>

            <?php if ($showMicrosoft): ?>
                <a
                    href="/auth/oauth/microsoft"
                    class="flex w-full items-center justify-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm font-medium text-zinc-800 transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-900/10"
                >
                    <svg class="h-5 w-5" viewBox="0 0 23 23" aria-hidden="true">
                        <path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/>
                        <path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/>
                    </svg>
                    <?= htmlspecialchars(__('login_microsoft_button'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$showCredentialForm && !$showGoogle && !$showMicrosoft): ?>
            <p class="text-center text-sm text-zinc-500"><?= htmlspecialchars(__('login_no_providers'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <p class="mt-8 text-xs text-zinc-400"><?= htmlspecialchars(__('login_footer'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
</body>
</html>
