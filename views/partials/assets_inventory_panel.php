<?php

declare(strict_types=1);

/**
 * @var list<array<string, mixed>> $assets
 * @var list<array<string, mixed>> $assetFilterDefinitions
 * @var array<string, string> $assetActiveFilters
 * @var bool $canManageAssets
 * @var bool $isSuperAdmin
 * @var callable $translateStatus
 */

$assetFilterDefinitions = $assetFilterDefinitions ?? [];
$assetActiveFilters = $assetActiveFilters ?? [];
?>
<div x-show="activeView === 'assets'" x-cloak class="min-w-0 space-y-6">
    <section class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-4 py-3">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900" x-text="activeAssetTypeName()"></h2>
                    <p class="mt-0.5 text-xs text-zinc-500"><?= htmlspecialchars(__('inventory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <p x-show="inventoryPagination.total > 0" x-cloak class="text-xs text-zinc-500">
                    <span x-text="inventoryPagination.total"></span>
                    <?= htmlspecialchars(__('inventory_filter_result_count_suffix'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <div class="border-b border-zinc-200 bg-zinc-50/70 px-4 py-3">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-700"><?= htmlspecialchars(__('inventory_filter_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="resetAssetFilters()"
                        class="rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-100"
                    >
                        <?= htmlspecialchars(__('inventory_filter_reset'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="button"
                        @click="applyAssetFilters()"
                        :disabled="assetFiltersLoading"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="assetFiltersLoading" x-cloak class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <?= htmlspecialchars(__('inventory_filter_apply'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <template x-for="field in assetFilterFields" :key="field.name">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-zinc-500" x-text="resolveAssetFilterLabel(field)"></span>
                        <input
                            x-show="field.input === 'text'"
                            type="text"
                            x-model="assetFilters[field.name]"
                            @keydown.enter.prevent="applyAssetFilters()"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-2"
                        >
                        <select
                            x-show="field.input === 'select'"
                            x-model="assetFilters[field.name]"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-2"
                        >
                            <option value=""><?= htmlspecialchars(__('inventory_filter_all'), ENT_QUOTES, 'UTF-8') ?></option>
                            <template x-for="option in (field.options || [])" :key="`${field.name}-${option.value}`">
                                <option :value="option.value" x-text="resolveAssetFilterOptionLabel(option)"></option>
                            </template>
                        </select>
                    </label>
                </template>
            </div>

            <p x-show="assetFiltersError" x-cloak class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700" x-text="assetFiltersError"></p>
        </div>

        <div x-show="importSummaryMessage" x-cloak class="border-b border-zinc-200 px-4 py-3">
            <p
                class="rounded-lg px-3 py-2 text-xs"
                :class="importSummaryIsError ? 'border border-rose-200 bg-rose-50 text-rose-700' : 'border border-emerald-200 bg-emerald-50 text-emerald-700'"
                x-text="importSummaryMessage"
            ></p>
        </div>

        <div class="px-4 py-3">
            <div class="w-full min-w-0 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="w-full table-fixed divide-y divide-slate-200">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <template x-for="(column, index) in inventoryGridColumns()" :key="column.column">
                                <th
                                    class="whitespace-nowrap px-3 py-1.5"
                                    :class="index === 0 ? 'sticky left-0 z-20 w-[22%] min-w-[10rem] border-r border-slate-100 bg-zinc-50' : 'w-[12%]'"
                                    x-text="column.label"
                                ></th>
                            </template>
                            <th class="w-[10%] whitespace-nowrap px-3 py-1.5"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white text-xs text-slate-600">
                        <tr x-show="!assetFiltersLoading && inventoryAssets.length === 0" x-cloak>
                            <td :colspan="inventoryGridColumns().length + 1" class="px-4 py-8 text-center text-sm text-slate-500">
                                <span x-show="hasActiveAssetFilters()" x-cloak><?= htmlspecialchars(__('inventory_assets_empty_filtered'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span x-show="!hasActiveAssetFilters()" x-cloak>
                                    <?= htmlspecialchars(__('empty_assets_prefix'), ENT_QUOTES, 'UTF-8') ?>
                                    <span class="font-medium text-slate-700"><?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?= htmlspecialchars(__('empty_assets_suffix'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                        <template x-for="asset in inventoryAssets" :key="asset.id">
                            <tr
                                class="group cursor-pointer hover:bg-zinc-50/80"
                                @click="openInventoryAssetModal(asset)"
                            >
                                <template x-for="(column, index) in inventoryGridColumns()" :key="`${asset.id}-${column.column}`">
                                    <td
                                        class="max-w-[180px] truncate whitespace-nowrap px-3 py-1.5"
                                        :class="index === 0 ? 'sticky left-0 z-10 border-r border-slate-100 bg-white text-sm font-medium text-slate-900 group-hover:bg-zinc-50/80' : ''"
                                        :title="String(asset[column.column] || '')"
                                    >
                                        <template x-if="column.column === 'status'">
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                                                :class="inventoryStatusClass(asset.status)"
                                                x-text="translateInventoryStatus(asset.status)"
                                            ></span>
                                        </template>
                                        <template x-if="column.column !== 'status'">
                                            <span x-text="resolveInventoryCellValue(asset, column.column)"></span>
                                        </template>
                                    </td>
                                </template>
                                <td class="whitespace-nowrap px-3 py-1.5" @click.stop>
                                    <div class="flex flex-wrap gap-1">
                                        <?php if ($canManageAssets): ?>
                                        <button
                                            type="button"
                                            x-show="!(asset.assigned_to || asset.user_name)"
                                            @click="openAssignModal(buildInventoryAssignPayload(asset))"
                                            class="rounded border border-emerald-200 px-1.5 py-0.5 text-[11px] font-medium text-emerald-800 hover:bg-emerald-50"
                                        ><?= htmlspecialchars(__('action_assign'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <?php endif; ?>
                                        <button
                                            type="button"
                                            x-show="asset.assigned_to || asset.user_name"
                                            @click="printTutanak(asset.id)"
                                            class="rounded border border-zinc-200 px-1.5 py-0.5 text-[11px] font-medium text-zinc-700 hover:bg-zinc-50"
                                        ><?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <?php if ($isSuperAdmin): ?>
                                        <button
                                            type="button"
                                            @click="deleteAsset(asset.id)"
                                            class="rounded border border-rose-200 px-1.5 py-0.5 text-[11px] font-medium text-rose-700 hover:bg-rose-50"
                                        ><?= htmlspecialchars(__('action_delete_asset'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        $listPagination = [
            'pagination' => 'inventoryPagination',
            'loading' => 'assetFiltersLoading',
            'goToPage' => 'goToInventoryPage',
            'pageNumbers' => 'inventoryPageNumbers',
            'label' => 'resolveInventoryPaginationLabel',
        ];
        require __DIR__ . '/list_pagination.php';
        ?>
    </section>
</div>
