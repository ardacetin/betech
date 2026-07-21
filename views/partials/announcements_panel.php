<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'announcements'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('announcements_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('announcements_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="openAnnouncementModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-[var(--brand,#7a242c)] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[var(--brand-hover,#641c23)]"
        >
            <span class="text-lg leading-none">+</span>
            <?= htmlspecialchars(__('announcements_add'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <p x-show="announcementsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('announcements_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="announcementsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="announcementsError"></p>
    <p x-show="announcementsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="announcementsSuccessMessage"></p>

    <p
        x-show="!announcementsLoading && !announcementsError && announcements.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('announcements_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!announcementsLoading && announcements.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('announcements_col_title'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('announcements_col_category'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('announcements_col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="item in announcements" :key="item.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-zinc-900" x-text="item.title"></p>
                            <p class="mt-1 line-clamp-2 text-xs text-zinc-500" x-text="item.summary"></p>
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="item.category || '—'"></td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="item.is_published ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-zinc-100 text-zinc-600 ring-zinc-500/20'"
                                x-text="item.is_published ? '<?= htmlspecialchars(__('kb_status_published'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('kb_status_draft'), ENT_QUOTES, 'UTF-8') ?>'"
                            ></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="openAnnouncementModal(item)" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"><?= htmlspecialchars(__('announcements_edit'), ENT_QUOTES, 'UTF-8') ?></button>
                                <button type="button" @click="deleteAnnouncement(item)" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100"><?= htmlspecialchars(__('announcements_delete'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div x-show="isAnnouncementModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center px-4" @keydown.escape.window="closeAnnouncementModal()">
        <div class="absolute inset-0 bg-zinc-900/40" @click="closeAnnouncementModal()"></div>
        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-zinc-900" x-text="announcementForm.id ? '<?= htmlspecialchars(__('announcements_edit'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('announcements_add'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
                <button type="button" @click="closeAnnouncementModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100">&times;</button>
            </div>
            <form @submit.prevent="submitAnnouncementForm" class="space-y-4 px-6 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('announcements_col_title'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="announcementForm.title" required maxlength="255" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('announcements_col_category'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="announcementForm.category" maxlength="64" placeholder="<?= htmlspecialchars(__('landing_tag_maintenance'), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('announcements_summary'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea x-model="announcementForm.summary" rows="4" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400"></textarea>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                    <input type="checkbox" x-model="announcementForm.is_published" class="rounded border-zinc-300">
                    <?= htmlspecialchars(__('announcements_publish'), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <p x-show="announcementFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="announcementFormError"></p>
                <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeAnnouncementModal()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isAnnouncementSubmitting" class="rounded-xl bg-[var(--brand,#7a242c)] px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60">
                        <span x-show="!isAnnouncementSubmitting"><?= htmlspecialchars(__('settings_save'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="isAnnouncementSubmitting" x-cloak><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
