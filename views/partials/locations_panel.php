<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings' && settingsTab === 'locations'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('locations_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('locations_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="openLocationModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
        >
            <span class="text-lg leading-none">+</span>
            <?= htmlspecialchars(__('add_location'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <p x-show="locationsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('locations_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="locationsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="locationsError"></p>
    <p x-show="locationsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="locationsSuccessMessage"></p>

    <p
        x-show="!locationsLoading && !locationsError && locations.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('locations_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!locationsLoading && locations.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_location_name'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_location_building'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('location_description_label'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_location_assets'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="location in locations" :key="location.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-zinc-900" x-text="location.name"></p>
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="location.building || '—'"></td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="location.description || '—'"></td>
                        <td class="px-6 py-4 text-sm tabular-nums text-zinc-700" x-text="location.asset_count ?? 0"></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="openLocationModal(location)"
                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                >
                                    <?= htmlspecialchars(__('action_edit_location'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <button
                                    type="button"
                                    @click="deleteLocation(location)"
                                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                >
                                    <?= htmlspecialchars(__('action_delete_location'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</section>
