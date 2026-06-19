<?php

declare(strict_types=1);
?>
<div x-show="isPortalTicketModalOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:px-4" @keydown.escape.window="closePortalTicketModal()">
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closePortalTicketModal()"></div>
    <div class="relative max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-zinc-200 bg-white shadow-soft sm:max-w-lg sm:rounded-2xl">
        <div class="border-b border-zinc-200 px-5 py-4">
            <h3 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('portal_new_ticket'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-sm text-zinc-500" x-show="!portalTicketLinkedAsset"><?= htmlspecialchars(__('portal_new_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div x-show="portalTicketLinkedAsset" x-cloak class="mx-5 mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-sm font-medium text-amber-950" x-text="portalTicketLinkedAssetMessage()"></p>
        </div>
        <form @submit.prevent="submitPortalTicket()" class="space-y-4 px-5 py-5">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="portalTicketForm.subject" required class="w-full rounded-lg border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-200">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <textarea x-model="portalTicketForm.description" rows="4" required class="w-full rounded-lg border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-200"></textarea>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <select x-model="portalTicketForm.priority" required class="w-full rounded-lg border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-200">
                    <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </label>
            <p x-show="portalTicketFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketFormError"></p>
            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-4">
                <button type="button" @click="closePortalTicketModal()" class="rounded-lg border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="submit" :disabled="isPortalTicketSubmitting" class="rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60">
                    <span x-show="isPortalTicketSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isPortalTicketSubmitting"><?= htmlspecialchars(__('portal_submit_ticket'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<div x-show="isPortalTicketDetailOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:px-4" @keydown.escape.window="closePortalTicketDetail()">
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closePortalTicketDetail()"></div>
    <div class="relative flex max-h-[92vh] w-full flex-col rounded-t-2xl border border-zinc-200 bg-white shadow-soft sm:max-w-lg sm:rounded-2xl">
        <div class="border-b border-zinc-200 px-5 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="portalTicketDetail?.ticket_number"></p>
            <h3 class="mt-1 text-lg font-semibold tracking-tight text-zinc-900" x-text="portalTicketDetail?.subject"></h3>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalTicketStatusClass(portalTicketDetail?.status)" x-text="portalTicketStatusLabel(portalTicketDetail?.status)"></span>
            </div>
        </div>
        <div class="overflow-y-auto px-5 py-5">
            <p class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 text-sm text-zinc-700" x-text="portalTicketDetail?.description"></p>
            <div class="mt-6">
                <h4 class="text-sm font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('ticket_comments_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                <p x-show="portalTicketDetailLoading" class="mt-3 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                <p x-show="!portalTicketDetailLoading && portalTicketComments.length === 0" x-cloak class="mt-3 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"><?= htmlspecialchars(__('ticket_no_comments'), ENT_QUOTES, 'UTF-8') ?></p>
                <div x-show="!portalTicketDetailLoading && portalTicketComments.length > 0" x-cloak class="mt-3 space-y-3">
                    <template x-for="comment in portalTicketComments" :key="comment.id">
                        <article class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3">
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
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_comment_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <textarea x-model="portalTicketCommentBody" rows="3" class="w-full rounded-lg border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-200"></textarea>
                    </label>
                    <p x-show="portalTicketCommentError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketCommentError"></p>
                    <button type="submit" :disabled="isPortalTicketCommentSubmitting" class="rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"><?= htmlspecialchars(__('action_add_ticket_reply'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
        <div class="border-t border-zinc-200 px-5 py-4">
            <button type="button" @click="closePortalTicketDetail()" class="w-full rounded-lg border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>

<div
    x-show="portalToastVisible"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-y-2 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-2 opacity-0"
    class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex justify-center px-4"
>
    <p class="pointer-events-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm" x-text="portalToastMessage"></p>
</div>
