<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $locale
 * @var array<string, mixed> $asset
 * @var array<string, mixed>|null $assignedUser
 * @var string $body
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        @page {
            margin: 18mm 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            color: #18181b;
            background: #fff;
            line-height: 1.65;
        }

        .sheet {
            max-width: 720px;
            margin: 0 auto;
            padding: 32px 24px 48px;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e4e4e7;
            font-size: 12px;
            color: #71717a;
        }

        .content {
            font-size: 15px;
            line-height: 1.65;
        }

        .content p {
            margin: 0 0 12px;
        }

        .content p:last-child {
            margin-bottom: 0;
        }

        .content ul,
        .content ol {
            margin: 0 0 12px 1.25rem;
            padding: 0;
        }

        .content li {
            margin-bottom: 6px;
        }

        .content strong,
        .content b {
            font-weight: 700;
        }

        .content .ql-align-center {
            text-align: center;
        }

        .content .ql-align-right {
            text-align: right;
        }

        .content .ql-align-justify {
            text-align: justify;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 56px;
        }

        .signature-block {
            padding-top: 48px;
            border-top: 1px solid #18181b;
        }

        .signature-label {
            font-size: 13px;
            font-weight: 600;
            color: #18181b;
        }

        .signature-hint {
            margin-top: 6px;
            font-size: 12px;
            color: #71717a;
        }

        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            justify-content: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid #e4e4e7;
            backdrop-filter: blur(8px);
        }

        .toolbar button {
            border: 1px solid #d4d4d8;
            background: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
        }

        .toolbar button.primary {
            background: #18181b;
            border-color: #18181b;
            color: #fff;
        }

        @media print {
            .toolbar {
                display: none;
            }

            .sheet {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()"><?= htmlspecialchars(__('tutanak_print'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" onclick="window.close()"><?= htmlspecialchars(__('tutanak_close'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>

    <div class="sheet">
        <div class="meta">
            <span><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars((string) $asset['asset_tag'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars((string) ($assignedUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="content"><?php /* Controlled Quill.js zimmet template HTML; placeholders are escaped server-side. */ ?><?= $body ?></div>

        <div class="signatures">
            <div>
                <div class="signature-block">
                    <p class="signature-label"><?= htmlspecialchars(__('tutanak_signature_employee'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="signature-hint"><?= htmlspecialchars((string) ($assignedUser['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div>
                <div class="signature-block">
                    <p class="signature-label"><?= htmlspecialchars(__('tutanak_signature_it'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="signature-hint"><?= htmlspecialchars(__('tutanak_signature_it_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                window.print();
            }, 350);
        });
    </script>
</body>
</html>
