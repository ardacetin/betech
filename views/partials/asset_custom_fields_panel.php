<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings' && settingsTab === 'asset_fields'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('asset_custom_fields_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('asset_custom_fields_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('asset_custom_fields_type_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <select
                    x-model="selectedAssetFieldTypeId"
                    @change="fetchAssetTypeCustomFields()"
                    class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                >
                    <template x-for="assetType in assetTypes" :key="assetType.id">
                        <option :value="assetType.id" x-text="assetType.name"></option>
                    </template>
                </select>
            </label>
            <button
                type="button"
                @click="openAssetCustomFieldModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('add_asset_custom_field'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <p x-show="assetCustomFieldsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('asset_custom_fields_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="assetCustomFieldsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assetCustomFieldsError"></p>
    <p x-show="assetCustomFieldsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="assetCustomFieldsSuccessMessage"></p>

    <p
        x-show="!assetCustomFieldsLoading && !assetCustomFieldsError && assetTypeCustomFields.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('asset_custom_fields_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!assetCustomFieldsLoading && assetTypeCustomFields.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_custom_field_label'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_custom_field_column'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_custom_field_type'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="field in assetTypeCustomFields" :key="field.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4 text-sm font-medium text-zinc-900" x-text="field.label"></td>
                        <td class="px-6 py-4 font-mono text-xs text-zinc-500" x-text="field.column_name"></td>
                        <td class="px-6 py-4 text-sm uppercase text-zinc-700" x-text="field.field_type"></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="openAssetCustomFieldModal(field)" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50">
                                    <?= htmlspecialchars(__('action_edit_asset_custom_field'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <button type="button" @click="deleteAssetCustomField(field)" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50">
                                    <?= htmlspecialchars(__('action_delete_asset_custom_field'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</section>
