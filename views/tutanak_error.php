<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $locale
 * @var string $message
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #fafafa;
            color: #18181b;
        }

        .card {
            max-width: 420px;
            padding: 24px;
            border: 1px solid #e4e4e7;
            border-radius: 16px;
            background: #fff;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="font-size: 18px; margin: 0 0 12px;"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p style="margin: 0; color: #71717a;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</body>
</html>
