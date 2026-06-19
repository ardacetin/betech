<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $locale
 * @var array<string, mixed> $asset
 * @var array<string, mixed>|null $assignedUser
 * @var string $operatorName
 * @var string $transactionDate
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
            max-width: 760px;
            margin: 0 auto;
            padding: 32px 24px 48px;
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

        .document-header {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 2px solid #18181b;
        }

        .document-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .document-date {
            margin: 10px 0 0;
            font-size: 13px;
            color: #52525b;
        }

        .asset-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            font-size: 14px;
        }

        .asset-table caption {
            text-align: left;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #18181b;
        }

        .asset-table th,
        .asset-table td {
            border: 1px solid #d4d4d8;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }

        .asset-table th {
            width: 34%;
            background: #f4f4f5;
            font-weight: 600;
            color: #3f3f46;
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
            gap: 40px;
            margin-top: 56px;
        }

        .signature-block {
            padding-top: 52px;
            border-top: 1px solid #18181b;
        }

        .signature-label {
            font-size: 13px;
            font-weight: 700;
            color: #18181b;
        }

        .signature-hint {
            margin-top: 6px;
            font-size: 12px;
            color: #71717a;
        }

        @media print {
            .toolbar {
                display: none !important;
            }

            .sheet {
                padding: 0;
                max-width: none;
            }

            body {
                background: #fff;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="primary" onclick="window.print()"><?= htmlspecialchars(__('tutanak_print'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" onclick="window.close()"><?= htmlspecialchars(__('tutanak_close'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>

    <div class="sheet">
        <header class="document-header">
            <h1 class="document-title"><?= htmlspecialchars(__('tutanak_document_title'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="document-date">
                <?= htmlspecialchars(__('tutanak_transaction_date'), ENT_QUOTES, 'UTF-8') ?>:
                <?= htmlspecialchars($transactionDate, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </header>

        <table class="asset-table">
            <caption><?= htmlspecialchars(__('tutanak_asset_details'), ENT_QUOTES, 'UTF-8') ?></caption>
            <tbody>
                <tr>
                    <th><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></th>
                    <td><?= htmlspecialchars((string) ($asset['asset_tag'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th><?= htmlspecialchars(__('label_serial_number'), ENT_QUOTES, 'UTF-8') ?></th>
                    <td><?= htmlspecialchars((string) ($asset['serial_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th><?= htmlspecialchars(__('tutanak_col_model'), ENT_QUOTES, 'UTF-8') ?></th>
                    <td><?= htmlspecialchars((string) ($asset['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></th>
                    <td><?= htmlspecialchars((string) ($asset['category_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?></th>
                    <td><?= htmlspecialchars((string) ($assignedUser['name'] ?? $asset['personnel_name'] ?? $asset['user_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="content"><?php /* Controlled Quill.js zimmet template HTML; placeholders are escaped server-side. */ ?><?= $body ?></div>

        <div class="signatures">
            <div>
                <div class="signature-block">
                    <p class="signature-label"><?= htmlspecialchars(__('tutanak_signature_handed_over'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="signature-hint"><?= htmlspecialchars($operatorName !== '' ? $operatorName : __('tutanak_signature_handed_over_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div>
                <div class="signature-block">
                    <p class="signature-label"><?= htmlspecialchars(__('tutanak_signature_received_by'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="signature-hint"><?= htmlspecialchars((string) ($assignedUser['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
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
