<?php

declare(strict_types=1);

$portalFieldClass = 'w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-inner placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20';
$portalLabelClass = 'mb-1.5 block text-sm font-medium text-gray-700';
?>
<div
    x-show="isPortalTicketModalOpen"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-8"
    @keydown.escape.window="closePortalTicketModal()"
>
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closePortalTicketModal()"></div>

    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="portal-new-ticket-title"
        class="relative w-full max-w-xl overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm"
    >
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-8 py-6">
            <div class="min-w-0 pr-4">
                <h3 id="portal-new-ticket-title" class="text-lg font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('portal_new_ticket'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-1.5 text-sm leading-relaxed text-gray-500" x-show="!portalTicketLinkedAsset"><?= htmlspecialchars(__('portal_new_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button
                type="button"
                @click="closePortalTicketModal()"
                class="shrink-0 rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                aria-label="<?= htmlspecialchars(__('portal_cancel'), ENT_QUOTES, 'UTF-8') ?>"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div x-show="portalTicketLinkedAsset" x-cloak class="mx-8 mt-6 rounded-lg border border-amber-200/80 bg-amber-50 px-4 py-3.5">
            <p class="text-sm font-medium leading-relaxed text-amber-950" x-text="portalTicketLinkedAssetMessage()"></p>
        </div>

        <form @submit.prevent="submitPortalTicket()" class="space-y-6 p-8">
            <div class="space-y-5">
                <label class="block">
                    <span class="<?= $portalLabelClass ?>"><?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="portalTicketForm.subject" required class="<?= $portalFieldClass ?>" placeholder="<?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="block">
                    <span class="<?= $portalLabelClass ?>"><?= htmlspecialchars(__('ticket_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea x-model="portalTicketForm.description" rows="4" required class="<?= $portalFieldClass ?>" placeholder="<?= htmlspecialchars(__('portal_new_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                </label>

                <label class="block">
                    <span class="<?= $portalLabelClass ?>"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="portalTicketForm.priority" required class="<?= $portalFieldClass ?>">
                        <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                    <span class="mt-1.5 block text-xs leading-relaxed text-gray-400"><?= htmlspecialchars(__('portal_priority_hint'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>

            <p x-show="portalTicketFormError" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketFormError"></p>

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-end">
                <button
                    type="button"
                    @click="closePortalTicketModal()"
                    :disabled="isPortalTicketSubmitting"
                    class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <?= htmlspecialchars(__('portal_cancel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="submit"
                    :disabled="isPortalTicketSubmitting"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg x-show="isPortalTicketSubmitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
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
    class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-8"
    @keydown.escape.window="closePortalTicketDetail()"
>
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closePortalTicketDetail()"></div>

    <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-8 py-6">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400" x-text="portalTicketDetail?.ticket_number"></p>
                <h3 class="mt-1 text-lg font-semibold tracking-tight text-gray-900" x-text="portalTicketDetail?.subject"></h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalTicketStatusClass(portalTicketDetail?.status)" x-text="portalTicketStatusLabel(portalTicketDetail?.status)"></span>
                </div>
            </div>
            <button
                type="button"
                @click="closePortalTicketDetail()"
                class="shrink-0 rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                aria-label="<?= htmlspecialchars(__('portal_cancel'), ENT_QUOTES, 'UTF-8') ?>"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-8 py-6">
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-5 py-4">
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700" x-text="portalTicketDetail?.description"></p>
            </div>

            <div class="mt-8">
                <h4 class="text-sm font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('ticket_comments_title'), ENT_QUOTES, 'UTF-8') ?></h4>

                <p x-show="portalTicketDetailLoading" class="mt-4 text-sm text-gray-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>

                <p x-show="!portalTicketDetailLoading && portalTicketComments.length === 0" x-cloak class="mt-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-500">
                    <?= htmlspecialchars(__('ticket_no_comments'), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <div x-show="!portalTicketDetailLoading && portalTicketComments.length > 0" x-cloak class="mt-4 space-y-3">
                    <template x-for="comment in portalTicketComments" :key="comment.id">
                        <article class="rounded-lg border border-gray-100 bg-gray-50 px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-gray-900" x-text="comment.author_name"></span>
                                <time class="text-xs text-gray-400" x-text="formatPortalDate(comment.created_at)"></time>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-gray-700" x-text="comment.body"></p>
                        </article>
                    </template>
                </div>

                <form class="mt-6 space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm" @submit.prevent="submitPortalTicketComment()">
                    <label class="block">
                        <span class="<?= $portalLabelClass ?>"><?= htmlspecialchars(__('ticket_comment_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <textarea x-model="portalTicketCommentBody" rows="3" class="<?= $portalFieldClass ?>"></textarea>
                    </label>
                    <p x-show="portalTicketCommentError" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketCommentError"></p>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="isPortalTicketCommentSubmitting"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <?= htmlspecialchars(__('action_add_ticket_reply'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="border-t border-gray-100 px-8 py-5">
            <button
                type="button"
                @click="closePortalTicketDetail()"
                class="w-full rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 sm:w-auto"
            >
                <?= htmlspecialchars(__('portal_close'), ENT_QUOTES, 'UTF-8') ?>
            </button>
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
    class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex justify-center px-4"
>
    <p class="pointer-events-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-800 shadow-sm" x-text="portalToastMessage"></p>
</div>
