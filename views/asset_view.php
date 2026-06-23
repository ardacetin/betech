<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var array<string, mixed> $asset
 * @var list<array{label: string, value: string}> $attributeRows
 */

$statusStyles = [
    'ready' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'deployed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'storage' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'broken' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
];

$status = (string) ($asset['status'] ?? 'ready');
$statusClass = $statusStyles[$status] ?? 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
$statusLabel = __('status_' . $status);
$assignedTo = trim((string) ($asset['assigned_to'] ?? $asset['user_name'] ?? ''));
$typeLabel = trim((string) ($asset['type'] ?? $asset['category_name'] ?? ''));
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

    <main class="mx-auto max-w-lg px-4 py-6">
        <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="border-b border-zinc-200 bg-zinc-900 px-5 py-6 text-white">
                <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-2xl font-semibold tracking-tight"><?= htmlspecialchars((string) $asset['asset_tag'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1 class="mt-3 text-lg font-medium"><?= htmlspecialchars((string) $asset['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>

            <div class="space-y-5 px-5 py-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-sm font-medium text-zinc-800">
                            <?= htmlspecialchars($typeLabel !== '' ? $typeLabel : __('unknown_category'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_status'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm text-zinc-800">
                        <?php if ($assignedTo !== ''): ?>
                            <?= htmlspecialchars($assignedTo, ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            <span class="text-zinc-400"><?= htmlspecialchars(__('not_assigned'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($attributeRows !== []): ?>
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('technical_specifications'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <dl class="mt-3 divide-y divide-zinc-100 rounded-xl border border-zinc-200">
                        <?php foreach ($attributeRows as $row): ?>
                        <div class="grid grid-cols-2 gap-3 px-4 py-3">
                            <dt class="text-sm text-zinc-500"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></dt>
                            <dd class="text-sm font-medium text-zinc-900"><?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>
            </div>
        </article>

        <p class="mt-6 text-center text-xs text-zinc-400"><?= htmlspecialchars(__('asset_view_scan_hint'), ENT_QUOTES, 'UTF-8') ?></p>
    </main>
</div>
