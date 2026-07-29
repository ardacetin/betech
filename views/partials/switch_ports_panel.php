<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'switch_ports'" x-cloak class="flex min-h-[calc(100dvh-10rem)] flex-col">
    <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-12 lg:gap-6">
        <aside class="flex max-h-[40vh] min-h-[240px] flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-3 lg:max-h-none lg:min-h-0">
            <div class="shrink-0 border-b border-zinc-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_ports_directory_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-0.5 text-xs text-zinc-500"><?= htmlspecialchars(__('switch_ports_directory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div
                x-show="switchPortsSwitches.length === 0"
                x-cloak
                class="flex flex-1 items-center justify-center p-4"
            >
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm leading-relaxed text-amber-900">
                    <?= htmlspecialchars(__('switch_ports_empty_registry'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <ul x-show="switchPortsSwitches.length > 0" x-cloak class="min-h-0 flex-1 divide-y divide-zinc-100 overflow-y-auto">
                <template x-for="sw in switchPortsSwitches" :key="sw.id">
                    <li>
                        <button
                            type="button"
                            @click="selectSwitchPort(sw.id)"
                            class="w-full px-4 py-3 text-left transition"
                            :class="isSwitchPortSelected(sw) ? 'btn-brand switch-dir-item-active' : 'hover:bg-zinc-50'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold leading-snug" :class="isSwitchPortSelected(sw) ? 'text-white' : 'text-zinc-900'" x-text="sw.name || sw.asset_tag"></p>
                                    <p class="mt-0.5 truncate text-xs leading-snug" :class="isSwitchPortSelected(sw) ? 'text-white' : 'text-zinc-500'" :style="isSwitchPortSelected(sw) ? 'opacity:0.8' : ''">
                                        <span x-text="sw.location || '—'"></span>
                                        <template x-if="sw.building"><span> · <span x-text="sw.building"></span></span></template>
                                    </p>
                                </div>
                                <span class="shrink-0 font-mono text-xs" :class="isSwitchPortSelected(sw) ? 'text-white' : 'text-zinc-400'" :style="isSwitchPortSelected(sw) ? 'opacity:0.75' : ''" x-text="sw.asset_tag"></span>
                            </div>
                            <div class="mt-2">
                                <div class="h-1.5 overflow-hidden rounded-full" :class="isSwitchPortSelected(sw) ? '' : 'bg-zinc-100'" :style="isSwitchPortSelected(sw) ? 'background:rgba(255,255,255,0.2)' : ''">
                                    <div
                                        class="h-full rounded-full"
                                        :class="isSwitchPortSelected(sw) ? 'bg-white' : (sw.utilization_percent >= 90 ? 'bg-rose-500' : sw.utilization_percent >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
                                        :style="`width: ${Math.min(100, sw.utilization_percent || 0)}%`"
                                    ></div>
                                </div>
                                <p class="mt-1 text-xs leading-none" :class="isSwitchPortSelected(sw) ? 'text-white' : 'text-zinc-500'" :style="isSwitchPortSelected(sw) ? 'opacity:0.8' : ''" x-text="formatSwitchPortUtilization(sw)"></p>
                            </div>
                        </button>
                    </li>
                </template>
            </ul>
        </aside>

        <div class="flex min-h-[320px] min-w-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:col-span-9 lg:min-h-0">
            <div x-show="!switchPortsSelectedId" x-cloak class="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center">
                <div class="max-w-md rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-10">
                    <p class="text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_select_prompt'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div x-show="switchPortsSelectedId" x-cloak class="flex min-h-0 flex-1 flex-col">
                <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-zinc-100 px-4 py-3 lg:px-5 lg:py-4">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-zinc-900" x-text="switchPortsMatrix?.switch?.name || '—'"></h3>
                        <p class="mt-0.5 text-sm text-zinc-500">
                            <span x-text="switchPortsMatrix?.switch?.location || '—'"></span>
                            <template x-if="switchPortsMatrix?.switch?.building"><span> · <span x-text="switchPortsMatrix.switch.building"></span></span></template>
                        </p>
                        <p class="mt-0.5 font-mono text-xs text-zinc-400" x-text="switchPortsMatrix?.switch?.asset_tag || ''"></p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm font-medium text-zinc-700" x-text="formatSwitchPortUtilization({ used_ports: switchPortsMatrix?.switch?.used_ports ?? 0, total_ports: switchPortsMatrix?.switch?.total_ports ?? 0 })"></div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 lg:px-5 lg:py-5">
                    <p x-show="switchPortsMatrixLoading" x-cloak class="text-sm text-zinc-500"><?= htmlspecialchars(__('switch_ports_matrix_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="switchPortsMatrixError" x-cloak class="text-sm text-rose-600" x-text="switchPortsMatrixError"></p>

                    <div x-show="!switchPortsMatrixLoading && switchPortsMatrix" x-cloak class="space-y-4">
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-50/80 p-4">
                            <div class="inline-flex min-w-full flex-col gap-3">
                                <div class="grid gap-2" :style="switchPortsGridStyle()">
                                    <template x-for="port in switchPortsTopRow()" :key="'t-' + port.port_number">
                                        <button
                                            type="button"
                                            @click="openSwitchPortConfigModal(port)"
                                            class="group relative flex h-11 min-w-[2.5rem] items-center justify-center rounded-md border text-sm font-semibold transition hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1"
                                            :class="port.occupied
                                                ? 'border-rose-400 bg-rose-500 text-white hover:bg-rose-600 focus-visible:ring-rose-400'
                                                : 'border-emerald-400 bg-emerald-500 text-white hover:bg-emerald-600 focus-visible:ring-emerald-400'"
                                            :title="port.occupied ? (port.mapping?.description || port.mapping?.asset_name || '') : '<?= htmlspecialchars(__('switch_ports_legend_available'), ENT_QUOTES, 'UTF-8') ?>'"
                                        >
                                            <span x-text="port.port_number"></span>
                                            <div
                                                x-show="port.occupied && (port.mapping?.description || port.mapping?.asset_name)"
                                                x-cloak
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-56 -translate-x-1/2 rounded-lg border border-zinc-200 bg-white p-3 text-left text-xs font-normal text-zinc-700 shadow-lg group-hover:block"
                                            >
                                                <p class="text-sm font-semibold text-zinc-900" x-text="port.mapping?.description || port.mapping?.asset_name || '—'"></p>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                                <div class="grid gap-2" :style="switchPortsGridStyle()">
                                    <template x-for="port in switchPortsBottomRow()" :key="'b-' + port.port_number">
                                        <button
                                            type="button"
                                            @click="openSwitchPortConfigModal(port)"
                                            class="group relative flex h-11 min-w-[2.5rem] items-center justify-center rounded-md border text-sm font-semibold transition hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1"
                                            :class="port.occupied
                                                ? 'border-rose-400 bg-rose-500 text-white hover:bg-rose-600 focus-visible:ring-rose-400'
                                                : 'border-emerald-400 bg-emerald-500 text-white hover:bg-emerald-600 focus-visible:ring-emerald-400'"
                                            :title="port.occupied ? (port.mapping?.description || port.mapping?.asset_name || '') : '<?= htmlspecialchars(__('switch_ports_legend_available'), ENT_QUOTES, 'UTF-8') ?>'"
                                        >
                                            <span x-text="port.port_number"></span>
                                            <div
                                                x-show="port.occupied && (port.mapping?.description || port.mapping?.asset_name)"
                                                x-cloak
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-56 -translate-x-1/2 rounded-lg border border-zinc-200 bg-white p-3 text-left text-xs font-normal text-zinc-700 shadow-lg group-hover:block"
                                            >
                                                <p class="text-sm font-semibold text-zinc-900" x-text="port.mapping?.description || port.mapping?.asset_name || '—'"></p>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-zinc-600">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-4 w-4 rounded border border-emerald-400 bg-emerald-500"></span>
                                <?= htmlspecialchars(__('switch_ports_legend_available'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="h-4 w-4 rounded border border-rose-400 bg-rose-500"></span>
                                <?= htmlspecialchars(__('switch_ports_legend_occupied'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<template x-teleport="body">
    <div
        x-show="isSwitchPortConfigModalOpen"
        x-cloak
        class="fixed inset-0 z-[70] flex items-center justify-center px-4"
        @keydown.escape.window="closeSwitchPortConfigModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeSwitchPortConfigModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white shadow-soft" @click.stop>
            <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-6 py-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_port_config_page_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500">
                        <span class="font-medium text-zinc-800" x-text="switchPortConfigForm.switch_name || '—'"></span>
                        <span class="text-zinc-300"> · </span>
                        <?= htmlspecialchars(__('switch_port_config_port'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="font-semibold" style="color:#7a242c;" x-text="switchPortConfigForm.port_number"></span>
                    </p>
                </div>
                <button type="button" @click="closeSwitchPortConfigModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600" aria-label="<?= htmlspecialchars(__('portal_close'), ENT_QUOTES, 'UTF-8') ?>">&times;</button>
            </div>

            <div class="space-y-4 px-6 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('switch_port_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea
                        x-model="switchPortConfigForm.description"
                        rows="6"
                        maxlength="2000"
                        placeholder="<?= htmlspecialchars(__('switch_port_description_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                    ></textarea>
                    <p class="mt-1.5 text-xs text-zinc-500"><?= htmlspecialchars(__('switch_port_description_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </label>

                <p x-show="switchPortConfigError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="switchPortConfigError"></p>
                <p x-show="switchPortConfigSuccess" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="switchPortConfigSuccess"></p>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button
                        type="button"
                        x-show="switchPortConfigForm.has_saved_description"
                        x-cloak
                        @click="clearSwitchPortDescription()"
                        :disabled="switchPortConfigSaving || switchPortConfigClearing"
                        class="rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-60"
                    ><?= htmlspecialchars(__('switch_port_disconnect'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="button"
                        @click="closeSwitchPortConfigModal()"
                        :disabled="switchPortConfigSaving || switchPortConfigClearing"
                        class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-60"
                    ><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="button"
                        @click="saveSwitchPortDescription()"
                        :disabled="switchPortConfigSaving || switchPortConfigClearing"
                        class="btn-brand inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-medium text-white transition disabled:opacity-60"
                    >
                        <span x-show="!switchPortConfigSaving"><?= htmlspecialchars(__('switch_port_description_save'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="switchPortConfigSaving" x-cloak><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
