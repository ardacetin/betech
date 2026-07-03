<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'switch_ports'" x-cloak class="flex h-full min-h-0 flex-col">
    <div class="mb-4 shrink-0">
        <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('switch_ports_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-12 gap-6 lg:h-[calc(100vh-200px)]">
        <aside class="col-span-12 flex min-h-[280px] flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-3 lg:min-h-0">
            <div class="shrink-0 border-b border-zinc-100 px-3.5 py-2.5">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-700"><?= htmlspecialchars(__('switch_ports_directory_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-0.5 text-[11px] leading-tight text-zinc-500"><?= htmlspecialchars(__('switch_ports_directory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div
                x-show="switchPortsSwitches.length === 0"
                x-cloak
                class="flex flex-1 items-center justify-center p-4"
            >
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center text-xs leading-relaxed text-amber-900">
                    <?= htmlspecialchars(__('switch_ports_empty_registry'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <ul x-show="switchPortsSwitches.length > 0" x-cloak class="min-h-0 flex-1 divide-y divide-zinc-100 overflow-y-auto">
                <template x-for="sw in switchPortsSwitches" :key="sw.id">
                    <li>
                        <button
                            type="button"
                            @click="selectSwitchPort(sw.id)"
                            class="w-full px-3.5 py-2.5 text-left transition"
                            :class="switchPortsSelectedId === sw.id ? 'bg-zinc-900 text-white' : 'hover:bg-zinc-50'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-[13px] font-semibold leading-tight" :class="switchPortsSelectedId === sw.id ? 'text-white' : 'text-zinc-900'" x-text="sw.name || sw.asset_tag"></p>
                                    <p class="mt-0.5 truncate text-[11px] leading-tight" :class="switchPortsSelectedId === sw.id ? 'text-zinc-300' : 'text-zinc-500'">
                                        <span x-text="sw.location || '—'"></span>
                                        <template x-if="sw.building"><span> · <span x-text="sw.building"></span></span></template>
                                    </p>
                                </div>
                                <span class="shrink-0 font-mono text-[10px]" :class="switchPortsSelectedId === sw.id ? 'text-zinc-300' : 'text-zinc-400'" x-text="sw.asset_tag"></span>
                            </div>
                            <div class="mt-2">
                                <div class="h-1 overflow-hidden rounded-full" :class="switchPortsSelectedId === sw.id ? 'bg-white/20' : 'bg-zinc-100'">
                                    <div
                                        class="h-full rounded-full"
                                        :class="switchPortsSelectedId === sw.id ? 'bg-sky-300' : (sw.utilization_percent >= 90 ? 'bg-rose-500' : sw.utilization_percent >= 70 ? 'bg-amber-500' : 'bg-sky-500')"
                                        :style="`width: ${Math.min(100, sw.utilization_percent || 0)}%`"
                                    ></div>
                                </div>
                                <p class="mt-1 text-[10px] leading-none" :class="switchPortsSelectedId === sw.id ? 'text-zinc-300' : 'text-zinc-500'" x-text="formatSwitchPortUtilization(sw)"></p>
                            </div>
                        </button>
                    </li>
                </template>
            </ul>
        </aside>

        <div class="col-span-12 flex min-h-[360px] min-w-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-9 lg:min-h-0">
            <div x-show="!switchPortsSelectedId" x-cloak class="flex flex-1 flex-col items-center justify-center px-6 text-center">
                <div class="max-w-md rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-10">
                    <p class="text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_select_prompt'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div x-show="switchPortsSelectedId" x-cloak class="flex min-h-0 flex-1 flex-col p-4 lg:p-5">
                <div class="mb-4 flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-zinc-100 pb-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-zinc-900" x-text="switchPortsMatrix?.switch?.name || '—'"></h3>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            <span x-text="switchPortsMatrix?.switch?.location || '—'"></span>
                            <template x-if="switchPortsMatrix?.switch?.building"><span> · <span x-text="switchPortsMatrix.switch.building"></span></span></template>
                        </p>
                        <p class="mt-0.5 font-mono text-[11px] text-zinc-400" x-text="switchPortsMatrix?.switch?.asset_tag || ''"></p>
                    </div>
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-xs font-medium text-zinc-700" x-text="formatSwitchPortUtilization({ used_ports: switchPortsMatrix?.switch?.used_ports ?? 0, total_ports: switchPortsMatrix?.switch?.total_ports ?? 0 })"></div>
                </div>

                <p x-show="switchPortsMatrixLoading" x-cloak class="text-xs text-zinc-500"><?= htmlspecialchars(__('switch_ports_matrix_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                <p x-show="switchPortsMatrixError" x-cloak class="text-xs text-rose-600" x-text="switchPortsMatrixError"></p>

                <div x-show="!switchPortsMatrixLoading && switchPortsMatrix" x-cloak class="flex min-h-0 flex-1 flex-col gap-3 overflow-auto">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50/80 p-3">
                        <div class="inline-flex min-w-full flex-col gap-2.5">
                            <div class="grid gap-1.5" :style="switchPortsGridStyle()">
                                <template x-for="port in switchPortsTopRow()" :key="'t-' + port.port_number">
                                    <a
                                        :href="switchPortConfigUrl(port.port_number)"
                                        class="group relative flex h-10 items-center justify-center rounded border text-xs font-semibold transition hover:shadow-sm"
                                        :class="port.occupied ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-50 text-slate-400'"
                                    >
                                        <span x-text="port.port_number"></span>
                                        <div
                                            x-show="port.occupied && port.mapping"
                                            x-cloak
                                            class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 hidden w-48 -translate-x-1/2 rounded-md border border-zinc-200 bg-white p-2 text-left text-[10px] font-normal text-zinc-700 shadow-lg group-hover:block"
                                        >
                                            <p class="font-semibold text-zinc-900" x-text="port.mapping?.asset_name || '—'"></p>
                                            <p class="mt-0.5"><span class="text-zinc-500"><?= htmlspecialchars(__('switch_port_asset_tag'), ENT_QUOTES, 'UTF-8') ?>:</span> <span x-text="port.mapping?.asset_tag || '—'"></span></p>
                                            <p x-show="port.mapping?.ip_address" x-cloak class="mt-0.5"><span class="text-zinc-500"><?= htmlspecialchars(__('switch_port_ip_address'), ENT_QUOTES, 'UTF-8') ?>:</span> <span x-text="port.mapping?.ip_address"></span></p>
                                            <p x-show="port.mapping?.assigned_to" x-cloak class="mt-0.5"><span class="text-zinc-500"><?= htmlspecialchars(__('switch_port_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span> <span x-text="port.mapping?.assigned_to"></span></p>
                                        </div>
                                    </a>
                                </template>
                            </div>
                            <div class="grid gap-1.5" :style="switchPortsGridStyle()">
                                <template x-for="port in switchPortsBottomRow()" :key="'b-' + port.port_number">
                                    <a
                                        :href="switchPortConfigUrl(port.port_number)"
                                        class="group relative flex h-10 items-center justify-center rounded border text-xs font-semibold transition hover:shadow-sm"
                                        :class="port.occupied ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-50 text-slate-400'"
                                    >
                                        <span x-text="port.port_number"></span>
                                        <div
                                            x-show="port.occupied && port.mapping"
                                            x-cloak
                                            class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 hidden w-48 -translate-x-1/2 rounded-md border border-zinc-200 bg-white p-2 text-left text-[10px] font-normal text-zinc-700 shadow-lg group-hover:block"
                                        >
                                            <p class="font-semibold text-zinc-900" x-text="port.mapping?.asset_name || '—'"></p>
                                            <p class="mt-0.5"><span class="text-zinc-500"><?= htmlspecialchars(__('switch_port_asset_tag'), ENT_QUOTES, 'UTF-8') ?>:</span> <span x-text="port.mapping?.asset_tag || '—'"></span></p>
                                            <p x-show="port.mapping?.ip_address" x-cloak class="mt-0.5"><span class="text-zinc-500"><?= htmlspecialchars(__('switch_port_ip_address'), ENT_QUOTES, 'UTF-8') ?>:</span> <span x-text="port.mapping?.ip_address"></span></p>
                                            <p x-show="port.mapping?.assigned_to" x-cloak class="mt-0.5"><span class="text-zinc-500"><?= htmlspecialchars(__('switch_port_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span> <span x-text="port.mapping?.assigned_to"></span></p>
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
</section>
