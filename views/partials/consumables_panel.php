<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'consumables'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('consumables_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('consumables_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="openConsumableModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
        >
            <span class="text-lg leading-none">+</span>
            <?= htmlspecialchars(__('add_consumable'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <p x-show="consumablesSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="consumablesSuccessMessage"></p>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('consumables_filter_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <p x-show="consumablesPagination.total > 0" x-cloak class="text-sm text-zinc-500">
                    <span x-text="consumablesPagination.total"></span>
                    <?= htmlspecialchars(__('consumables_filter_result_count_suffix'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <div class="border-b border-zinc-200 bg-zinc-50/70 px-6 py-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('consumables_filter_title'), ENT_QUOTES, 'UTF-8') ?></span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="resetConsumableFilters()"
                        class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-100"
                    >
                        <?= htmlspecialchars(__('consumables_filter_reset'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="button"
                        @click="applyConsumableFilters()"
                        :disabled="consumablesLoading"
                        class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="consumablesLoading" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <?= htmlspecialchars(__('consumables_filter_apply'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <template x-for="field in consumableFilterFields" :key="field.name">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-zinc-500" x-text="resolveConsumableFilterLabel(field)"></span>
                        <input
                            x-show="field.input === 'text'"
                            type="text"
                            x-model="consumableFilters[field.name]"
                            @keydown.enter.prevent="applyConsumableFilters()"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                        <select
                            x-show="field.input === 'select'"
                            x-model="consumableFilters[field.name]"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                            <option value=""><?= htmlspecialchars(__('consumables_filter_all'), ENT_QUOTES, 'UTF-8') ?></option>
                            <template x-for="option in (field.options || [])" :key="`${field.name}-${option.value}`">
                                <option :value="option.value" x-text="resolveConsumableFilterOptionLabel(option)"></option>
                            </template>
                        </select>
                    </label>
                </template>
            </div>

            <p x-show="consumablesError" x-cloak class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="consumablesError"></p>
        </div>

        <p x-show="consumablesLoading" x-cloak class="px-6 py-6 text-sm text-zinc-500">
            <?= htmlspecialchars(__('consumables_loading'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p
            x-show="!consumablesLoading && !consumablesError && consumables.length === 0 && !hasActiveConsumableFilters()"
            x-cloak
            class="px-6 py-8 text-sm text-zinc-500"
        >
            <?= htmlspecialchars(__('consumables_empty'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p
            x-show="!consumablesLoading && !consumablesError && consumables.length === 0 && hasActiveConsumableFilters()"
            x-cloak
            class="px-6 py-8 text-sm text-zinc-500"
        >
            <?= htmlspecialchars(__('consumables_filter_empty'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <div x-show="!consumablesLoading && consumables.length > 0" x-cloak>
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_consumable_name'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_consumable_quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_consumable_min_stock'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_consumable_location'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="item in consumables" :key="item.id">
                        <tr class="hover:bg-zinc-50/80">
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-zinc-900" x-text="item.name"></p>
                                    <span
                                        x-show="consumableIsLowStock(item)"
                                        x-cloak
                                        class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20"
                                    ><?= htmlspecialchars(__('consumable_low_stock'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm tabular-nums text-zinc-700" x-text="item.quantity"></td>
                            <td class="px-6 py-4 text-sm tabular-nums text-zinc-600" x-text="item.min_stock_level"></td>
                            <td class="px-6 py-4 text-sm text-zinc-600" x-text="item.location_label || '—'"></td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="openConsumableAdjustModal(item, 'checkout')"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    ><?= htmlspecialchars(__('consumable_checkout'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <button
                                        type="button"
                                        @click="openConsumableAdjustModal(item, 'restock')"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    ><?= htmlspecialchars(__('consumable_restock'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <button
                                        type="button"
                                        @click="openConsumableModal(item)"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    ><?= htmlspecialchars(__('edit_consumable'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <button
                                        type="button"
                                        @click="deleteConsumable(item)"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100"
                                    ><?= htmlspecialchars(__('delete_consumable'), ENT_QUOTES, 'UTF-8') ?></button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <?php
            $listPagination = [
                'pagination' => 'consumablesPagination',
                'loading' => 'consumablesLoading',
                'goToPage' => 'goToConsumablesPage',
                'pageNumbers' => 'consumablesPageNumbers',
                'label' => 'resolveConsumablesPaginationLabel',
            ];
            require __DIR__ . '/list_pagination.php';
            ?>
        </div>
    </div>
</section>
