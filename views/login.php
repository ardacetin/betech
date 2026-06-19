<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $locale
 * @var string $errorMessage
 * @var string $redirectTarget
 * @var string $csrfToken
 */
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' · ' . $appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-full bg-[#f7f7f8] text-zinc-900 antialiased">
<div class="flex min-h-full flex-col items-center justify-center px-4 py-12">
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

        <form method="post" action="/login" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8') ?>">

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('login_username_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input
                    type="text"
                    name="identifier"
                    autocomplete="username"
                    required
                    class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"
                    placeholder="<?= htmlspecialchars(__('login_username_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                >
            </label>

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
        </form>
    </div>

    <p class="mt-8 text-xs text-zinc-400"><?= htmlspecialchars(__('login_footer'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
</body>
</html>
