<?php

declare(strict_types=1);

/**
 * @var list<array<string, mixed>> $assets
 * @var list<array<string, mixed>> $assetFilterDefinitions
 * @var array<string, string> $assetActiveFilters
 * @var bool $canManageAssets
 * @var bool $isSuperAdmin
 * @var array<string, string> $statusStyles
 * @var callable $translateStatus
 * @var callable $formatPropertyValue
 * @var callable $formatLocationLabel
 */

$assetFilterDefinitions = $assetFilterDefinitions ?? [];
$assetActiveFilters = $assetActiveFilters ?? [];
?>
<div x-show="activeView === 'assets'" x-cloak class="space-y-8">
    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('inventory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <p x-show="inventoryAssets.length > 0" x-cloak class="text-sm text-zinc-500">
                    <span x-text="inventoryAssets.length"></span>
                    <?= htmlspecialchars(__('inventory_filter_result_count_suffix'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <div class="border-b border-zinc-200 bg-zinc-50/70 px-6 py-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_filter_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="resetAssetFilters()"
                        class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-100"
                    >
                        <?= htmlspecialchars(__('inventory_filter_reset'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="button"
                        @click="applyAssetFilters()"
                        :disabled="assetFiltersLoading"
                        class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="assetFiltersLoading" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <?= htmlspecialchars(__('inventory_filter_apply'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <template x-for="field in assetFilterFields" :key="field.name">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-zinc-500" x-text="resolveAssetFilterLabel(field)"></span>
                        <input
                            x-show="field.input === 'text'"
                            type="text"
                            x-model="assetFilters[field.name]"
                            @keydown.enter.prevent="applyAssetFilters()"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                        <select
                            x-show="field.input === 'select'"
                            x-model="assetFilters[field.name]"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                            <option value=""><?= htmlspecialchars(__('inventory_filter_all'), ENT_QUOTES, 'UTF-8') ?></option>
                            <template x-for="option in (field.options || [])" :key="`${field.name}-${option.value}`">
                                <option :value="option.value" x-text="resolveAssetFilterOptionLabel(option)"></option>
                            </template>
                        </select>
                    </label>
                </template>
            </div>

            <p x-show="assetFiltersError" x-cloak class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assetFiltersError"></p>
        </div>

        <div x-show="importSummaryMessage" x-cloak class="border-b border-zinc-200 px-6 py-4">
            <p
                class="rounded-xl px-4 py-3 text-sm"
                :class="importSummaryIsError ? 'border border-rose-200 bg-rose-50 text-rose-700' : 'border border-emerald-200 bg-emerald-50 text-emerald-700'"
                x-text="importSummaryMessage"
            ></p>
            <ul x-show="importSummaryErrors.length > 0" x-cloak class="mt-3 max-h-40 space-y-1 overflow-y-auto rounded-xl border border-rose-100 bg-rose-50/50 px-4 py-3 text-xs text-rose-700">
                <template x-for="(item, index) in importSummaryErrors" :key="index">
                    <li x-text="formatImportError(item)"></li>
                </template>
            </ul>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_location'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_properties'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white">
                    <tr x-show="!assetFiltersLoading && inventoryAssets.length === 0" x-cloak>
                        <td colspan="8" class="px-6 py-12 text-center text-sm text-zinc-500">
                            <span x-show="hasActiveAssetFilters()" x-cloak><?= htmlspecialchars(__('inventory_assets_empty_filtered'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span x-show="!hasActiveAssetFilters()" x-cloak>
                                <?= htmlspecialchars(__('empty_assets_prefix'), ENT_QUOTES, 'UTF-8') ?>
                                <span class="font-medium text-zinc-700"><?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                                <?= htmlspecialchars(__('empty_assets_suffix'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                    <template x-for="asset in inventoryAssets" :key="asset.id">
                        <tr class="hover:bg-zinc-50/80">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900" x-text="asset.asset_tag"></td>
                            <td class="px-6 py-4 text-sm text-zinc-700" x-text="asset.name"></td>
                            <td class="px-6 py-4 text-sm text-zinc-600" x-text="asset.category_name || window.__i18n.unknown_category"></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="inventoryStatusClass(asset.status)" x-text="translateInventoryStatus(asset.status)"></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-600">
                                <span x-show="asset.user_name" x-text="asset.user_name"></span>
                                <span x-show="!asset.user_name" class="text-zinc-400"><?= htmlspecialchars(__('not_assigned'), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-600">
                                <span x-show="formatInventoryLocation(asset)" x-text="formatInventoryLocation(asset)"></span>
                                <span x-show="!formatInventoryLocation(asset)" class="text-zinc-400"><?= htmlspecialchars(__('not_located'), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex max-w-xl flex-wrap gap-2">
                                    <template x-if="inventoryPropertyEntries(asset).length === 0">
                                        <span class="text-xs text-zinc-400"><?= htmlspecialchars(__('no_properties'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </template>
                                    <template x-for="entry in inventoryPropertyEntries(asset)" :key="`${asset.id}-${entry[0]}`">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-xs text-zinc-700">
                                            <span class="font-medium" x-text="`${entry[0]}:`"></span>
                                            <span x-text="formatInventoryPropertyValue(entry[1])"></span>
                                        </span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="openDetailModal(buildInventoryDetailPayload(asset))"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    >
                                        <?= htmlspecialchars(__('action_view_history'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <?php if ($canManageAssets): ?>
                                    <button
                                        type="button"
                                        x-show="!asset.user_id"
                                        @click="openAssignModal(buildInventoryAssignPayload(asset))"
                                        class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-medium text-emerald-800 transition hover:bg-emerald-50"
                                    >
                                        <?= htmlspecialchars(__('action_assign'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openEditModal(buildInventoryEditPayload(asset))"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    >
                                        <?= htmlspecialchars(__('action_edit'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        x-show="asset.user_id"
                                        @click="printTutanak(asset.id)"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    >
                                        <?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <?php if ($canManageAssets): ?>
                                    <button
                                        type="button"
                                        x-show="asset.user_id"
                                        @click="openReturnModal(buildInventoryReturnPayload(asset))"
                                        class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-50"
                                    >
                                        <?= htmlspecialchars(__('action_return_to_storage'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <button
                                        type="button"
                                        x-show="asset.user_id"
                                        @click="openTransferModal(buildInventoryTransferPayload(asset))"
                                        class="rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-medium text-indigo-800 transition hover:bg-indigo-50"
                                    >
                                        <?= htmlspecialchars(__('action_transfer'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($isSuperAdmin): ?>
                                    <button
                                        type="button"
                                        @click="deleteAsset(asset.id)"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                    >
                                        <?= htmlspecialchars(__('action_delete_asset'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>
