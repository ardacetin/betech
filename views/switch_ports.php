<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var list<array<string, mixed>> $switches
 * @var string $switchesJson
 * @var int $selectedSwitchId
 * @var string $matrixJson
 */
?>
<div class="min-h-screen bg-gray-50" x-data="switchPortsPage()" x-init="init()">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-6 py-4">
            <a href="/" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600" aria-label="<?= htmlspecialchars(__('switch_ports_back'), ENT_QUOTES, 'UTF-8') ?>">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold text-zinc-900"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="mt-0.5 text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-6">
        <div class="grid min-h-0 gap-6 lg:h-[calc(100vh-160px)] lg:grid-cols-12">
            <aside class="flex min-h-[280px] flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-3 lg:min-h-0">
                <div class="shrink-0 border-b border-zinc-100 px-3.5 py-2.5">
                    <h2 class="text-xs font-bold uppercase tracking-wide text-zinc-700"><?= htmlspecialchars(__('switch_ports_directory_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-0.5 text-[11px] leading-tight text-zinc-500"><?= htmlspecialchars(__('switch_ports_directory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <div x-show="switches.length === 0" x-cloak class="flex flex-1 items-center justify-center p-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center text-xs leading-relaxed text-amber-900">
                        <?= htmlspecialchars(__('switch_ports_empty_registry'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>

                <ul x-show="switches.length > 0" x-cloak class="min-h-0 flex-1 divide-y divide-zinc-100 overflow-y-auto">
                    <template x-for="sw in switches" :key="sw.id">
                        <li>
                            <button
                                type="button"
                                @click="selectSwitch(sw.id)"
                                class="w-full px-3.5 py-2.5 text-left transition"
                                :class="selectedId === sw.id ? 'bg-zinc-900 text-white' : 'hover:bg-zinc-50'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-semibold leading-tight" :class="selectedId === sw.id ? 'text-white' : 'text-zinc-900'" x-text="sw.name || sw.asset_tag"></p>
                                        <p class="mt-0.5 truncate text-[11px] leading-tight" :class="selectedId === sw.id ? 'text-zinc-300' : 'text-zinc-500'">
                                            <span x-text="sw.location || '—'"></span>
                                            <template x-if="sw.building"><span> · <span x-text="sw.building"></span></span></template>
                                        </p>
                                    </div>
                                    <span class="shrink-0 font-mono text-[10px]" :class="selectedId === sw.id ? 'text-zinc-300' : 'text-zinc-400'" x-text="sw.asset_tag"></span>
                                </div>
                                <div class="mt-2">
                                    <div class="h-1 overflow-hidden rounded-full" :class="selectedId === sw.id ? 'bg-white/20' : 'bg-zinc-100'">
                                        <div
                                            class="h-full rounded-full"
                                            :class="selectedId === sw.id ? 'bg-sky-300' : (sw.utilization_percent >= 90 ? 'bg-rose-500' : sw.utilization_percent >= 70 ? 'bg-amber-500' : 'bg-sky-500')"
                                            :style="`width: ${Math.min(100, sw.utilization_percent || 0)}%`"
                                        ></div>
                                    </div>
                                    <p class="mt-1 text-[10px] leading-none" :class="selectedId === sw.id ? 'text-zinc-300' : 'text-zinc-500'" x-text="formatUtilization(sw)"></p>
                                </div>
                            </button>
                        </li>
                    </template>
                </ul>
            </aside>

            <div class="flex min-h-[360px] min-w-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-9 lg:min-h-0">
                <div x-show="!selectedId" x-cloak class="flex flex-1 flex-col items-center justify-center px-6 text-center">
                    <div class="max-w-md rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-10">
                        <p class="text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_select_prompt'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <div x-show="selectedId" x-cloak class="flex min-h-0 flex-1 flex-col p-4 lg:p-5">
                    <div class="mb-4 flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-zinc-100 pb-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-zinc-900" x-text="matrix?.switch?.name || '—'"></h3>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                <span x-text="matrix?.switch?.location || '—'"></span>
                                <template x-if="matrix?.switch?.building"><span> · <span x-text="matrix.switch.building"></span></span></template>
                            </p>
                            <p class="mt-0.5 font-mono text-[11px] text-zinc-400" x-text="matrix?.switch?.asset_tag || ''"></p>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-xs font-medium text-zinc-700" x-text="formatUtilization({ used_ports: matrix?.switch?.used_ports ?? 0, total_ports: matrix?.switch?.total_ports ?? 0 })"></div>
                    </div>

                    <p x-show="matrixLoading" x-cloak class="text-xs text-zinc-500"><?= htmlspecialchars(__('switch_ports_matrix_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="matrixError" x-cloak class="text-xs text-rose-600" x-text="matrixError"></p>

                    <div x-show="!matrixLoading && matrix" x-cloak class="flex min-h-0 flex-1 flex-col gap-3 overflow-auto">
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50/80 p-3">
                            <div class="inline-flex min-w-full flex-col gap-2.5">
                                <div class="grid gap-1.5" :style="gridStyle()">
                                    <template x-for="port in topRow()" :key="'t-' + port.port_number">
                                        <a
                                            :href="portConfigUrl(port.port_number)"
                                            class="group relative flex h-10 items-center justify-center rounded border text-xs font-semibold transition hover:shadow-sm"
                                            :class="port.occupied ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-50 text-slate-400'"
                                        >
                                            <span x-text="port.port_number"></span>
                                            <div
                                                x-show="port.occupied && port.mapping"
                                                x-cloak
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 hidden w-52 -translate-x-1/2 rounded-md border border-zinc-200 bg-white p-2 text-left text-[10px] font-normal text-zinc-700 shadow-lg group-hover:block"
                                            >
                                                <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('switch_port_description_label'), ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="mt-1 whitespace-pre-wrap font-medium text-zinc-900" x-text="port.mapping?.description || port.mapping?.asset_name || '—'"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                                <div class="grid gap-1.5" :style="gridStyle()">
                                    <template x-for="port in bottomRow()" :key="'b-' + port.port_number">
                                        <a
                                            :href="portConfigUrl(port.port_number)"
                                            class="group relative flex h-10 items-center justify-center rounded border text-xs font-semibold transition hover:shadow-sm"
                                            :class="port.occupied ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-50 text-slate-400'"
                                        >
                                            <span x-text="port.port_number"></span>
                                            <div
                                                x-show="port.occupied && port.mapping"
                                                x-cloak
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 hidden w-52 -translate-x-1/2 rounded-md border border-zinc-200 bg-white p-2 text-left text-[10px] font-normal text-zinc-700 shadow-lg group-hover:block"
                                            >
                                                <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('switch_port_description_label'), ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="mt-1 whitespace-pre-wrap font-medium text-zinc-900" x-text="port.mapping?.description || port.mapping?.asset_name || '—'"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3 text-[11px] text-zinc-500">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-3.5 w-3.5 rounded border border-slate-200 bg-slate-50"></span>
                                <?= htmlspecialchars(__('switch_ports_legend_available'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-3.5 w-3.5 rounded border border-blue-300 bg-blue-50"></span>
                                <?= htmlspecialchars(__('switch_ports_legend_occupied'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function switchPortsPage() {
    const initialSwitches = <?= $switchesJson ?>;
    const initialSelectedId = <?= $selectedSwitchId > 0 ? $selectedSwitchId : 'null' ?>;
    const initialMatrix = <?= $matrixJson ?>;
    const utilizationLabel = <?= json_encode(__('switch_ports_utilization'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
    const matrixErrorMessage = <?= json_encode(__('switch_ports_matrix_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;

    return {
        switches: initialSwitches,
        selectedId: initialSelectedId,
        matrix: initialMatrix,
        matrixLoading: false,
        matrixError: '',

        init() {
            // initial data loaded from server; nothing to fetch on first render
        },

        formatUtilization(sw) {
            const used = sw?.used_ports ?? 0;
            const total = sw?.total_ports ?? 0;
            return utilizationLabel.replace(':used', used).replace(':total', total);
        },

        gridStyle() {
            const cols = this.matrix?.ports_per_row || 12;
            return `grid-template-columns: repeat(${cols}, minmax(2.25rem, 1fr));`;
        },

        topRow() {
            if (!this.matrix?.ports) {
                return [];
            }
            return this.matrix.ports.slice(0, this.matrix.ports_per_row);
        },

        bottomRow() {
            if (!this.matrix?.ports) {
                return [];
            }
            return this.matrix.ports.slice(this.matrix.ports_per_row);
        },

        portConfigUrl(portNumber) {
            return `/network/port-config?switch_id=${encodeURIComponent(this.selectedId)}&port=${encodeURIComponent(portNumber)}`;
        },

        async selectSwitch(switchId) {
            this.selectedId = switchId;
            this.matrix = null;
            this.matrixLoading = true;
            this.matrixError = '';

            const url = new URL(window.location.href);
            url.searchParams.set('switch_id', String(switchId));
            window.history.replaceState({}, '', url);

            try {
                const response = await fetch(`/api/network/switches/matrix?switch_id=${encodeURIComponent(switchId)}`);
                let payload = null;

                try {
                    payload = await response.json();
                } catch (parseError) {
                    payload = null;
                }

                if (!response.ok || !payload || payload.status !== 'success') {
                    this.matrixError = matrixErrorMessage;
                    return;
                }

                this.matrix = payload.data;
            } catch (error) {
                this.matrixError = matrixErrorMessage;
            } finally {
                this.matrixLoading = false;
            }
        },
    };
}
</script>
