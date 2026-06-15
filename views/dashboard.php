<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $environment
 * @var string $locale
 * @var list<array<string, mixed>> $assets
 * @var array<string, mixed> $analytics
 * @var string $analyticsJson
 * @var list<array<string, mixed>> $categories
 * @var string $categoryFieldsJson
 * @var string $assetQrCodesJson
 * @var string $settingsJson
 * @var string $globalCustomFieldsJson
 * @var string $personnelJson
 */

$userRole = $userRole ?? 'end_user';
$canManageAssets = $canManageAssets ?? false;
$canAccessSettings = $canAccessSettings ?? false;
$canAccessPersonnel = $canAccessPersonnel ?? false;
$isEndUser = $isEndUser ?? false;
$isSuperAdmin = $isSuperAdmin ?? false;

$statusStyles = [
    'ready' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'deployed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'storage' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'broken' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
];

$translateStatus = static function (string $status): string {
    $key = 'status_' . $status;

    return __($key);
};

$formatPropertyValue = static function (mixed $value): string {
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
};

$statusChartColors = [
    'ready' => 'bg-sky-500',
    'deployed' => 'bg-emerald-500',
    'storage' => 'bg-amber-500',
    'broken' => 'bg-rose-500',
];

$categoryChartColors = [
    'bg-zinc-800',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-amber-500',
    'bg-violet-500',
    'bg-rose-500',
];

$summaryCards = [
    ['label' => __('metric_total_assets'), 'value' => $analytics['summary_cards']['total'], 'hint' => __('metric_total_hint')],
    ['label' => __('metric_deployed'), 'value' => $analytics['summary_cards']['deployed'], 'hint' => __('metric_deployed_hint')],
    ['label' => __('metric_in_storage'), 'value' => $analytics['summary_cards']['in_storage'], 'hint' => __('metric_in_storage_hint')],
    ['label' => __('metric_broken'), 'value' => $analytics['summary_cards']['broken'], 'hint' => __('metric_broken_hint')],
];

$assignedPercentage = (float) ($analytics['assignment']['assigned_percentage'] ?? 0);
$assignmentGradient = sprintf(
    'conic-gradient(#18181b 0%% %.1f%%, #e4e4e7 %.1f%% 100%%)',
    $assignedPercentage,
    $assignedPercentage
);

