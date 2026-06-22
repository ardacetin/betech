<?php

declare(strict_types=1);

$adminFieldClass = 'w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4';
$adminLabelClass = 'mb-1.5 block text-sm font-medium text-zinc-700';
?>
<div
    x-show="isPortalTicketModalOpen"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center px-4"
    @keydown.escape.window="closePortalTicketModal()"
>
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closePortalTicketModal()"></div>
    <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_new_ticket'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-1 text-sm text-zinc-500" x-show="!portalTicketLinkedAsset"><?= htmlspecialchars(__('portal_new_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button type="button" @click="closePortalTicketModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
        </div>
        <form @submit.prevent="submitPortalTicket()" class="px-6 py-5">
            <div x-show="portalTicketLinkedAsset" x-cloak class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700"><?= htmlspecialchars(__('ticket_linked_asset_title'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-sm font-medium text-zinc-900" x-text="portalTicketLinkedAssetMessage()"></p>
            </div>
            <div class="grid gap-4">
                <label class="block">
                    <span class="<?= $adminLabelClass ?>"><?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="portalTicketForm.subject" required class="<?= $adminFieldClass ?>">
                </label>
                <label class="block">
                    <span class="<?= $adminLabelClass ?>"><?= htmlspecialchars(__('ticket_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea x-model="portalTicketForm.description" rows="4" required class="<?= $adminFieldClass ?>"></textarea>
                </label>
                <label class="block sm:max-w-xs">
                    <span class="<?= $adminLabelClass ?>"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="portalTicketForm.priority" required class="<?= $adminFieldClass ?>">
                        <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
            </div>
            <p x-show="portalTicketFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketFormError"></p>
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                <button type="button" @click="closePortalTicketModal()" :disabled="isPortalTicketSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="submit" :disabled="isPortalTicketSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="isPortalTicketSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isPortalTicketSubmitting"><?= htmlspecialchars(__('portal_submit_ticket'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<div
    x-show="isPortalTicketDetailOpen"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center px-4"
    @keydown.escape.window="closePortalTicketDetail()"
>
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closePortalTicketDetail()"></div>
    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="portalTicketDetail?.ticket_number"></p>
                <h3 class="mt-1 text-lg font-semibold text-zinc-900" x-text="portalTicketDetail?.subject"></h3>
                <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_ticket_detail_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button type="button" @click="closePortalTicketDetail()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
        </div>
        <div class="overflow-y-auto px-6 py-5">
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalTicketStatusClass(portalTicketDetail?.status)" x-text="portalTicketStatusLabel(portalTicketDetail?.status)"></span>
            </div>
            <p class="mt-4 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-700" x-text="portalTicketDetail?.description"></p>
            <div class="mt-6">
                <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('ticket_comments_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                <p x-show="portalTicketDetailLoading" x-cloak class="mt-4 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                <p x-show="!portalTicketDetailLoading && portalTicketComments.length === 0" x-cloak class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"><?= htmlspecialchars(__('ticket_no_comments'), ENT_QUOTES, 'UTF-8') ?></p>
                <div x-show="!portalTicketDetailLoading && portalTicketComments.length > 0" x-cloak class="mt-4 space-y-3">
                    <template x-for="comment in portalTicketComments" :key="comment.id">
                        <article class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900" x-text="comment.author_name"></span>
                                <time class="text-xs text-zinc-400" x-text="formatPortalDate(comment.created_at)"></time>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-700" x-text="comment.body"></p>
                        </article>
                    </template>
                </div>
                <form class="mt-4 space-y-3" @submit.prevent="submitPortalTicketComment()">
                    <label class="block">
                        <span class="<?= $adminLabelClass ?>"><?= htmlspecialchars(__('ticket_comment_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <textarea x-model="portalTicketCommentBody" rows="3" class="<?= $adminFieldClass ?>"></textarea>
                    </label>
                    <p x-show="portalTicketCommentError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketCommentError"></p>
                    <button type="submit" :disabled="isPortalTicketCommentSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isPortalTicketCommentSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isPortalTicketCommentSubmitting"><?= htmlspecialchars(__('action_add_ticket_reply'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </form>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-6 py-4">
            <button type="button" @click="closePortalTicketDetail()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>

<p
    x-show="portalToastVisible"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-y-2 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-2 opacity-0"
    class="fixed inset-x-0 top-4 z-[70] mx-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 shadow-soft"
    x-text="portalToastMessage"
></p>
