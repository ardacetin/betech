<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 */
?>
<div class="flex min-h-full items-center justify-center bg-zinc-50 px-4">
    <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-soft">
        <p class="text-sm font-semibold uppercase tracking-wide text-zinc-400">404</p>
        <h1 class="mt-2 text-xl font-semibold text-zinc-900"><?= htmlspecialchars(__('asset_not_found_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-3 text-sm text-zinc-500"><?= htmlspecialchars(__('asset_not_found_message'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
