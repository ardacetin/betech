<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var string $csrfToken
 * @var string $backUrl
 */
?>
<div class="min-h-screen bg-gray-50" x-data="switchPortsPage()" x-init="init()">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-6 py-4">
            <div class="flex min-w-0 items-center gap-4">
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600" aria-label="<?= htmlspecialchars(__('switch_ports_back'), ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_ports_page_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-0.5 text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="hidden text-sm text-zinc-500 sm:block"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </header>

    <main class="mx-auto grid max-w-[1600px] gap-6 px-6 py-8 lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="border-b border-zinc-100 px-4 py-3">
                <h2 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_ports_directory_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mt-0.5 text-xs text-zinc-500"><?= htmlspecialchars(__('switch_ports_directory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <p x-show="directoryLoading" x-cloak class="px-4 py-6 text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_loading'), ENT_QUOTES, 'UTF-8') ?></p>
            <p x-show="directoryError" x-cloak class="px-4 py-3 text-sm text-rose-600" x-text="directoryError"></p>
            <p
                x-show="!directoryLoading && !directoryError && switches.length === 0"
                x-cloak
                class="px-4 py-6 text-sm text-zinc-500"
            ><?= htmlspecialchars(__('network_switch_list_empty'), ENT_QUOTES, 'UTF-8') ?></p>

            <ul x-show="!directoryLoading && switches.length > 0" x-cloak class="max-h-[calc(100vh-220px)] divide-y divide-zinc-100 overflow-y-auto">
                <template x-for="sw in switches" :key="sw.id">
                    <li>
                        <button
                            type="button"
                            @click="selectSwitch(sw.id)"
                            class="w-full px-4 py-3 text-left transition hover:bg-zinc-50"
                            :class="selectedSwitchId === sw.id ? 'bg-zinc-900 text-white hover:bg-zinc-900' : ''"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold" :class="selectedSwitchId === sw.id ? 'text-white' : 'text-zinc-900'" x-text="sw.name || sw.asset_tag"></p>
                                    <p class="mt-0.5 truncate text-xs" :class="selectedSwitchId === sw.id ? 'text-zinc-300' : 'text-zinc-500'">
                                        <span x-text="sw.location || '—'"></span>
                                        <span x-show="sw.building"> · <span x-text="sw.building"></span></span>
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium" :class="selectedSwitchId === sw.id ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-600'" x-text="sw.asset_tag"></span>
                            </div>
                            <div class="mt-2.5">
                                <div class="h-1.5 overflow-hidden rounded-full" :class="selectedSwitchId === sw.id ? 'bg-white/20' : 'bg-zinc-100'">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="selectedSwitchId === sw.id ? 'bg-sky-300' : (sw.utilization_percent >= 90 ? 'bg-rose-500' : sw.utilization_percent >= 70 ? 'bg-amber-500' : 'bg-sky-500')"
                                        :style="`width: ${Math.min(100, sw.utilization_percent)}%`"
                                    ></div>
                                </div>
                                <p class="mt-1 text-[11px]" :class="selectedSwitchId === sw.id ? 'text-zinc-300' : 'text-zinc-500'" x-text="formatUtilization(sw)"></p>
                            </div>
                        </button>
                    </li>
                </template>
            </ul>
        </aside>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <div x-show="!selectedSwitchId" x-cloak class="flex min-h-[420px] flex-col items-center justify-center text-center">
                <p class="text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_select_prompt'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div x-show="selectedSwitchId" x-cloak>
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900" x-text="matrix?.switch?.name || ''"></h2>
                        <p class="mt-1 text-sm text-zinc-500">
                            <span x-text="matrix?.switch?.location || '—'"></span>
                            <span x-show="matrix?.switch?.building"> · <span x-text="matrix?.switch?.building"></span></span>
                        </p>
                        <p class="mt-1 font-mono text-xs text-zinc-400" x-text="matrix?.switch?.asset_tag || ''"></p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2 text-sm text-zinc-700">
                        <span x-text="formatUtilization({ used_ports: matrix?.switch?.used_ports ?? 0, total_ports: matrix?.switch?.total_ports ?? 0 })"></span>
                    </div>
                </div>

                <p x-show="matrixLoading" x-cloak class="text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_matrix_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                <p x-show="matrixError" x-cloak class="text-sm text-rose-600" x-text="matrixError"></p>

                <div x-show="!matrixLoading && matrix" x-cloak class="space-y-4">
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-50/50 p-4">
                        <div class="inline-flex min-w-full flex-col gap-3">
                            <div class="grid gap-2" :style="`grid-template-columns: repeat(${matrix.ports_per_row}, minmax(2.75rem, 1fr));`">
                                <template x-for="port in topRowPorts()" :key="'top-' + port.port_number">
                                    <div x-html="renderPortCell(port)"></div>
                                </template>
                            </div>
                            <div class="grid gap-2" :style="`grid-template-columns: repeat(${matrix.ports_per_row}, minmax(2.75rem, 1fr));`">
                                <template x-for="port in bottomRowPorts()" :key="'bottom-' + port.port_number">
                                    <div x-html="renderPortCell(port)"></div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs text-zinc-500">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-4 w-4 rounded border border-slate-200 bg-slate-50"></span>
                            <?= htmlspecialchars(__('switch_ports_legend_available'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="h-4 w-4 rounded border border-blue-300 bg-blue-50"></span>
                            <?= htmlspecialchars(__('switch_ports_legend_occupied'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
function switchPortsPage() {
    const labels = {
        portFull: <?= json_encode(__('switch_ports_utilization'), JSON_UNESCAPED_UNICODE) ?>,
        mapAsset: <?= json_encode(__('switch_ports_map_asset'), JSON_UNESCAPED_UNICODE) ?>,
        assetName: <?= json_encode(__('switch_port_asset_name'), JSON_UNESCAPED_UNICODE) ?>,
        assetTag: <?= json_encode(__('switch_port_asset_tag'), JSON_UNESCAPED_UNICODE) ?>,
        ipAddress: <?= json_encode(__('switch_port_ip_address'), JSON_UNESCAPED_UNICODE) ?>,
        assignedUser: <?= json_encode(__('switch_port_assigned_user'), JSON_UNESCAPED_UNICODE) ?>,
    };

    return {
        switches: [],
        selectedSwitchId: null,
        matrix: null,
        directoryLoading: false,
        directoryError: '',
        matrixLoading: false,
        matrixError: '',

        async init() {
            const params = new URLSearchParams(window.location.search);
            const preselected = parseInt(params.get('switch_id') || '0', 10);
            await this.fetchDirectory();
            if (preselected > 0) {
                await this.selectSwitch(preselected);
            }
        },

        async fetchDirectory() {
            this.directoryLoading = true;
            this.directoryError = '';
            try {
                const response = await fetch('/api/network/switches/directory');
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') {
                    throw new Error(payload.message || 'Failed');
                }
                this.switches = payload.data || [];
            } catch (error) {
                this.directoryError = error?.message || <?= json_encode(__('switch_ports_fetch_error'), JSON_UNESCAPED_UNICODE) ?>;
            } finally {
                this.directoryLoading = false;
            }
        },

        async selectSwitch(switchId) {
            this.selectedSwitchId = switchId;
            this.matrix = null;
            this.matrixLoading = true;
            this.matrixError = '';
            const url = new URL(window.location.href);
            url.searchParams.set('switch_id', String(switchId));
            window.history.replaceState({}, '', url);
            try {
                const response = await fetch(`/api/network/switches/matrix?switch_id=${encodeURIComponent(switchId)}`);
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') {
                    throw new Error(payload.message || 'Failed');
                }
                this.matrix = payload.data;
            } catch (error) {
                this.matrixError = error?.message || <?= json_encode(__('switch_ports_matrix_error'), JSON_UNESCAPED_UNICODE) ?>;
            } finally {
                this.matrixLoading = false;
            }
        },

        formatUtilization(sw) {
            const used = sw.used_ports ?? 0;
            const total = sw.total_ports ?? 0;
            return labels.portFull.replace(':used', used).replace(':total', total);
        },

        topRowPorts() {
            if (!this.matrix?.ports) return [];
            return this.matrix.ports.slice(0, this.matrix.ports_per_row);
        },

        bottomRowPorts() {
            if (!this.matrix?.ports) return [];
            return this.matrix.ports.slice(this.matrix.ports_per_row);
        },

        portConfigUrl(portNumber) {
            return `/network/port-config?switch_id=${encodeURIComponent(this.selectedSwitchId)}&port=${encodeURIComponent(portNumber)}`;
        },

        renderPortCell(port) {
            const occupied = port.occupied && port.mapping;
            const baseClasses = occupied
                ? 'group relative flex h-11 flex-col items-center justify-center rounded-lg border bg-blue-50 border-blue-300 text-blue-700 font-medium'
                : 'group relative flex h-11 flex-col items-center justify-center rounded-lg border bg-slate-50 border-slate-200 text-slate-400';
            const href = this.portConfigUrl(port.port_number);
            const tooltip = occupied ? `
                <div class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-52 -translate-x-1/2 rounded-lg border border-zinc-200 bg-white p-2.5 text-left text-[11px] font-normal text-zinc-700 shadow-lg group-hover:block">
                    <p class="font-semibold text-zinc-900">${this.escapeHtml(port.mapping.asset_name || '—')}</p>
                    <p class="mt-1"><span class="text-zinc-500">${labels.assetTag}:</span> ${this.escapeHtml(port.mapping.asset_tag || '—')}</p>
                    ${port.mapping.ip_address ? `<p class="mt-0.5"><span class="text-zinc-500">${labels.ipAddress}:</span> ${this.escapeHtml(port.mapping.ip_address)}</p>` : ''}
                    ${port.mapping.assigned_to ? `<p class="mt-0.5"><span class="text-zinc-500">${labels.assignedUser}:</span> ${this.escapeHtml(port.mapping.assigned_to)}</p>` : ''}
                </div>
            ` : '';
            const linkIcon = occupied ? '' : `
                <span class="absolute right-0.5 top-0.5 rounded p-0.5 text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-white hover:text-sky-600" title="${labels.mapAsset}">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                </span>
            `;

            return `<a href="${href}" class="${baseClasses} transition hover:shadow-sm hover:ring-2 hover:ring-sky-200/80">
                ${tooltip}
                <span class="text-xs font-semibold">${port.port_number}</span>
                ${linkIcon}
            </a>`;
        },

        escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },
    };
}
</script>
