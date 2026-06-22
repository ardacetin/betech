<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $locale
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" class="h-full">
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
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-600 text-base font-semibold text-white shadow-sm">403</div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('unauthorized_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-2 max-w-md text-sm text-zinc-500"><?= htmlspecialchars(__('unauthorized_message'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-3">
        <a href="/" class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800">
            <?= htmlspecialchars(__('unauthorized_back_home'), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <a href="/logout" class="inline-flex items-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50">
            <?= htmlspecialchars(__('nav_logout'), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>
</div>
</body>
</html>
