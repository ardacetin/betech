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

$i18n = [
    'locale' => $locale ?? 'tr',
    'portal_assets_loading' => __('portal_assets_loading'),
    'portal_assets_empty' => __('portal_assets_empty'),
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
<div class="mx-auto flex min-h-full max-w-3xl flex-col" x-data="endUserPortal()" x-init="init()">
    <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur-sm">
        <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white">B</div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-zinc-900"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="truncate text-xs text-zinc-500"><?= htmlspecialchars($userName !== '' ? $userName : $userEmail, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <a href="/logout" class="shrink-0 rounded-xl border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50">
                <?= htmlspecialchars(__('nav_logout'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </header>

    <main class="flex-1 px-4 py-6 pb-28 sm:px-6">
        <?php if (!$hasPersonnelProfile): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
            <?= htmlspecialchars(__('portal_profile_not_linked'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section x-show="activeTab === 'assets'" x-cloak class="space-y-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('portal_my_assets_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_my_assets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <p x-show="assetsLoading" class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_assets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
            <p x-show="assetsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assetsError"></p>
            <p x-show="!assetsLoading && !assetsError && assets.length === 0" x-cloak class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">
                <?= htmlspecialchars(__('portal_assets_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="!assetsLoading && assets.length > 0" x-cloak class="space-y-3">
                <template x-for="asset in assets" :key="asset.id">
                    <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-soft">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="asset.asset_tag"></p>
                                <h2 class="mt-1 text-base font-semibold text-zinc-900" x-text="asset.name"></h2>
                                <p class="mt-1 text-sm text-zinc-500" x-text="asset.category_name || '—'"></p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(asset.status)" x-text="statusLabel(asset.status)"></span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" @click="printTutanak(asset.id)" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50">
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

        <section x-show="activeTab === 'tickets'" x-cloak class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('portal_my_tickets_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_my_tickets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="openTicketModal()" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-zinc-800 sm:w-auto sm:py-2.5">
                    <span class="text-lg leading-none">+</span>
                    <?= htmlspecialchars(__('portal_open_new_ticket'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <p x-show="ticketsLoading" class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
            <p x-show="ticketsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketsError"></p>
            <p x-show="!ticketsLoading && !ticketsError && tickets.length === 0" x-cloak class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">
                <?= htmlspecialchars(__('portal_tickets_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="!ticketsLoading && tickets.length > 0" x-cloak class="space-y-3">
                <template x-for="ticket in tickets" :key="ticket.id">
                    <button type="button" @click="openTicketDetail(ticket)" class="w-full rounded-2xl border border-zinc-200 bg-white p-4 text-left shadow-soft transition hover:border-zinc-300">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-xs font-medium tabular-nums text-zinc-500" x-text="ticket.ticket_number"></p>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="ticketStatusClass(ticket.status)" x-text="ticketStatusLabel(ticket.status)"></span>
                        </div>
                        <h2 class="mt-2 text-base font-semibold text-zinc-900" x-text="ticket.subject"></h2>
                        <p class="mt-1 line-clamp-2 text-sm text-zinc-500" x-text="ticket.description"></p>
                        <p class="mt-3 text-xs text-zinc-400" x-text="formatDate(ticket.created_at)"></p>
                    </button>
                </template>
            </div>
        </section>
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 backdrop-blur-sm">
        <div class="mx-auto grid max-w-3xl grid-cols-2 gap-1 p-2">
            <button type="button" @click="activeTab = 'assets'" class="rounded-xl px-4 py-3 text-sm font-medium transition" :class="activeTab === 'assets' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-50'">
                <?= htmlspecialchars(__('portal_tab_assets'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" @click="switchToTickets()" class="rounded-xl px-4 py-3 text-sm font-medium transition" :class="activeTab === 'tickets' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-50'">
                <?= htmlspecialchars(__('portal_tab_tickets'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </nav>

    <div x-show="isTicketModalOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:px-4" @keydown.escape.window="closeTicketModal()">
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTicketModal()"></div>
        <div class="relative max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-zinc-200 bg-white shadow-soft sm:max-w-lg sm:rounded-2xl">
            <div class="border-b border-zinc-200 px-5 py-4">
                <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_new_ticket'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-1 text-sm text-zinc-500" x-show="!ticketLinkedAsset"><?= htmlspecialchars(__('portal_new_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div x-show="ticketLinkedAsset" x-cloak class="mx-5 mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-medium text-amber-950" x-text="ticketLinkedAssetMessage()"></p>
            </div>
            <form @submit.prevent="submitTicket()" class="space-y-4 px-5 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="ticketForm.subject" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea x-model="ticketForm.description" rows="4" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"></textarea>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="ticketForm.priority" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
                <p x-show="ticketFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketFormError"></p>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-4">
                    <button type="button" @click="closeTicketModal()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isTicketSubmitting" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60">
                        <span x-show="isTicketSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isTicketSubmitting"><?= htmlspecialchars(__('portal_submit_ticket'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="isTicketDetailOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:px-4" @keydown.escape.window="closeTicketDetail()">
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTicketDetail()"></div>
        <div class="relative flex max-h-[92vh] w-full flex-col rounded-t-2xl border border-zinc-200 bg-white shadow-soft sm:max-w-lg sm:rounded-2xl">
            <div class="border-b border-zinc-200 px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="ticketDetail?.ticket_number"></p>
                <h3 class="mt-1 text-lg font-semibold text-zinc-900" x-text="ticketDetail?.subject"></h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="ticketStatusClass(ticketDetail?.status)" x-text="ticketStatusLabel(ticketDetail?.status)"></span>
                </div>
            </div>
            <div class="overflow-y-auto px-5 py-5">
                <p class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700" x-text="ticketDetail?.description"></p>
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('ticket_comments_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p x-show="ticketDetailLoading" class="mt-3 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="!ticketDetailLoading && ticketComments.length === 0" x-cloak class="mt-3 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"><?= htmlspecialchars(__('ticket_no_comments'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div x-show="!ticketDetailLoading && ticketComments.length > 0" x-cloak class="mt-3 space-y-3">
                        <template x-for="comment in ticketComments" :key="comment.id">
                            <article class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900" x-text="comment.author_name"></span>
                                    <time class="text-xs text-zinc-400" x-text="formatDate(comment.created_at)"></time>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-700" x-text="comment.body"></p>
                            </article>
                        </template>
                    </div>
                    <form class="mt-4 space-y-3" @submit.prevent="submitTicketComment()">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_comment_label'), ENT_QUOTES, 'UTF-8') ?></span>
                            <textarea x-model="ticketCommentBody" rows="3" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"></textarea>
                        </label>
                        <p x-show="ticketCommentError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketCommentError"></p>
                        <button type="submit" :disabled="isTicketCommentSubmitting" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"><?= htmlspecialchars(__('action_add_ticket_reply'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </div>
            </div>
            <div class="border-t border-zinc-200 px-5 py-4">
                <button type="button" @click="closeTicketDetail()" class="w-full rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
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
        <p class="pointer-events-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-soft" x-text="toastMessage"></p>
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
            },
            switchToTickets() {
                this.activeTab = 'tickets';
                this.fetchTickets();
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
            statusClass(status) {
                return window.__portalStatusStyles[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
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
                    closed: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
                };
                return classes[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
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
