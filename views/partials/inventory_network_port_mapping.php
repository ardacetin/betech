<?php

declare(strict_types=1);

/**
 * Network port mapping block for inventory edit page.
 * Expects Alpine scope: switchAssets, portMappingForm
 */
?>
<section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('network_connection_settings_title'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('network_connection_settings_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('network_switch_select_label'), ENT_QUOTES, 'UTF-8') ?></span>
            <select
                x-model="portMappingForm.switch_asset_id"
                class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
            >
                <option value=""><?= htmlspecialchars(__('network_switch_select_placeholder'), ENT_QUOTES, 'UTF-8') ?></option>
                <template x-for="switchAsset in switchAssets" :key="switchAsset.id">
                    <option :value="String(switchAsset.id)" x-text="switchAsset.label"></option>
                </template>
            </select>
            <p x-show="switchAssets.length === 0" x-cloak class="mt-2 text-xs text-amber-700">
                <?= htmlspecialchars(__('network_switch_list_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </label>
        <label class="block">
            <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('network_port_number_label'), ENT_QUOTES, 'UTF-8') ?></span>
            <input
                x-model="portMappingForm.port_number"
                type="text"
                inputmode="numeric"
                placeholder="<?= htmlspecialchars(__('network_port_number_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
            >
        </label>
    </div>
</section>
