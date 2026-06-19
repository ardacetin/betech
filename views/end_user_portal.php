<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var string $csrfToken
 * @var string $userName
 * @var string $userEmail
 * @var bool $hasPersonnelProfile
 */

$statusStyles = [
    'ready' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'deployed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'storage' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'broken' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'under_repair' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
];

$displayName = $userName !== '' ? $userName : $userEmail;

$i18n = [
    'locale' => $locale ?? 'tr',
    'portal_assets_loading' => __('portal_assets_loading'),
    'portal_assets_empty' => __('portal_assets_empty'),
    'portal_assets_empty_hint' => __('portal_assets_empty_hint'),
    'portal_create_ticket' => __('portal_create_ticket'),
    'portal_assets_error' => __('portal_assets_error'),
    'portal_tickets_loading' => __('portal_tickets_loading'),
    'portal_tickets_empty' => __('portal_tickets_empty'),
    'portal_tickets_error' => __('portal_tickets_error'),
    'portal_profile_not_linked' => __('portal_profile_not_linked'),
    'ticket_create_success' => __('ticket_create_success'),
    'ticket_create_error' => __('ticket_create_error'),
    'ticket_comment_create_success' => __('ticket_comment_create_success'),
    'ticket_comment_create_error' => __('ticket_comment_create_error'),
    'ticket_not_found' => __('ticket_not_found'),
    'ticket_status_open' => __('ticket_status_open'),
    'ticket_status_in_progress' => __('ticket_status_in_progress'),
    'ticket_status_resolved' => __('ticket_status_resolved'),
    'ticket_status_closed' => __('ticket_status_closed'),
    'ticket_priority_low' => __('ticket_priority_low'),
    'ticket_priority_medium' => __('ticket_priority_medium'),
    'ticket_priority_high' => __('ticket_priority_high'),
    'ticket_priority_critical' => __('ticket_priority_critical'),
    'ticket_no_comments' => __('ticket_no_comments'),
    'saving' => __('saving'),
    'status_ready' => __('status_ready'),
    'status_deployed' => __('status_deployed'),
    'status_storage' => __('status_storage'),
    'status_broken' => __('status_broken'),
    'status_under_repair' => __('status_under_repair'),
    'portal_report_issue' => __('portal_report_issue'),
    'portal_ticket_for_asset' => __('portal_ticket_for_asset'),
];
?>
<div class="min-h-full bg-gray-50" x-data="endUserPortal()" x-init="init()">
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-5xl px-4">
            <div class="flex h-16 items-center justify-between gap-4">
                <div class="shrink-0">
                    <span class="text-lg font-bold tracking-tight text-gray-900">ITMS</span>
                </div>

                <nav class="hidden flex-1 items-center justify-center gap-1 sm:flex" aria-label="<?= htmlspecialchars(__('portal_page_title'), ENT_QUOTES, 'UTF-8') ?>">
                    <button
                        type="button"
                        @click="activeTab = 'assets'"
                        class="rounded-md px-4 py-2 text-sm font-medium transition"
                        :class="activeTab === 'assets'
                            ? 'bg-gray-100 text-gray-900 shadow-sm ring-1 ring-gray-200/80'
                            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                    >
                        <?= htmlspecialchars(__('portal_tab_assets'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="button"
                        @click="switchToTickets()"
                        class="rounded-md px-4 py-2 text-sm font-medium transition"
                        :class="activeTab === 'tickets'
                            ? 'bg-gray-100 text-gray-900 shadow-sm ring-1 ring-gray-200/80'
                            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                    >
                        <?= htmlspecialchars(__('portal_tab_tickets'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </nav>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="hidden items-center gap-2 sm:flex">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.981 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                        <span class="max-w-[10rem] truncate text-sm text-gray-600"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <a
                        href="/logout"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50"
                    >
                        <svg class="h-4 w-4 text-gray-400 sm:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.981 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span><?= htmlspecialchars(__('nav_logout'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </div>
            </div>

            <nav class="flex items-center gap-1 border-t border-gray-100 pb-3 pt-2 sm:hidden" aria-label="<?= htmlspecialchars(__('portal_page_title'), ENT_QUOTES, 'UTF-8') ?>">
                <button
                    type="button"
                    @click="activeTab = 'assets'"
                    class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="activeTab === 'assets'
                        ? 'border-b-2 border-gray-900 bg-gray-100 text-gray-900'
                        : 'border-b-2 border-transparent text-gray-500'"
                >
                    <?= htmlspecialchars(__('portal_tab_assets'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="switchToTickets()"
                    class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="activeTab === 'tickets'
                        ? 'border-b-2 border-gray-900 bg-gray-100 text-gray-900'
                        : 'border-b-2 border-transparent text-gray-500'"
                >
                    <?= htmlspecialchars(__('portal_tab_tickets'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        <?php if (!$hasPersonnelProfile): ?>
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
            <?= htmlspecialchars(__('portal_profile_not_linked'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section x-show="activeTab === 'assets'" x-cloak class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('portal_my_assets_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="mt-1.5 text-sm text-gray-500"><?= htmlspecialchars(__('portal_my_assets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div x-show="assetsLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-gray-500"><?= htmlspecialchars(__('portal_assets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <p x-show="assetsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assetsError"></p>

            <div
                x-show="!assetsLoading && !assetsError && assets.length === 0"
                x-cloak
                class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm"
            >
                <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                </svg>
                <p class="mt-6 text-base font-medium text-gray-700"><?= htmlspecialchars(__('portal_assets_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500"><?= htmlspecialchars(__('portal_assets_empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                <button
                    type="button"
                    @click="goToTicketsView()"
                    class="mt-8 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-400 hover:bg-gray-50"
                >
                    <?= htmlspecialchars(__('portal_create_ticket'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div x-show="!assetsLoading && assets.length > 0" x-cloak class="space-y-4">
                <template x-for="asset in assets" :key="asset.id">
                    <article class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-gray-200">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400" x-text="asset.asset_tag"></p>
                                <h2 class="mt-1 text-base font-semibold tracking-tight text-gray-900" x-text="asset.name"></h2>
                                <p class="mt-1 text-sm text-gray-500" x-text="asset.category_name || '—'"></p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(asset.status)" x-text="statusLabel(asset.status)"></span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" @click="printTutanak(asset.id)" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50">
                                <?= htmlspecialchars(__('action_print_tutanak'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <button type="button" @click="openTicketModalForAsset(asset)" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900 transition hover:bg-amber-100">
                                <?= htmlspecialchars(__('portal_report_issue'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <section x-show="activeTab === 'tickets'" x-cloak class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('portal_my_tickets_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-1.5 text-sm text-gray-500"><?= htmlspecialchars(__('portal_my_tickets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="openTicketModal()" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 sm:w-auto">
                    <span class="text-lg leading-none">+</span>
                    <?= htmlspecialchars(__('portal_open_new_ticket'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div x-show="ticketsLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-gray-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <p x-show="ticketsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketsError"></p>

            <div
                x-show="!ticketsLoading && !ticketsError && tickets.length === 0"
                x-cloak
                class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm"
            >
                <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
                <p class="mt-6 text-base font-medium text-gray-700"><?= htmlspecialchars(__('portal_tickets_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                <button
                    type="button"
                    @click="openTicketModal()"
                    class="mt-8 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-400 hover:bg-gray-50"
                >
                    <?= htmlspecialchars(__('portal_create_ticket'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div x-show="!ticketsLoading && tickets.length > 0" x-cloak class="space-y-4">
                <template x-for="ticket in tickets" :key="ticket.id">
                    <button type="button" @click="openTicketDetail(ticket)" class="w-full rounded-xl border border-gray-100 bg-white p-5 text-left shadow-sm transition hover:border-gray-200">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-xs font-medium tabular-nums text-gray-500" x-text="ticket.ticket_number"></p>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="ticketStatusClass(ticket.status)" x-text="ticketStatusLabel(ticket.status)"></span>
                        </div>
                        <h2 class="mt-2 text-base font-semibold tracking-tight text-gray-900" x-text="ticket.subject"></h2>
                        <p class="mt-1 line-clamp-2 text-sm text-gray-500" x-text="ticket.description"></p>
                        <p class="mt-3 text-xs text-gray-400" x-text="formatDate(ticket.created_at)"></p>
                    </button>
                </template>
            </div>
        </section>
    </main>

    <div x-show="isTicketModalOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:px-4" @keydown.escape.window="closeTicketModal()">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closeTicketModal()"></div>
        <div class="relative max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-gray-200 bg-white shadow-xl sm:max-w-lg sm:rounded-2xl">
            <div class="border-b border-gray-200 px-5 py-4">
                <h3 class="text-lg font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('portal_new_ticket'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-1 text-sm text-gray-500" x-show="!ticketLinkedAsset"><?= htmlspecialchars(__('portal_new_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div x-show="ticketLinkedAsset" x-cloak class="mx-5 mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-medium text-amber-950" x-text="ticketLinkedAssetMessage()"></p>
            </div>
            <form @submit.prevent="submitTicket()" class="space-y-4 px-5 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700"><?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="ticketForm.subject" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-200">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700"><?= htmlspecialchars(__('ticket_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea x-model="ticketForm.description" rows="4" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-200"></textarea>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="ticketForm.priority" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-200">
                        <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
                <p x-show="ticketFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketFormError"></p>
                <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                    <button type="button" @click="closeTicketModal()" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isTicketSubmitting" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-60">
                        <span x-show="isTicketSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isTicketSubmitting"><?= htmlspecialchars(__('portal_submit_ticket'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="isTicketDetailOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:px-4" @keydown.escape.window="closeTicketDetail()">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closeTicketDetail()"></div>
        <div class="relative flex max-h-[92vh] w-full flex-col rounded-t-2xl border border-gray-200 bg-white shadow-xl sm:max-w-lg sm:rounded-2xl">
            <div class="border-b border-gray-200 px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400" x-text="ticketDetail?.ticket_number"></p>
                <h3 class="mt-1 text-lg font-semibold tracking-tight text-gray-900" x-text="ticketDetail?.subject"></h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="ticketStatusClass(ticketDetail?.status)" x-text="ticketStatusLabel(ticketDetail?.status)"></span>
                </div>
            </div>
            <div class="overflow-y-auto px-5 py-5">
                <p class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-700" x-text="ticketDetail?.description"></p>
                <div class="mt-6">
                    <h4 class="text-sm font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('ticket_comments_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p x-show="ticketDetailLoading" class="mt-3 text-sm text-gray-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="!ticketDetailLoading && ticketComments.length === 0" x-cloak class="mt-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars(__('ticket_no_comments'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div x-show="!ticketDetailLoading && ticketComments.length > 0" x-cloak class="mt-3 space-y-3">
                        <template x-for="comment in ticketComments" :key="comment.id">
                            <article class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900" x-text="comment.author_name"></span>
                                    <time class="text-xs text-gray-400" x-text="formatDate(comment.created_at)"></time>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700" x-text="comment.body"></p>
                            </article>
                        </template>
                    </div>
                    <form class="mt-4 space-y-3" @submit.prevent="submitTicketComment()">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700"><?= htmlspecialchars(__('ticket_comment_label'), ENT_QUOTES, 'UTF-8') ?></span>
                            <textarea x-model="ticketCommentBody" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-200"></textarea>
                        </label>
                        <p x-show="ticketCommentError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketCommentError"></p>
                        <button type="submit" :disabled="isTicketCommentSubmitting" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-60"><?= htmlspecialchars(__('action_add_ticket_reply'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </div>
            </div>
            <div class="border-t border-gray-200 px-5 py-4">
                <button type="button" @click="closeTicketDetail()" class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>

    <div
        x-show="toastVisible"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-2 opacity-0"
        class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex justify-center px-4"
    >
        <p class="pointer-events-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm" x-text="toastMessage"></p>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
    window.__portalI18n = <?= json_encode($i18n, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
    window.__portalStatusStyles = <?= json_encode($statusStyles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;

    function endUserPortal() {
        return {
            activeTab: 'assets',
            assets: [],
            assetsLoading: false,
            assetsError: '',
            tickets: [],
            ticketsLoading: false,
            ticketsError: '',
            toastMessage: '',
            toastVisible: false,
            toastTimer: null,
            isTicketModalOpen: false,
            isTicketSubmitting: false,
            ticketLinkedAsset: null,
            ticketForm: { subject: '', description: '', priority: 'medium' },
            ticketFormError: '',
            isTicketDetailOpen: false,
            ticketDetailLoading: false,
            ticketDetail: null,
            ticketComments: [],
            ticketCommentBody: '',
            ticketCommentError: '',
            isTicketCommentSubmitting: false,
            init() {
                this.fetchAssets();
                const params = new URLSearchParams(window.location.search);
                const ticketId = params.get('ticket');

                if (ticketId) {
                    this.activeTab = 'tickets';
                    this.fetchTickets().then(() => this.maybeOpenTicketFromUrl(ticketId));
                }
            },
            switchToTickets() {
                this.activeTab = 'tickets';
                this.fetchTickets();
            },
            goToTicketsView() {
                this.switchToTickets();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            async fetchAssets() {
                this.assetsLoading = true;
                this.assetsError = '';
                try {
                    const response = await fetch('/api/my/assets', { headers: { Accept: 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) {
                        this.assetsError = result.message || window.__portalI18n.portal_assets_error;
                        this.assets = [];
                        return;
                    }
                    this.assets = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.assetsError = window.__portalI18n.portal_assets_error;
                    this.assets = [];
                } finally {
                    this.assetsLoading = false;
                }
            },
            async fetchTickets() {
                this.ticketsLoading = true;
                this.ticketsError = '';
                try {
                    const response = await fetch('/api/tickets', { headers: { Accept: 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) {
                        this.ticketsError = result.message || window.__portalI18n.portal_tickets_error;
                        this.tickets = [];
                        return;
                    }
                    this.tickets = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.ticketsError = window.__portalI18n.portal_tickets_error;
                    this.tickets = [];
                } finally {
                    this.ticketsLoading = false;
                }
            },
            maybeOpenTicketFromUrl(ticketId) {
                if (!ticketId) {
                    return;
                }

                const ticket = this.tickets.find((item) => String(item.id) === String(ticketId));

                if (ticket) {
                    this.openTicketDetail(ticket);
                    return;
                }

                this.openTicketDetail({ id: Number(ticketId) });
            },
            statusClass(status) {
                return window.__portalStatusStyles[status] || 'bg-gray-100 text-gray-700 ring-gray-500/20';
            },
            statusLabel(status) {
                const map = {
                    ready: window.__portalI18n.status_ready,
                    deployed: window.__portalI18n.status_deployed,
                    storage: window.__portalI18n.status_storage,
                    broken: window.__portalI18n.status_broken,
                    under_repair: window.__portalI18n.status_under_repair,
                };
                return map[status] || status || '—';
            },
            ticketStatusClass(status) {
                const classes = {
                    open: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    in_progress: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    closed: 'bg-gray-100 text-gray-700 ring-gray-500/20',
                };
                return classes[status] || 'bg-gray-100 text-gray-700 ring-gray-500/20';
            },
            ticketStatusLabel(status) {
                const map = {
                    open: window.__portalI18n.ticket_status_open,
                    in_progress: window.__portalI18n.ticket_status_in_progress,
                    resolved: window.__portalI18n.ticket_status_resolved,
                    closed: window.__portalI18n.ticket_status_closed,
                };
                return map[status] || status;
            },
            formatDate(value) {
                if (!value) return '—';
                const date = new Date(String(value).replace(' ', 'T'));
                return Number.isNaN(date.getTime()) ? value : date.toLocaleString(window.__portalI18n.locale || 'tr');
            },
            printTutanak(assetId) {
                window.open(`/api/assets/${assetId}/tutanak`, '_blank', 'noopener,noreferrer');
            },
            openTicketModal() {
                this.ticketLinkedAsset = null;
                this.ticketForm = { subject: '', description: '', priority: 'medium' };
                this.ticketFormError = '';
                this.isTicketModalOpen = true;
            },
            openTicketModalForAsset(asset) {
                this.ticketLinkedAsset = {
                    id: asset.id,
                    name: asset.name || '',
                    asset_tag: asset.asset_tag || '',
                };
                this.ticketForm = { subject: '', description: '', priority: 'medium' };
                this.ticketFormError = '';
                this.isTicketModalOpen = true;
            },
            ticketLinkedAssetMessage() {
                if (!this.ticketLinkedAsset) {
                    return '';
                }

                return window.__portalI18n.portal_ticket_for_asset
                    .replace(':name', this.ticketLinkedAsset.name || '—')
                    .replace(':tag', this.ticketLinkedAsset.asset_tag || '—');
            },
            closeTicketModal() {
                if (this.isTicketSubmitting) return;
                this.isTicketModalOpen = false;
                this.ticketLinkedAsset = null;
            },
            showToast(message) {
                this.toastMessage = message;
                this.toastVisible = true;
                if (this.toastTimer) {
                    clearTimeout(this.toastTimer);
                }
                this.toastTimer = setTimeout(() => {
                    this.toastVisible = false;
                    this.toastMessage = '';
                    this.toastTimer = null;
                }, 4000);
            },
            async submitTicket() {
                this.isTicketSubmitting = true;
                this.ticketFormError = '';
                const payload = {
                    subject: this.ticketForm.subject.trim(),
                    description: this.ticketForm.description.trim(),
                    priority: this.ticketForm.priority,
                };
                if (this.ticketLinkedAsset?.id) {
                    payload.asset_id = Number(this.ticketLinkedAsset.id);
                }
                try {
                    const response = await fetch('/api/tickets', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        this.ticketFormError = result.message || window.__portalI18n.ticket_create_error;
                        return;
                    }
                    this.isTicketModalOpen = false;
                    this.ticketLinkedAsset = null;
                    this.ticketForm = { subject: '', description: '', priority: 'medium' };
                    this.activeTab = 'tickets';
                    if (result.data) {
                        this.tickets = [result.data, ...this.tickets.filter((ticket) => ticket.id !== result.data.id)];
                    }
                    this.showToast(result.message || window.__portalI18n.ticket_create_success);
                    await this.fetchTickets();
                } catch (error) {
                    this.ticketFormError = window.__portalI18n.ticket_create_error;
                } finally {
                    this.isTicketSubmitting = false;
                }
            },
            async openTicketDetail(ticket) {
                this.isTicketDetailOpen = true;
                this.ticketDetailLoading = true;
                this.ticketDetail = ticket;
                this.ticketComments = [];
                this.ticketCommentBody = '';
                this.ticketCommentError = '';
                try {
                    const response = await fetch(`/api/tickets/${ticket.id}`, { headers: { Accept: 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) {
                        this.ticketsError = result.message || window.__portalI18n.ticket_not_found;
                        return;
                    }
                    this.ticketDetail = result.data;
                    this.ticketComments = Array.isArray(result.data.comments) ? result.data.comments : [];
                } catch (error) {
                    this.ticketsError = window.__portalI18n.portal_tickets_error;
                } finally {
                    this.ticketDetailLoading = false;
                }
            },
            closeTicketDetail() {
                if (this.isTicketCommentSubmitting) return;
                this.isTicketDetailOpen = false;
                this.ticketDetail = null;
            },
            async submitTicketComment() {
                if (!this.ticketDetail?.id) return;
                const body = this.ticketCommentBody.trim();
                if (body === '') {
                    this.ticketCommentError = window.__portalI18n.ticket_comment_create_error;
                    return;
                }
                this.isTicketCommentSubmitting = true;
                this.ticketCommentError = '';
                try {
                    const response = await fetch(`/api/tickets/${this.ticketDetail.id}/comments`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({ body }),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        this.ticketCommentError = result.message || window.__portalI18n.ticket_comment_create_error;
                        return;
                    }
                    this.ticketCommentBody = '';
                    this.ticketComments.push(result.data);
                } catch (error) {
                    this.ticketCommentError = window.__portalI18n.ticket_comment_create_error;
                } finally {
                    this.isTicketCommentSubmitting = false;
                }
            },
        };
    }
</script>
