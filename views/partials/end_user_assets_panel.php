<?php

declare(strict_types=1);

/**
 * @var bool $hasPersonnelProfile
 */
?>
<section x-show="activeView === 'my_assets'" x-cloak class="space-y-6">
    <?php if (!$hasPersonnelProfile): ?>
    <div class="rounded-xl border border-amber-200/80 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-900">
        <?= htmlspecialchars(__('portal_profile_not_linked'), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div x-show="portalAssetsLoading" class="overflow-hidden rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm">
        <div class="mx-auto flex h-10 w-10 items-center justify-center">
            <svg class="h-5 w-5 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>
        <p class="mt-4 text-sm text-gray-500"><?= htmlspecialchars(__('portal_assets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <p x-show="portalAssetsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700" x-text="portalAssetsError"></p>

    <div
        x-show="!portalAssetsLoading && !portalAssetsError && portalAssets.length === 0"
        x-cloak
        class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm"
    >
        <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
        </svg>
        <p class="mt-6 text-base font-semibold tracking-tight text-gray-800"><?= htmlspecialchars(__('portal_assets_empty'), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-gray-500"><?= htmlspecialchars(__('portal_assets_empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        <button
            type="button"
            @click="activeView = 'my_tickets'; fetchPortalTickets()"
            class="mt-8 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50"
        >
            <?= htmlspecialchars(__('portal_create_ticket'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <div x-show="!portalAssetsLoading && portalAssets.length > 0" x-cloak class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400"><?= htmlspecialchars(__('portal_tab_assets'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars(__('portal_my_assets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="divide-y divide-gray-100">
            <template x-for="asset in portalAssets" :key="asset.id">
                <article class="px-6 py-5 transition hover:bg-gray-50/60">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400" x-text="asset.asset_tag"></p>
                            <h2 class="mt-1 text-base font-semibold tracking-tight text-gray-900" x-text="asset.name"></h2>
                            <p class="mt-1 text-sm text-gray-500" x-text="asset.category_name || '—'"></p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalAssetStatusClass(asset.status)" x-text="portalAssetStatusLabel(asset.status)"></span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" @click="printTutanak(asset.id)" class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50">
                            <?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button type="button" @click="openPortalTicketModalForAsset(asset)" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900 transition hover:bg-amber-100">
                            <?= htmlspecialchars(__('portal_report_issue'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </article>
            </template>
        </div>
    </div>
</section>
