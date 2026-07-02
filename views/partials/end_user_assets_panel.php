<?php

declare(strict_types=1);

/**
 * @var bool $hasPersonnelProfile
 */
?>
<section x-show="activeView === 'my_assets'" x-cloak class="space-y-6">
    <?php if (!$hasPersonnelProfile): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
        <?= htmlspecialchars(__('portal_profile_not_linked'), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <p x-show="portalAssetsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('portal_assets_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p x-show="portalAssetsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalAssetsError"></p>

    <section
        x-show="!portalAssetsLoading && !portalAssetsError && portalAssets.length === 0"
        x-cloak
        class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft"
    >
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-16 w-16 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
            </svg>
            <p class="mt-6 text-base font-medium text-zinc-700"><?= htmlspecialchars(__('portal_assets_empty'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mx-auto mt-2 max-w-md text-sm text-zinc-500"><?= htmlspecialchars(__('portal_assets_empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </section>

    <section x-show="!portalAssetsLoading && portalAssets.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_my_assets_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_my_assets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead class="bg-zinc-50 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="sticky left-0 z-10 whitespace-nowrap border-r border-slate-100 bg-zinc-50 px-4 py-2 text-left"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="whitespace-nowrap px-4 py-2 text-left"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="whitespace-nowrap px-4 py-2 text-left"><?= htmlspecialchars(__('col_asset_type_name'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="whitespace-nowrap px-4 py-2 text-left"><?= htmlspecialchars(__('col_serial_number'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="whitespace-nowrap px-4 py-2 text-left"><?= htmlspecialchars(__('col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="whitespace-nowrap px-4 py-2 text-left"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-slate-600">
                    <template x-for="asset in portalAssets" :key="`${asset.asset_type_slug || 'asset'}-${asset.id}`">
                        <tr class="group cursor-pointer hover:bg-zinc-50/80" @click="openPortalAssetDetail(asset)">
                            <td class="sticky left-0 z-10 max-w-[10rem] truncate border-r border-slate-100 bg-white px-4 py-2 font-medium text-slate-900 group-hover:bg-zinc-50/80" x-text="asset.asset_tag"></td>
                            <td class="max-w-[14rem] truncate px-4 py-2" x-text="asset.name"></td>
                            <td class="max-w-[10rem] truncate px-4 py-2" x-text="asset.asset_type_name || asset.type || '—'"></td>
                            <td class="max-w-[10rem] truncate px-4 py-2 font-mono" x-text="asset.serial_number || '—'"></td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset" :class="portalAssetStatusClass(asset.status)" x-text="portalAssetStatusLabel(asset.status)"></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2" @click.stop>
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" @click="openPortalAssetDetail(asset)" class="rounded border border-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 hover:bg-zinc-50">
                                        <?= htmlspecialchars(__('action_view_details'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <button type="button" @click="printTutanak(asset.id)" class="rounded border border-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 hover:bg-zinc-50">
                                        <?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                    <button type="button" @click="openPortalTicketModalForAsset(asset)" class="rounded border border-amber-200 px-2 py-1 text-[11px] font-medium text-amber-800 hover:bg-amber-50">
                                        <?= htmlspecialchars(__('portal_report_issue'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</section>
