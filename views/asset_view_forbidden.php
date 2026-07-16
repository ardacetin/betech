<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var string $message
 */
?>
<div class="min-h-full bg-zinc-50">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-lg items-center gap-3 px-4 py-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white">B</div>
            <div>
                <p class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-zinc-500"><?= htmlspecialchars(__('asset_view_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-lg px-4 py-10">
        <div class="rounded-2xl border border-amber-200 bg-white px-6 py-8 text-center shadow-soft">
            <h1 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="mt-3 text-sm text-zinc-600"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="/login" class="mt-6 inline-flex rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">
                <?= htmlspecialchars(__('login_submit'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </main>
</div>
