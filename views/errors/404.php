<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $heading
 * @var string $message
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
<body class="min-h-full bg-white text-gray-900 antialiased">
<div class="flex min-h-full flex-col items-center justify-center px-4 py-16">
    <div class="w-full max-w-md text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 text-gray-400 shadow-sm">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
            </svg>
        </div>
        <p class="text-sm font-semibold uppercase tracking-wider text-gray-400">404</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-gray-900"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-3 text-sm leading-6 text-gray-500"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="mt-8">
            <a href="/" class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"></path>
                </svg>
                <?= htmlspecialchars(__('error_back_dashboard'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </div>
</div>
</body>
</html>
