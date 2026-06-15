<?php

declare(strict_types=1);

/**
 * @var string $content
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale ?? 'tr', ENT_QUOTES, 'UTF-8') ?>" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($csrfToken)): ?>
        <meta name="csrf-token" content="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle . ' · ' . $appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.14.8/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="h-full font-sans text-zinc-900 antialiased">
    <?= $content ?>
    <?php if (!empty($csrfToken)): ?>
        <script>
            (function () {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                if (token === '') {
                    return;
                }

                const originalFetch = window.fetch.bind(window);
                window.fetch = function (input, init) {
                    init = init ?? {};
                    const method = String(init.method ?? 'GET').toUpperCase();

                    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                        const headers = new Headers(init.headers ?? {});
                        if (!headers.has('X-CSRF-TOKEN')) {
                            headers.set('X-CSRF-TOKEN', token);
                        }
                        init.headers = headers;
                    }

                    return originalFetch(input, init);
                };
            })();
        </script>
    <?php endif; ?>
</body>
</html>
