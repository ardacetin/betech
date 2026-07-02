<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings' && settingsTab === 'asset_components'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('asset_components_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('asset_components_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('asset_components_type_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <select
                    x-model="selectedAssetComponentTypeId"
                    @change="fetchAssetTypeComponents()"
                    class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                >
                    <template x-for="assetType in assetTypes" :key="assetType.id">
                        <option :value="assetType.id" x-text="assetType.name"></option>
                    </template>
                </select>
            </label>
            <button
                type="button"
                @click="openAssetComponentModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('add_asset_component'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <p x-show="assetComponentsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('asset_components_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="assetComponentsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assetComponentsError"></p>
    <p x-show="assetComponentsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="assetComponentsSuccessMessage"></p>

    <p
        x-show="!assetComponentsLoading && !assetComponentsError && assetTypeComponents.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('asset_components_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!assetComponentsLoading && assetTypeComponents.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_component_name'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_component_column'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="component in assetTypeComponents" :key="component.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4 text-sm font-medium text-zinc-900" x-text="component.name"></td>
                        <td class="px-6 py-4 font-mono text-xs text-zinc-500" x-text="component.column_name"></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="openAssetComponentModal(component)" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50">
                                    <?= htmlspecialchars(__('action_edit_asset_component'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <button type="button" @click="deleteAssetComponent(component)" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50">
                                    <?= htmlspecialchars(__('action_delete_asset_component'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</section>