$i18nScript = json_encode([
    'create_error' => __('create_error'),
    'update_error' => __('update_error'),
    'network_error' => __('network_error'),
    'locale' => $locale ?? 'tr',
    'no_category_fields' => __('no_category_fields'),
    'no_users_found' => __('no_users_found'),
    'search_users_placeholder' => __('search_users_placeholder'),
    'history_empty' => __('history_empty'),
    'history_loading' => __('history_loading'),
    'history_error' => __('history_error'),
    'history_action_created' => __('history_action_created'),
    'history_action_assigned' => __('history_action_assigned'),
    'history_action_unassigned' => __('history_action_unassigned'),
    'history_action_status_change' => __('history_action_status_change'),
    'history_action_updated' => __('history_action_updated'),
    'settings_save_success' => __('settings_save_success'),
    'settings_save_error' => __('settings_save_error'),
    'settings_network_error' => __('settings_network_error'),
    'settings_auth_local' => __('settings_auth_local'),
    'settings_auth_local_hint' => __('settings_auth_local_hint'),
    'settings_auth_ldap' => __('settings_auth_ldap'),
    'settings_auth_ldap_hint' => __('settings_auth_ldap_hint'),
    'settings_auth_google' => __('settings_auth_google'),
    'settings_auth_google_hint' => __('settings_auth_google_hint'),
    'settings_auth_azure' => __('settings_auth_azure'),
    'settings_auth_azure_hint' => __('settings_auth_azure_hint'),
    'history_action_offboarded' => __('history_action_offboarded'),
    'offboard_confirm' => __('offboard_confirm'),
    'offboard_success' => __('offboard_success'),
    'offboard_error' => __('offboard_error'),
    'offboard_network_error' => __('offboard_network_error'),
    'personnel_status_active' => __('personnel_status_active'),
    'personnel_status_offboarded' => __('personnel_status_offboarded'),
    'delete_confirm' => __('delete_confirm'),
    'delete_success' => __('delete_success'),
    'delete_error' => __('delete_error'),
    'delete_network_error' => __('delete_network_error'),
    'return_confirm' => __('return_confirm'),
    'return_success' => __('return_success'),
    'return_error' => __('return_error'),
    'return_network_error' => __('return_network_error'),
    'transfer_success' => __('transfer_success'),
    'transfer_error' => __('transfer_error'),
    'transfer_network_error' => __('transfer_network_error'),
    'transfer_select_user' => __('transfer_select_user'),
    'history_action_returned' => __('history_action_returned'),
    'history_action_transferred' => __('history_action_transferred'),
    'categories_fetch_error' => __('categories_fetch_error'),
    'categories_network_error' => __('categories_network_error'),
    'category_create_success' => __('category_create_success'),
    'category_update_success' => __('category_update_success'),
    'category_delete_success' => __('category_delete_success'),
    'category_create_error' => __('category_create_error'),
    'category_update_error' => __('category_update_error'),
    'category_delete_error' => __('category_delete_error'),
    'category_delete_confirm' => __('category_delete_confirm'),
    'category_delete_in_use' => __('category_delete_in_use'),
    'category_field_count' => __('category_field_count'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
?>
<div class="min-h-full" x-data="assetDashboard()" x-init="if (canManageAssets) { fetchCategories(); }">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 border-r border-zinc-200 bg-white lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white">B</div>
                <div>
                    <p class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-zinc-500"><?= htmlspecialchars(__('app_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 p-4">
                <button
                    type="button"
                    @click="activeView = 'assets'"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition"
                    :class="activeView === 'assets' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50'"
                >
                    <span class="h-2 w-2 rounded-full" :class="activeView === 'assets' ? 'bg-zinc-900' : 'bg-zinc-300'"></span>
                    <?= htmlspecialchars($isEndUser ? __('page_title_end_user') : __('nav_assets'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php if ($canManageAssets): ?>
                <button
                    type="button"
                    @click="activeView = 'categories'; fetchCategories()"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition"
                    :class="activeView === 'categories' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50'"
                >
                    <span class="h-2 w-2 rounded-full" :class="activeView === 'categories' ? 'bg-zinc-900' : 'bg-zinc-300'"></span>
                    <?= htmlspecialchars(__('nav_categories'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
                <?php if ($canAccessPersonnel): ?>
                <button
                    type="button"
                    @click="activeView = 'personnel'"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition"
                    :class="activeView === 'personnel' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50'"
                >
                    <span class="h-2 w-2 rounded-full" :class="activeView === 'personnel' ? 'bg-zinc-900' : 'bg-zinc-300'"></span>
                    <?= htmlspecialchars(__('nav_personnel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
                <?php if ($canAccessSettings): ?>
                <button
                    type="button"
                    @click="activeView = 'settings'; $nextTick(() => initQuillEditor())"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition"
                    :class="activeView === 'settings' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50'"
                >
                    <span class="h-2 w-2 rounded-full" :class="activeView === 'settings' ? 'bg-zinc-900' : 'bg-zinc-300'"></span>
                    <?= htmlspecialchars(__('nav_settings'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
            </nav>

            <div class="border-t border-zinc-200 p-4 space-y-3">
                <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('environment'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-sm font-medium text-zinc-700"><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></p>
                <a href="/logout" class="inline-flex items-center text-sm font-medium text-zinc-600 transition hover:text-zinc-900">
                    <?= htmlspecialchars(__('nav_logout'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </aside>

        <main class="flex-1">
            <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <h1
                            class="text-2xl font-semibold tracking-tight text-zinc-900"
                            x-text="resolvePageTitle()"
                        ></h1>
                        <p
                            class="mt-1 text-sm text-zinc-500"
                            x-text="resolvePageSubtitle()"
                        ></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center rounded-xl border border-zinc-200 bg-white p-1 shadow-soft">
                            <span class="sr-only"><?= htmlspecialchars(__('language'), ENT_QUOTES, 'UTF-8') ?></span>
                            <a
                                href="<?= htmlspecialchars(lang_url('tr'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= ($locale ?? 'tr') === 'tr' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100' ?> rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            >TR</a>
                            <a
                                href="<?= htmlspecialchars(lang_url('en'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= ($locale ?? 'tr') === 'en' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100' ?> rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            >EN</a>
                        </div>
                        <button
                            type="button"
                            x-show="activeView === 'assets' && canManageAssets"
                            @click="openAddModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'categories' && canManageAssets"
                            @click="openCategoryModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_category'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-6 py-8">
                <div x-show="activeView === 'assets'" x-cloak class="space-y-8">
                <?php if (!$isEndUser): ?>
                <section class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('analytics_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('analytics_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <?php foreach ($summaryCards as $card): ?>
                        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft">
                            <p class="text-sm font-medium text-zinc-500"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900"><?= (int) $card['value'] ?></p>
                            <p class="mt-2 text-xs text-zinc-400"><?= htmlspecialchars($card['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft">
                            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('analytics_status_distribution'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="mt-5 space-y-4">
                                <?php foreach ($analytics['by_status'] as $row):
                                    $statusKey = (string) $row['status'];
                                    $barColor = $statusChartColors[$statusKey] ?? 'bg-zinc-500';
                                    $barWidth = max(0, min(100, (float) $row['percentage']));
                                ?>
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium text-zinc-700"><?= htmlspecialchars($translateStatus($statusKey), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="tabular-nums text-zinc-500"><?= (int) $row['count'] ?> · <?= number_format((float) $row['percentage'], 1) ?>%</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                                        <div class="h-2 rounded-full transition-all duration-500 <?= $barColor ?>" style="width: <?= $barWidth ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </article>

                        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft">
                            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('analytics_category_distribution'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <?php if ($analytics['by_category'] === []): ?>
                                <p class="mt-5 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
                                    <?= htmlspecialchars(__('analytics_no_category_data'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php else: ?>
                                <div class="mt-5 space-y-4">
                                    <?php foreach ($analytics['by_category'] as $index => $row):
                                        $barColor = $categoryChartColors[$index % count($categoryChartColors)];
                                        $barWidth = max(0, min(100, (float) $row['percentage']));
                                    ?>
                                    <div>
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="truncate font-medium text-zinc-700"><?= htmlspecialchars((string) $row['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="shrink-0 tabular-nums text-zinc-500"><?= (int) $row['count'] ?> · <?= number_format((float) $row['percentage'], 1) ?>%</span>
                                        </div>
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                                            <div class="h-2 rounded-full transition-all duration-500 <?= $barColor ?>" style="width: <?= $barWidth ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>

                        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft">
                            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('analytics_assignment_overview'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="mt-5 flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-between">
                                <div class="relative flex h-36 w-36 shrink-0 items-center justify-center rounded-full" style="background: <?= htmlspecialchars($assignmentGradient, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white shadow-soft">
                                        <span class="text-2xl font-semibold tabular-nums text-zinc-900"><?= number_format($assignedPercentage, 1) ?>%</span>
                                        <span class="mt-1 text-[10px] uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('analytics_assigned'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                                <div class="w-full space-y-3 sm:flex-1">
                                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-900"></span>
                                            <span class="text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('analytics_assigned'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <span class="text-sm tabular-nums text-zinc-600">
                                            <?= (int) $analytics['assignment']['assigned'] ?>
                                            (<?= number_format((float) $analytics['assignment']['assigned_percentage'], 1) ?>%)
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-300"></span>
                                            <span class="text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('analytics_unassigned'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <span class="text-sm tabular-nums text-zinc-600">
                                            <?= (int) $analytics['assignment']['unassigned'] ?>
                                            (<?= number_format((float) $analytics['assignment']['unassigned_percentage'], 1) ?>%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
                <?php endif; ?>

                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
                    <div class="border-b border-zinc-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars($isEndUser ? __('inventory_title_end_user') : __('inventory_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars($isEndUser ? __('inventory_subtitle_end_user') : __('inventory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200">
                            <thead class="bg-zinc-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_properties'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 bg-white">
                                <?php if ($assets === []): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-zinc-500">
                                        <?php if ($isEndUser): ?>
                                            <?= htmlspecialchars(__('empty_assets_end_user'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                        <?= htmlspecialchars(__('empty_assets_prefix'), ENT_QUOTES, 'UTF-8') ?>
                                        <span class="font-medium text-zinc-700"><?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?= htmlspecialchars(__('empty_assets_suffix'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($assets as $asset):
                                        $status = (string) ($asset['status'] ?? 'ready');
                                        $statusClass = $statusStyles[$status] ?? 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
                                        $properties = is_array($asset['properties'] ?? null) ? $asset['properties'] : [];
                                    ?>
                                    <tr class="hover:bg-zinc-50/80">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900">
                                            <?= htmlspecialchars((string) $asset['asset_tag'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-700">
                                            <?= htmlspecialchars((string) $asset['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-600">
                                            <?= htmlspecialchars((string) ($asset['category_name'] ?? __('unknown_category')), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset <?= $statusClass ?>">
                                                <?= htmlspecialchars($translateStatus($status), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-600">
                                            <?php if (!empty($asset['user_name'])): ?>
                                                <?= htmlspecialchars((string) $asset['user_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-zinc-400"><?= htmlspecialchars(__('not_assigned'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex max-w-xl flex-wrap gap-2">
                                                <?php if ($properties === []): ?>
                                                    <span class="text-xs text-zinc-400"><?= htmlspecialchars(__('no_properties'), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <?php foreach ($properties as $key => $value): ?>
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-xs text-zinc-700">
                                                        <span class="font-medium"><?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>:</span>
                                                        <span><?= htmlspecialchars($formatPropertyValue($value), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    @click='openDetailModal(<?= json_encode([
                                                        'id' => (int) $asset['id'],
                                                        'asset_tag' => (string) $asset['asset_tag'],
                                                        'name' => (string) $asset['name'],
                                                        'status' => $status,
                                                        'category_name' => (string) ($asset['category_name'] ?? __('unknown_category')),
                                                        'user_id' => $asset['user_id'] ?? null,
                                                        'user_name' => $asset['user_name'] ?? null,
                                                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>)'
                                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                                >
                                                    <?= htmlspecialchars(__('action_view_history'), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <?php if ($canManageAssets): ?>
                                                <button
                                                    type="button"
                                                    @click='openEditModal(<?= json_encode([
                                                        'id' => (int) $asset['id'],
                                                        'asset_tag' => (string) $asset['asset_tag'],
                                                        'name' => (string) $asset['name'],
                                                        'status' => $status,
                                                        'user_id' => $asset['user_id'] ?? null,
                                                        'user_name' => $asset['user_name'] ?? null,
                                                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>)'
                                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                                >
                                                    <?= htmlspecialchars(__('action_assign'), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (!empty($asset['user_id'])): ?>
                                                <button
                                                    type="button"
                                                    @click="printTutanak(<?= (int) $asset['id'] ?>)"
                                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                                >
                                                    <?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <?php if ($canManageAssets): ?>
                                                <button
                                                    type="button"
                                                    @click="returnAssetToStorage(<?= (int) $asset['id'] ?>)"
                                                    class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-50"
                                                >
                                                    <?= htmlspecialchars(__('action_return_to_storage'), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click='openTransferModal(<?= json_encode([
                                                        'id' => (int) $asset['id'],
                                                        'asset_tag' => (string) $asset['asset_tag'],
                                                        'name' => (string) $asset['name'],
                                                        'user_id' => $asset['user_id'] ?? null,
                                                        'user_name' => $asset['user_name'] ?? null,
                                                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>)'
                                                    class="rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-medium text-indigo-800 transition hover:bg-indigo-50"
                                                >
                                                    <?= htmlspecialchars(__('action_transfer'), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($isSuperAdmin): ?>
                                                <button
                                                    type="button"
                                                    @click="deleteAsset(<?= (int) $asset['id'] ?>)"
                                                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                                >
                                                    <?= htmlspecialchars(__('action_delete_asset'), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                </div>

                <?php if ($canManageAssets): ?>
                <?php require __DIR__ . '/partials/categories_panel.php'; ?>
                <?php endif; ?>
                <?php if ($canAccessSettings): ?>
                <?php require __DIR__ . '/partials/settings_panel.php'; ?>
                <?php endif; ?>
                <?php if ($canAccessPersonnel): ?>
                <?php require __DIR__ . '/partials/personnel_panel.php'; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div
        x-show="isAddOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeAddModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeAddModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_add_asset'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeAddModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitAddForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_asset_tag'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.asset_tag" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_serial_number'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.serial_number" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_name'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.name" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_category'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select
                            x-model="form.category_id"
                            @change="loadCategoryFields(form.category_id)"
                            required
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                            <option value=""><?= htmlspecialchars(__('select_category'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>">
                                <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="form.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require __DIR__ . '/partials/user_picker.php'; ?>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('technical_specifications'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('technical_specifications_hint'), ENT_QUOTES, 'UTF-8') ?></p>

                    <p
                        x-show="dynamicFields.length === 0"
                        x-cloak
                        class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"
                    >
                        <?= htmlspecialchars(__('no_category_fields'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div x-show="dynamicFields.length > 0" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                        <template x-for="field in dynamicFields" :key="field.name">
                            <label class="block" :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
                                <span class="mb-1.5 block text-sm font-medium text-zinc-700" x-text="resolveFieldLabel(field)"></span>
                                <input
                                    x-show="field.type !== 'textarea'"
                                    :type="field.type === 'number' ? 'number' : 'text'"
                                    x-model="dynamicValues[field.name]"
                                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                                >
                                <textarea
                                    x-show="field.type === 'textarea'"
                                    x-model="dynamicValues[field.name]"
                                    rows="3"
                                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                                ></textarea>
                            </label>
                        </template>
                    </div>
                </div>

                <div x-show="addErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="addErrorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeAddModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isSubmitting"><?= htmlspecialchars(__('create_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isEditOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeEditModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeEditModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_edit_asset'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_edit_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeEditModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitEditForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="editAsset?.asset_tag"></p>
                    <p class="mt-3 text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm text-zinc-700" x-text="editAsset?.name"></p>
                </div>

                <label class="mt-5 block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="editForm.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require __DIR__ . '/partials/user_picker.php'; ?>
                </div>

                <div x-show="editErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="editErrorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeEditModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isSubmitting"><?= htmlspecialchars(__('save_changes'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isDetailOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeDetailModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeDetailModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_asset_detail'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_asset_detail_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeDetailModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="detailAsset?.asset_tag"></p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-sm text-zinc-700" x-text="detailAsset?.name"></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-sm text-zinc-700" x-text="detailAsset?.category_name"></p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('qr_code_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('qr_code_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <button
                            type="button"
                            @click="printAssetLabel()"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-100"
                        >
                            <?= htmlspecialchars(__('print_label'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="mt-4 flex justify-center rounded-xl border border-dashed border-zinc-200 bg-white p-4">
                        <div class="h-36 w-36" x-html="detailQrSvg"></div>
                    </div>
                </div>

                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('history_title'), ENT_QUOTES, 'UTF-8') ?></h4>

                    <p x-show="historyLoading" x-cloak class="mt-4 text-sm text-zinc-500" x-text="window.__i18n.history_loading"></p>
                    <p x-show="historyError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="historyError"></p>
                    <p x-show="!historyLoading && !historyError && assetHistory.length === 0" x-cloak class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500" x-text="window.__i18n.history_empty"></p>

                    <div x-show="!historyLoading && assetHistory.length > 0" x-cloak class="mt-4 space-y-4">
                        <template x-for="entry in assetHistory" :key="entry.id">
                            <article class="relative border-l-2 border-zinc-200 pl-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700" x-text="resolveHistoryAction(entry.action)"></span>
                                    <time class="text-xs text-zinc-400" x-text="formatHistoryDate(entry.created_at)"></time>
                                </div>
                                <p class="mt-2 text-sm text-zinc-700" x-text="entry.notes"></p>
                                <p x-show="entry.target_user_name" class="mt-1 text-xs text-zinc-500">
                                    <span><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span>
                                    <span x-text="entry.target_user_name"></span>
                                </p>
                            </article>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="canManageAssets && detailAsset?.user_id" x-cloak class="flex flex-wrap gap-2 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="returnAssetToStorage(detailAsset.id)"
                    class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-50"
                >
                    <?= htmlspecialchars(__('action_return_to_storage'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="openTransferModal(detailAsset)"
                    class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-2 text-xs font-medium text-indigo-800 transition hover:bg-indigo-50"
                >
                    <?= htmlspecialchars(__('action_transfer'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isTransferOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeTransferModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTransferModal()"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('transfer_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('transfer_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeTransferModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="transferAsset?.asset_tag"></p>
                    <p class="mt-2 text-xs text-zinc-500">
                        <span><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span>
                        <span x-text="transferAsset?.user_name"></span>
                    </p>
                </div>

                <p class="mt-4 text-sm text-zinc-600"><?= htmlspecialchars(__('transfer_select_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>

                <div class="relative mt-3">
                    <div x-show="transferSelectedUser" x-cloak class="mb-3 flex items-center justify-between rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-indigo-900" x-text="transferSelectedUser?.name"></p>
                            <p class="text-xs text-indigo-700" x-text="transferSelectedUser?.email"></p>
                        </div>
                        <button type="button" @click="clearTransferUser()" class="text-xs font-medium text-indigo-800 hover:underline">
                            <?= htmlspecialchars(__('unassign_user'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    <input
                        type="text"
                        x-model="transferUserSearchQuery"
                        @input.debounce.300ms="searchTransferUsers()"
                        @focus="showTransferUserResults = true"
                        :placeholder="window.__i18n.search_users_placeholder"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                    >
                    <div
                        x-show="showTransferUserResults && (transferUserSearchResults.length > 0 || (transferUserSearchQuery !== '' && !transferUserSearchLoading))"
                        x-cloak
                        @click.outside="showTransferUserResults = false"
                        class="absolute z-10 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-soft"
                    >
                        <template x-for="user in transferUserSearchResults" :key="user.id">
                            <button
                                type="button"
                                @click="selectTransferUser(user)"
                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50"
                            >
                                <span class="text-sm font-medium text-zinc-900" x-text="user.name"></span>
                                <span class="text-xs text-zinc-500" x-text="user.email"></span>
                                <span class="text-xs text-zinc-400" x-text="user.department || ''"></span>
                            </button>
                        </template>
                        <p
                            x-show="transferUserSearchResults.length === 0 && transferUserSearchQuery !== '' && !transferUserSearchLoading"
                            class="px-4 py-3 text-sm text-zinc-500"
                            x-text="window.__i18n.no_users_found"
                        ></p>
                    </div>
                </div>

                <p x-show="transferErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="transferErrorMessage"></p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="closeTransferModal()"
                    :disabled="isTransferSubmitting"
                    class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="submitTransfer()"
                    :disabled="isTransferSubmitting"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isTransferSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isTransferSubmitting"><?= htmlspecialchars(__('transfer_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isCategoryModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeCategoryModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeCategoryModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900" x-text="categoryForm.id ? '<?= htmlspecialchars(__('edit_category'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('add_category'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_category_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeCategoryModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitCategoryForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('category_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input
                        type="text"
                        x-model="categoryForm.name"
                        required
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                    >
                </label>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('category_fields_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('category_fields_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <button
                            type="button"
                            @click="addCategoryField()"
                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                        >
                            <span class="text-base leading-none">+</span>
                            <?= htmlspecialchars(__('category_add_field'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>

                    <p
                        x-show="categoryForm.fields.length === 0"
                        x-cloak
                        class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"
                    >
                        <?= htmlspecialchars(__('category_no_fields'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div x-show="categoryForm.fields.length > 0" x-cloak class="mt-4 space-y-3">
                        <template x-for="(field, index) in categoryForm.fields" :key="index">
                            <div class="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:grid-cols-12">
                                <label class="block sm:col-span-3">
                                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_name'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <input
                                        type="text"
                                        x-model="field.name"
                                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                        placeholder="ram"
                                    >
                                </label>
                                <label class="block sm:col-span-4">
                                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_label'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <input
                                        type="text"
                                        x-model="field.label"
                                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                        placeholder="<?= htmlspecialchars(__('settings_field_label_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </label>
                                <label class="block sm:col-span-3">
                                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_type'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <select
                                        x-model="field.type"
                                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                    >
                                        <option value="text"><?= htmlspecialchars(__('settings_field_type_text'), ENT_QUOTES, 'UTF-8') ?></option>
                                        <option value="number"><?= htmlspecialchars(__('settings_field_type_number'), ENT_QUOTES, 'UTF-8') ?></option>
                                        <option value="textarea"><?= htmlspecialchars(__('settings_field_type_textarea'), ENT_QUOTES, 'UTF-8') ?></option>
                                    </select>
                                </label>
                                <div class="flex items-end justify-end sm:col-span-2">
                                    <button
                                        type="button"
                                        @click="removeCategoryField(index)"
                                        class="rounded-lg px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
                                    >
                                        <?= htmlspecialchars(__('category_remove_field'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <p x-show="categoryFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="categoryFormError"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button
                        type="button"
                        @click="closeCategoryModal()"
                        :disabled="isCategorySubmitting"
                        class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="submit"
                        :disabled="isCategorySubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isCategorySubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isCategorySubmitting"><?= htmlspecialchars(__('category_save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    #zimmet-quill-wrapper .ql-toolbar.ql-snow {
        border: 0;
        border-bottom: 1px solid #e4e4e7;
        background: #fafafa;
    }

    #zimmet-quill-wrapper .ql-container.ql-snow {
        border: 0;
        font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    }

    #zimmet-quill-wrapper .ql-editor {
        min-height: 240px;
        line-height: 1.6;
    }
</style>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

<script>
    window.__i18n = <?= $i18nScript ?>;
    window.__categoryFields = <?= $categoryFieldsJson ?>;
    window.__assetQrCodes = <?= $assetQrCodesJson ?>;
    window.__analytics = <?= $analyticsJson ?>;
    window.__settings = <?= $settingsJson ?>;
    window.__globalCustomFields = <?= $globalCustomFieldsJson ?>;
    window.__personnel = <?= $personnelJson ?? '[]' ?>;

    function assetDashboard() {
        return {
            activeView: 'assets',
            userRole: <?= json_encode($userRole, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            canManageAssets: <?= $canManageAssets ? 'true' : 'false' ?>,
            canAccessSettings: <?= $canAccessSettings ? 'true' : 'false' ?>,
            canAccessPersonnel: <?= $canAccessPersonnel ? 'true' : 'false' ?>,
            isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>,
            pageTitles: {
                assets: <?= json_encode($isEndUser ? __('page_title_end_user') : $pageTitle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                categories: <?= json_encode(__('categories_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                settings: <?= json_encode(__('settings_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                personnel: <?= json_encode(__('personnel_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            },
            pageSubtitles: {
                assets: <?= json_encode($isEndUser ? __('page_subtitle_end_user') : __('page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                categories: <?= json_encode(__('categories_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                settings: <?= json_encode(__('settings_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                personnel: <?= json_encode(__('personnel_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            },
            isAddOpen: false,
            isEditOpen: false,
            isDetailOpen: false,
            isTransferOpen: false,
            isTransferSubmitting: false,
            transferAsset: null,
            transferSelectedUser: null,
            transferUserSearchQuery: '',
            transferUserSearchResults: [],
            transferUserSearchLoading: false,
            showTransferUserResults: false,
            transferErrorMessage: '',
            categories: [],
            categoriesLoading: false,
            categoriesError: '',
            categoriesSuccessMessage: '',
            isCategoryModalOpen: false,
            isCategorySubmitting: false,
            categoryForm: {
                id: null,
                name: '',
                fields: [],
            },
            categoryFormError: '',
            isSubmitting: false,
            addErrorMessage: '',
            editErrorMessage: '',
            detailAsset: null,
            detailQrSvg: '',
            assetHistory: [],
            historyLoading: false,
            historyError: '',
            categoryFields: window.__categoryFields || {},
            dynamicFields: [],
            dynamicValues: {},
            userSearchQuery: '',
            userSearchResults: [],
            userSearchLoading: false,
            showUserResults: false,
            selectedUser: null,
            editAsset: null,
            editForm: {
                status: 'ready',
            },
            form: {
                asset_tag: '',
                serial_number: '',
                name: '',
                category_id: '',
                status: 'ready',
            },
            settingsForm: {
                active_auth_driver: window.__settings?.active_auth_driver || 'local',
                zimmet_template: window.__settings?.zimmet_template || '',
                custom_fields: Array.isArray(window.__settings?.custom_fields)
                    ? JSON.parse(JSON.stringify(window.__settings.custom_fields))
                    : [],
                ldap_config: {
                    host: window.__settings?.ldap_config?.host || '',
                    port: window.__settings?.ldap_config?.port || '389',
                    base_dn: window.__settings?.ldap_config?.base_dn || '',
                    bind_dn: window.__settings?.ldap_config?.bind_dn || '',
                    bind_password: '',
                    bind_password_configured: Boolean(window.__settings?.ldap_config?.bind_password_configured),
                    use_tls: Boolean(window.__settings?.ldap_config?.use_tls),
                },
                google_config: {
                    domain: window.__settings?.google_config?.domain || '',
                    admin_email: window.__settings?.google_config?.admin_email || '',
                    auth_mode: window.__settings?.google_config?.auth_mode || 'service_account',
                    service_account_json: '',
                    service_account_configured: Boolean(window.__settings?.google_config?.service_account_configured),
                    oauth_token_json: '',
                    oauth_token_configured: Boolean(window.__settings?.google_config?.oauth_token_configured),
                },
                login_config: {
                    providers: {
                        local: Boolean(window.__settings?.login_config?.providers?.local ?? true),
                        ldap: Boolean(window.__settings?.login_config?.providers?.ldap),
                        google: Boolean(window.__settings?.login_config?.providers?.google),
                        microsoft: Boolean(window.__settings?.login_config?.providers?.microsoft),
                    },
                    google_sso: {
                        client_id: window.__settings?.login_config?.google_sso?.client_id || '',
                        client_secret: '',
                        client_secret_configured: Boolean(window.__settings?.login_config?.google_sso?.client_secret_configured),
                    },
                    microsoft_sso: {
                        tenant_id: window.__settings?.login_config?.microsoft_sso?.tenant_id || '',
                        client_id: window.__settings?.login_config?.microsoft_sso?.client_id || '',
                        client_secret: '',
                        client_secret_configured: Boolean(window.__settings?.login_config?.microsoft_sso?.client_secret_configured),
                    },
                },
            },
            authDrivers: [
                {
                    id: 'local',
                    label: window.__i18n.settings_auth_local,
                    description: window.__i18n.settings_auth_local_hint,
                },
                {
                    id: 'ldap',
                    label: window.__i18n.settings_auth_ldap,
                    description: window.__i18n.settings_auth_ldap_hint,
                },
                {
                    id: 'google',
                    label: window.__i18n.settings_auth_google,
                    description: window.__i18n.settings_auth_google_hint,
                },
                {
                    id: 'azure',
                    label: window.__i18n.settings_auth_azure,
                    description: window.__i18n.settings_auth_azure_hint,
                },
            ],
            isSavingSettings: false,
            settingsErrorMessage: '',
            settingsSuccessMessage: '',
            quillEditor: null,
            globalCustomFields: Array.isArray(window.__globalCustomFields) ? window.__globalCustomFields : [],
            personnel: Array.isArray(window.__personnel) ? window.__personnel : [],
            isOffboarding: false,
            offboardSuccessMessage: '',
            offboardErrorMessage: '',
            resolvePageTitle() {
                return this.pageTitles[this.activeView] || this.pageTitles.assets;
            },
            resolvePageSubtitle() {
                return this.pageSubtitles[this.activeView] || this.pageSubtitles.assets;
            },
            resolvePersonnelStatus(status) {
                return status === 'offboarded'
                    ? window.__i18n.personnel_status_offboarded
                    : window.__i18n.personnel_status_active;
            },
            printTutanak(assetId) {
                window.open(`/api/assets/${assetId}/tutanak`, '_blank', 'noopener,noreferrer');
            },
            async deleteAsset(assetId) {
                if (!assetId || !window.confirm(window.__i18n.delete_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/assets/${assetId}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        window.alert(result.message || window.__i18n.delete_error);
                        return;
                    }

                    window.alert(result.message || window.__i18n.delete_success);
                    window.location.reload();
                } catch (error) {
                    window.alert(window.__i18n.delete_network_error);
                }
            },
            async returnAssetToStorage(assetId) {
                if (!assetId || !window.confirm(window.__i18n.return_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/assets/${assetId}/return`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        window.alert(result.message || window.__i18n.return_error);
                        return;
                    }

                    window.alert(result.message || window.__i18n.return_success);
                    window.location.reload();
                } catch (error) {
                    window.alert(window.__i18n.return_network_error);
                }
            },
            openTransferModal(asset) {
                this.transferAsset = asset;
                this.transferErrorMessage = '';
                this.resetTransferUserSearch();
                this.isTransferOpen = true;
            },
            closeTransferModal() {
                if (this.isTransferSubmitting) {
                    return;
                }

                this.isTransferOpen = false;
                this.transferAsset = null;
                this.resetTransferUserSearch();
                this.transferErrorMessage = '';
            },
            resetTransferUserSearch() {
                this.transferUserSearchQuery = '';
                this.transferUserSearchResults = [];
                this.transferUserSearchLoading = false;
                this.showTransferUserResults = false;
                this.transferSelectedUser = null;
            },
            async searchTransferUsers() {
                this.transferUserSearchLoading = true;

                try {
                    const query = encodeURIComponent(this.transferUserSearchQuery.trim());
                    const response = await fetch(`/api/users/search?q=${query}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.transferUserSearchResults = [];
                        return;
                    }

                    this.transferUserSearchResults = Array.isArray(result.data) ? result.data : [];
                    this.showTransferUserResults = true;
                } catch (error) {
                    this.transferUserSearchResults = [];
                } finally {
                    this.transferUserSearchLoading = false;
                }
            },
            selectTransferUser(user) {
                this.transferSelectedUser = user;
                this.transferUserSearchQuery = '';
                this.transferUserSearchResults = [];
                this.showTransferUserResults = false;
                this.transferErrorMessage = '';
            },
            clearTransferUser() {
                this.transferSelectedUser = null;
            },
            async submitTransfer() {
                if (!this.transferAsset?.id) {
                    this.transferErrorMessage = window.__i18n.transfer_error;
                    return;
                }

                if (!this.transferSelectedUser?.id) {
                    this.transferErrorMessage = window.__i18n.transfer_select_user;
                    return;
                }

                this.isTransferSubmitting = true;
                this.transferErrorMessage = '';

                try {
                    const response = await fetch(`/api/assets/${this.transferAsset.id}/transfer`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            user_id: Number(this.transferSelectedUser.id),
                        }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.transferErrorMessage = result.message || window.__i18n.transfer_error;
                        return;
                    }

                    window.alert(result.message || window.__i18n.transfer_success);
                    window.location.reload();
                } catch (error) {
                    this.transferErrorMessage = window.__i18n.transfer_network_error;
                } finally {
                    this.isTransferSubmitting = false;
                }
            },
            async startOffboarding(person) {
                if (!person?.id || !window.confirm(window.__i18n.offboard_confirm)) {
                    return;
                }

                this.isOffboarding = true;
                this.offboardSuccessMessage = '';
                this.offboardErrorMessage = '';

                try {
                    const response = await fetch(`/api/users/${person.id}/offboard`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.offboardErrorMessage = result.message || window.__i18n.offboard_error;
                        return;
                    }

                    this.offboardSuccessMessage = result.message || window.__i18n.offboard_success;
                    person.status = 'offboarded';
                    person.assigned_asset_count = 0;
                    window.setTimeout(() => window.location.reload(), 1200);
                } catch (error) {
                    this.offboardErrorMessage = window.__i18n.offboard_network_error;
                } finally {
                    this.isOffboarding = false;
                }
            },
            openAddModal() {
                this.addErrorMessage = '';
                this.resetDynamicFields();
                this.resetUserSearch();
                this.loadCategoryFields(this.form.category_id);
                this.isAddOpen = true;
            },
            closeAddModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isAddOpen = false;
            },
            openEditModal(asset) {
                this.editErrorMessage = '';
                this.editAsset = asset;
                this.editForm.status = asset.status || 'ready';
                this.resetUserSearch();

                if (asset.user_id) {
                    this.selectedUser = {
                        id: String(asset.user_id),
                        name: asset.user_name || '',
                        email: '',
                        department: null,
                    };
                } else {
                    this.selectedUser = null;
                }

                this.isEditOpen = true;
            },
            closeEditModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isEditOpen = false;
                this.editAsset = null;
            },
            async openDetailModal(asset) {
                this.detailAsset = asset;
                this.detailQrSvg = window.__assetQrCodes?.[asset.id] || '';
                this.assetHistory = [];
                this.historyError = '';
                this.historyLoading = true;
                this.isDetailOpen = true;

                try {
                    const response = await fetch(`/api/assets/${asset.id}/history`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.historyError = result.message || window.__i18n.history_error;
                        return;
                    }

                    this.assetHistory = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.historyError = window.__i18n.history_error;
                } finally {
                    this.historyLoading = false;
                }
            },
            closeDetailModal() {
                this.isDetailOpen = false;
                this.detailAsset = null;
                this.detailQrSvg = '';
                this.assetHistory = [];
                this.historyError = '';
                this.historyLoading = false;
            },
            printAssetLabel() {
                if (!this.detailAsset) {
                    return;
                }

                const qrSvg = window.__assetQrCodes?.[this.detailAsset.id] || this.detailQrSvg || '';
                const assetTag = this.detailAsset.asset_tag || '';
                const categoryName = this.detailAsset.category_name || '';
                const printWindow = window.open('', '_blank', 'width=420,height=320');

                if (!printWindow) {
                    return;
                }

                printWindow.document.write(`<!DOCTYPE html>
<html lang="${window.__i18n.locale || 'tr'}">
<head>
    <meta charset="UTF-8">
    <title>${assetTag}</title>
    <style>
        @page { size: 62mm 29mm; margin: 2mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label {
            width: 58mm;
            min-height: 25mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 1.5mm;
        }
        .tag {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: 0.02em;
        }
        .category {
            font-size: 8pt;
            line-height: 1.2;
            color: #444;
        }
        .qr svg {
            display: block;
            width: 18mm;
            height: 18mm;
        }
        @media print {
            body { margin: 0; }
            .label { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="tag">${assetTag}</div>
        <div class="category">${categoryName}</div>
        <div class="qr">${qrSvg}</div>
    </div>
    <script>
        window.addEventListener('load', function () {
            window.focus();
            window.print();
        });
    <\/script>
</body>
</html>`);
                printWindow.document.close();
            },
            resolveHistoryAction(action) {
                const labels = {
                    created: window.__i18n.history_action_created,
                    assigned: window.__i18n.history_action_assigned,
                    unassigned: window.__i18n.history_action_unassigned,
                    status_change: window.__i18n.history_action_status_change,
                    updated: window.__i18n.history_action_updated,
                    offboarded: window.__i18n.history_action_offboarded,
                    returned: window.__i18n.history_action_returned,
                    transferred: window.__i18n.history_action_transferred,
                };

                return labels[action] || action;
            },
            formatHistoryDate(value) {
                if (!value) {
                    return '';
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString(window.__i18n.locale === 'en' ? 'en-US' : 'tr-TR');
            },
            resetDynamicFields() {
                this.dynamicFields = [];
                this.dynamicValues = {};
            },
            resetUserSearch() {
                this.userSearchQuery = '';
                this.userSearchResults = [];
                this.userSearchLoading = false;
                this.showUserResults = false;
                this.selectedUser = null;
            },
            async searchUsers() {
                this.userSearchLoading = true;

                try {
                    const query = encodeURIComponent(this.userSearchQuery.trim());
                    const response = await fetch(`/api/users/search?q=${query}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.userSearchResults = [];
                        return;
                    }

                    this.userSearchResults = Array.isArray(result.data) ? result.data : [];
                    this.showUserResults = true;
                } catch (error) {
                    this.userSearchResults = [];
                } finally {
                    this.userSearchLoading = false;
                }
            },
            selectUser(user) {
                this.selectedUser = user;
                this.userSearchQuery = '';
                this.userSearchResults = [];
                this.showUserResults = false;
            },
            clearSelectedUser() {
                this.selectedUser = null;
            },
            loadCategoryFields(categoryId) {
                const normalizedId = String(categoryId || '');
                this.resetDynamicFields();

                const globalFields = Array.isArray(this.globalCustomFields) ? this.globalCustomFields : [];
                const categorySpecificFields = normalizedId === ''
                    ? []
                    : (this.categoryFields[normalizedId] || this.categoryFields[Number(normalizedId)] || []);

                const mergedFields = [
                    ...(Array.isArray(categorySpecificFields) ? categorySpecificFields : []),
                    ...globalFields,
                ];

                const seen = new Set();
                this.dynamicFields = mergedFields.filter((field) => {
                    if (!field || !field.name || seen.has(field.name)) {
                        return false;
                    }

                    seen.add(field.name);
                    return true;
                });

                this.dynamicFields.forEach((field) => {
                    this.dynamicValues[field.name] = '';
                });
            },
            resolveFieldLabel(field) {
                if (window.__i18n.locale === 'en' && field.label_en) {
                    return field.label_en;
                }

                return field.label || field.name;
            },
            buildAddPayload() {
                const payload = {
                    asset_tag: this.form.asset_tag.trim(),
                    name: this.form.name.trim(),
                    category_id: Number(this.form.category_id),
                    status: this.form.status,
                };

                if (this.form.serial_number.trim() !== '') {
                    payload.serial_number = this.form.serial_number.trim();
                }

                if (this.selectedUser?.id) {
                    payload.user_id = Number(this.selectedUser.id);
                }

                this.dynamicFields.forEach((field) => {
                    if (!field || !field.name) {
                        return;
                    }

                    const rawValue = this.dynamicValues[field.name];

                    if (rawValue === undefined || rawValue === null || String(rawValue).trim() === '') {
                        return;
                    }

                    payload[field.name] = field.type === 'number'
                        ? Number(rawValue)
                        : String(rawValue).trim();
                });

                return payload;
            },
            buildEditPayload() {
                return {
                    status: this.editForm.status,
                    user_id: this.selectedUser?.id ? Number(this.selectedUser.id) : null,
                };
            },
            async submitAddForm() {
                this.isSubmitting = true;
                this.addErrorMessage = '';

                try {
                    const response = await fetch('/api/assets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildAddPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.addErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.addErrorMessage = result.message || window.__i18n.create_error;
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.addErrorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
            async submitEditForm() {
                if (!this.editAsset?.id) {
                    return;
                }

                this.isSubmitting = true;
                this.editErrorMessage = '';

                try {
                    const response = await fetch(`/api/assets/${this.editAsset.id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildEditPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.editErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.editErrorMessage = result.message || window.__i18n.update_error;
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.editErrorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
            addCustomField() {
                this.settingsForm.custom_fields.push({
                    name: '',
                    label: '',
                    type: 'text',
                });
            },
            removeCustomField(index) {
                this.settingsForm.custom_fields.splice(index, 1);
            },
            async fetchCategories() {
                if (!this.canManageAssets) {
                    return;
                }

                this.categoriesLoading = true;
                this.categoriesError = '';

                try {
                    const response = await fetch('/api/categories', {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.categoriesError = result.message || window.__i18n.categories_fetch_error;
                        this.categories = [];
                        return;
                    }

                    this.categories = Array.isArray(result.data) ? result.data : [];
                    this.syncCategoryFieldMap();
                } catch (error) {
                    this.categoriesError = window.__i18n.categories_network_error;
                    this.categories = [];
                } finally {
                    this.categoriesLoading = false;
                }
            },
            syncCategoryFieldMap() {
                const map = {};

                this.categories.forEach((category) => {
                    if (!category?.id) {
                        return;
                    }

                    map[String(category.id)] = Array.isArray(category.fields) ? category.fields : [];
                });

                this.categoryFields = map;
                window.__categoryFields = map;
            },
            resolveCategoryFieldCount(category) {
                const count = Array.isArray(category?.fields) ? category.fields.length : 0;

                return (window.__i18n.category_field_count || '%d alan').replace('%d', String(count));
            },
            openCategoryModal(category = null) {
                this.categoryFormError = '';
                this.categoriesSuccessMessage = '';

                if (category) {
                    this.categoryForm = {
                        id: category.id,
                        name: category.name || '',
                        fields: Array.isArray(category.fields)
                            ? JSON.parse(JSON.stringify(category.fields))
                            : [],
                    };
                } else {
                    this.categoryForm = {
                        id: null,
                        name: '',
                        fields: [],
                    };
                }

                this.isCategoryModalOpen = true;
            },
            closeCategoryModal() {
                if (this.isCategorySubmitting) {
                    return;
                }

                this.isCategoryModalOpen = false;
                this.categoryFormError = '';
            },
            addCategoryField() {
                this.categoryForm.fields.push({
                    name: '',
                    label: '',
                    type: 'text',
                });
            },
            removeCategoryField(index) {
                this.categoryForm.fields.splice(index, 1);
            },
            buildCategoryPayload() {
                return {
                    name: this.categoryForm.name.trim(),
                    fields: this.categoryForm.fields
                        .filter((field) => field && (field.name?.trim() || field.label?.trim()))
                        .map((field) => ({
                            name: String(field.name || '').trim(),
                            label: String(field.label || '').trim(),
                            type: field.type || 'text',
                        })),
                };
            },
            async submitCategoryForm() {
                this.isCategorySubmitting = true;
                this.categoryFormError = '';
                this.categoriesSuccessMessage = '';

                const payload = this.buildCategoryPayload();
                const isEdit = Boolean(this.categoryForm.id);
                const url = isEdit ? `/api/categories/${this.categoryForm.id}` : '/api/categories';
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.categoryFormError = result.message || (isEdit
                            ? window.__i18n.category_update_error
                            : window.__i18n.category_create_error);
                        return;
                    }

                    this.isCategoryModalOpen = false;
                    this.categoriesSuccessMessage = result.message || (isEdit
                        ? window.__i18n.category_update_success
                        : window.__i18n.category_create_success);
                    await this.fetchCategories();
                    window.setTimeout(() => window.location.reload(), 900);
                } catch (error) {
                    this.categoryFormError = window.__i18n.categories_network_error;
                } finally {
                    this.isCategorySubmitting = false;
                }
            },
            async deleteCategory(category) {
                if (!category?.id || !window.confirm(window.__i18n.category_delete_confirm)) {
                    return;
                }

                this.categoriesSuccessMessage = '';
                this.categoriesError = '';

                try {
                    const response = await fetch(`/api/categories/${category.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.categoriesError = result.message || window.__i18n.category_delete_error;
                        return;
                    }

                    this.categoriesSuccessMessage = result.message || window.__i18n.category_delete_success;
                    await this.fetchCategories();
                    window.setTimeout(() => window.location.reload(), 900);
                } catch (error) {
                    this.categoriesError = window.__i18n.categories_network_error;
                }
            },
            initQuillEditor() {
                if (typeof Quill === 'undefined') {
                    return;
                }

                const container = document.getElementById('zimmet-quill-editor');

                if (!container) {
                    return;
                }

                if (this.quillEditor) {
                    return;
                }

                this.quillEditor = new Quill(container, {
                    theme: 'snow',
                    placeholder: 'Zimmet formu metnini buraya yazın…',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            [{ align: [] }],
                            ['clean'],
                        ],
                    },
                });

                const template = this.settingsForm.zimmet_template || '';

                if (template.includes('<')) {
                    this.quillEditor.root.innerHTML = template;
                } else if (template !== '') {
                    this.quillEditor.setText(template);
                }
            },
            syncZimmetTemplate() {
                if (!this.quillEditor) {
                    return;
                }

                const html = this.quillEditor.root.innerHTML.trim();
                const isEmpty = html === '' || html === '<p><br></p>';
                this.settingsForm.zimmet_template = isEmpty ? '' : html;

                if (this.$refs.zimmetTemplateInput) {
                    this.$refs.zimmetTemplateInput.value = this.settingsForm.zimmet_template;
                }
            },
            async saveSettings() {
                this.syncZimmetTemplate();
                this.isSavingSettings = true;
                this.settingsErrorMessage = '';
                this.settingsSuccessMessage = '';

                try {
                    const response = await fetch('/api/settings', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            active_auth_driver: this.settingsForm.active_auth_driver,
                            zimmet_template: this.settingsForm.zimmet_template,
                            custom_fields: this.settingsForm.custom_fields,
                            ldap_config: this.settingsForm.ldap_config,
                            google_config: this.settingsForm.google_config,
                            login_config: this.settingsForm.login_config,
                        }),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.settingsErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.settingsErrorMessage = result.message || window.__i18n.settings_save_error;
                        }

                        return;
                    }

                    this.settingsSuccessMessage = window.__i18n.settings_save_success;
                    this.globalCustomFields = Array.isArray(result.data?.custom_fields)
                        ? result.data.custom_fields
                        : this.settingsForm.custom_fields;
                    window.__globalCustomFields = this.globalCustomFields;

                    if (result.data?.ldap_config) {
                        this.settingsForm.ldap_config = {
                            ...result.data.ldap_config,
                            bind_password: '',
                        };
                    }

                    if (result.data?.google_config) {
                        this.settingsForm.google_config = {
                            ...result.data.google_config,
                            service_account_json: '',
                            oauth_token_json: '',
                        };
                    }

                    if (result.data?.login_config) {
                        this.settingsForm.login_config = {
                            ...result.data.login_config,
                            google_sso: {
                                ...result.data.login_config.google_sso,
                                client_secret: '',
                            },
                            microsoft_sso: {
                                ...result.data.login_config.microsoft_sso,
                                client_secret: '',
                            },
                        };
                    }

                    if (this.form.category_id) {
                        this.loadCategoryFields(this.form.category_id);
                    }
                } catch (error) {
                    this.settingsErrorMessage = window.__i18n.settings_network_error;
                } finally {
                    this.isSavingSettings = false;
                }
            },
        };
    }
</script>
