<?php

declare(strict_types=1);

/**
 * @var bool $hasPersonnelProfile
 */

$adminFieldClass = 'w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4';
$adminLabelClass = 'mb-1.5 block text-sm font-medium text-zinc-700';
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
            <button
                type="button"
                @click="activeView = 'my_tickets'; fetchPortalTickets()"
                class="mt-8 inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50"
            >
                <?= htmlspecialchars(__('portal_create_ticket'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </section>

    <section x-show="!portalAssetsLoading && portalAssets.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_my_assets_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_my_assets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="divide-y divide-zinc-100">
            <template x-for="asset in portalAssets" :key="asset.id">
                <article class="px-6 py-4 hover:bg-zinc-50/80">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="asset.asset_tag"></p>
                            <h3 class="mt-1 text-sm font-medium text-zinc-900" x-text="asset.name"></h3>
                            <p class="mt-1 text-sm text-zinc-600" x-text="asset.category_name || '—'"></p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalAssetStatusClass(asset.status)" x-text="portalAssetStatusLabel(asset.status)"></span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" @click="printTutanak(asset.id)" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50">
                            <?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button type="button" @click="openPortalTicketModalForAsset(asset)" class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-50">
                            <?= htmlspecialchars(__('portal_report_issue'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </article>
            </template>
        </div>
    </section>
</section>
