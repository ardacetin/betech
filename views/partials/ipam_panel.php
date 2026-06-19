<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'ipam'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900" x-text="ipamSubView === 'addresses' ? (selectedIpNetwork?.name || '') : '<?= htmlspecialchars(__('ipam_page_title'), ENT_QUOTES, 'UTF-8') ?>'"></h2>
            <p class="mt-1 text-sm text-zinc-500" x-show="ipamSubView === 'networks'"><?= htmlspecialchars(__('ipam_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-sm text-zinc-500" x-show="ipamSubView === 'addresses'" x-cloak>
                <span x-text="selectedIpNetwork?.cidr_notation || ''"></span>
                <span x-show="selectedIpNetwork?.gateway" x-cloak> · <?= htmlspecialchars(__('ipam_gateway'), ENT_QUOTES, 'UTF-8') ?>: <span x-text="selectedIpNetwork?.gateway"></span></span>
                <span x-show="selectedIpNetwork?.vlan_id" x-cloak> · VLAN <span x-text="selectedIpNetwork?.vlan_id"></span></span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                x-show="ipamSubView === 'addresses'"
                x-cloak
                @click="backToIpNetworks()"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50"
            ><?= htmlspecialchars(__('ipam_back_to_networks'), ENT_QUOTES, 'UTF-8') ?></button>
            <button
                type="button"
                @click="openIpamImportModal()"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50"
            ><?= htmlspecialchars(__('ipam_import_excel'), ENT_QUOTES, 'UTF-8') ?></button>
            <button
                type="button"
                x-show="ipamSubView === 'networks'"
                @click="openIpNetworkModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('ipam_add_network'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <div x-show="ipamSubView === 'networks'">
        <p x-show="ipNetworksLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
            <?= htmlspecialchars(__('ipam_loading'), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p x-show="ipNetworksError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ipNetworksError"></p>
        <p x-show="ipNetworksSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="ipNetworksSuccessMessage"></p>
        <p
            x-show="!ipNetworksLoading && !ipNetworksError && ipNetworks.length === 0"
            x-cloak
            class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
        ><?= htmlspecialchars(__('ipam_networks_empty'), ENT_QUOTES, 'UTF-8') ?></p>

        <div x-show="!ipNetworksLoading && ipNetworks.length > 0" x-cloak class="grid gap-4 lg:grid-cols-2">
            <template x-for="network in ipNetworks" :key="network.id">
                <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft transition hover:border-zinc-300">
                    <div class="flex items-start justify-between gap-3">
                        <button type="button" @click="openIpNetworkAddresses(network)" class="text-left">
                            <h3 class="text-base font-semibold text-zinc-900" x-text="network.name"></h3>
                            <p class="mt-1 font-mono text-sm text-zinc-600" x-text="network.cidr_notation"></p>
                        </button>
                        <div class="flex gap-1">
                            <button type="button" @click="openIpNetworkModal(network)" class="rounded-lg px-2 py-1 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"><?= htmlspecialchars(__('action_edit'), ENT_QUOTES, 'UTF-8') ?></button>
                            <button type="button" @click="deleteIpNetwork(network)" class="rounded-lg px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"><?= htmlspecialchars(__('action_delete_location'), ENT_QUOTES, 'UTF-8') ?></button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500" x-show="network.description" x-text="network.description"></p>
                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs text-zinc-500">
                            <span x-text="formatIpUtilization(network)"></span>
                            <span x-text="network.utilization_percent + '%'"></span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="network.utilization_percent >= 90 ? 'bg-rose-500' : (network.utilization_percent >= 70 ? 'bg-amber-500' : 'bg-emerald-500')"
                                :style="'width:' + Math.min(100, network.utilization_percent) + '%'"
                            ></div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-zinc-600" x-show="network.gateway" x-text="'GW: ' + network.gateway"></span>
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-zinc-600" x-show="network.vlan_id" x-text="'VLAN ' + network.vlan_id"></span>
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-zinc-600" x-text="network.total_ips + ' IP'"></span>
                    </div>
                </article>
            </template>
        </div>
    </div>

    <div x-show="ipamSubView === 'addresses'" x-cloak class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <template x-for="filter in ipAddressStatusFilters" :key="filter.value">
                <button
                    type="button"
                    @click="setIpAddressStatusFilter(filter.value)"
                    class="rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition"
                    :class="ipAddressStatusFilter === filter.value ? 'bg-zinc-900 text-white ring-zinc-900' : 'bg-white text-zinc-600 ring-zinc-200 hover:bg-zinc-50'"
                    x-text="filter.label"
                ></button>
            </template>
        </div>

        <p x-show="ipAddressesLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
            <?= htmlspecialchars(__('ipam_addresses_loading'), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p x-show="ipAddressesError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ipAddressesError"></p>
        <p
            x-show="!ipAddressesLoading && !ipAddressesError && filteredIpAddresses.length === 0"
            x-cloak
            class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
        ><?= htmlspecialchars(__('ipam_addresses_empty'), ENT_QUOTES, 'UTF-8') ?></p>

        <div x-show="!ipAddressesLoading && filteredIpAddresses.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('ipam_col_ip'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('ipam_col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('ipam_col_hostname'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('ipam_col_asset'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('ipam_col_mac'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <template x-for="address in filteredIpAddresses" :key="address.id">
                            <tr class="hover:bg-zinc-50/80">
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-zinc-900" x-text="address.ip_address"></td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="ipAddressStatusClass(address.status)" x-text="ipAddressStatusLabel(address.status)"></span>
                                </td>
                                <td class="px-4 py-2 text-zinc-700" x-text="address.hostname || '—'"></td>
                                <td class="px-4 py-2">
                                    <button
                                        type="button"
                                        x-show="address.asset_id"
                                        x-cloak
                                        @click="openAssetFromIpam(address)"
                                        class="font-medium text-sky-700 hover:text-sky-900 hover:underline"
                                        x-text="address.asset_tag || ('#' + address.asset_id)"
                                    ></button>
                                    <span x-show="!address.asset_id" x-cloak class="text-zinc-400">—</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-zinc-600" x-text="address.mac_address || '—'"></td>
                                <td class="px-4 py-2">
                                    <button type="button" @click="openIpAddressModal(address)" class="text-xs font-medium text-zinc-600 hover:text-zinc-900"><?= htmlspecialchars(__('action_edit'), ENT_QUOTES, 'UTF-8') ?></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="!ipAddressesLoading && filteredIpAddresses.length > 0" x-cloak class="grid grid-cols-2 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
            <template x-for="address in filteredIpAddresses" :key="'grid-' + address.id">
                <button
                    type="button"
                    @click="openIpAddressModal(address)"
                    class="rounded-lg border px-2 py-2 text-left text-[11px] font-mono transition hover:ring-2 hover:ring-zinc-300"
                    :class="ipAddressGridClass(address.status)"
                    :title="address.hostname || address.asset_tag || address.ip_address"
                >
                    <span x-text="address.ip_address.split('.').slice(3).join('.') || address.ip_address"></span>
                </button>
            </template>
        </div>
    </div>
</section>

<div
    x-show="isIpNetworkModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center px-4"
    @keydown.escape.window="closeIpNetworkModal()"
>
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeIpNetworkModal()"></div>
    <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-zinc-900" x-text="ipNetworkForm.id ? '<?= htmlspecialchars(__('ipam_edit_network'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('ipam_add_network'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
        </div>
        <form @submit.prevent="submitIpNetworkForm()" class="space-y-4 px-6 py-5">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_network_name'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="ipNetworkForm.name" required class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
            </label>
            <div class="grid gap-4 sm:grid-cols-2" x-show="!ipNetworkForm.id">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_network_address'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="ipNetworkForm.network_address" :required="!ipNetworkForm.id" placeholder="192.168.1.0" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm font-mono">
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_cidr'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="number" min="8" max="32" x-model="ipNetworkForm.cidr" :required="!ipNetworkForm.id" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                </label>
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-700" x-show="!ipNetworkForm.id">
                <input type="checkbox" x-model="ipNetworkForm.auto_generate" class="rounded border-zinc-300">
                <?= htmlspecialchars(__('ipam_auto_generate_ips'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_gateway'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="ipNetworkForm.gateway" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm font-mono">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_vlan_id'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="number" min="0" x-model="ipNetworkForm.vlan_id" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_description'), ENT_QUOTES, 'UTF-8') ?></span>
                <textarea x-model="ipNetworkForm.description" rows="2" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm"></textarea>
            </label>
            <p x-show="ipNetworkFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ipNetworkFormError"></p>
            <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4">
                <button type="button" @click="closeIpNetworkModal()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="submit" :disabled="isIpNetworkSubmitting" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"><?= htmlspecialchars(__('category_save'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
    </div>
</div>

<div
    x-show="isIpAddressModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center px-4"
    @keydown.escape.window="closeIpAddressModal()"
>
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeIpAddressModal()"></div>
    <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('ipam_edit_address'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 font-mono text-sm text-zinc-500" x-text="ipAddressForm.ip_address"></p>
        </div>
        <form @submit.prevent="submitIpAddressForm()" class="space-y-4 px-6 py-5">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_col_status'), ENT_QUOTES, 'UTF-8') ?></span>
                <select x-model="ipAddressForm.status" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    <option value="available"><?= htmlspecialchars(__('ipam_status_available'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="reserved"><?= htmlspecialchars(__('ipam_status_reserved'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="assigned"><?= htmlspecialchars(__('ipam_status_assigned'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="dhcp"><?= htmlspecialchars(__('ipam_status_dhcp'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_col_hostname'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="ipAddressForm.hostname" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_col_mac'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="ipAddressForm.mac_address" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm font-mono">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_notes'), ENT_QUOTES, 'UTF-8') ?></span>
                <textarea x-model="ipAddressForm.notes" rows="2" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm"></textarea>
            </label>
            <p x-show="ipAddressFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ipAddressFormError"></p>
            <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4">
                <button type="button" @click="closeIpAddressModal()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="submit" :disabled="isIpAddressSubmitting" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"><?= htmlspecialchars(__('category_save'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
    </div>
</div>

<div
    x-show="isIpamImportOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center px-4"
    @keydown.escape.window="closeIpamImportModal()"
>
    <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeIpamImportModal()"></div>
    <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('ipam_import_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('ipam_import_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="space-y-4 px-6 py-5">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_import_type'), ENT_QUOTES, 'UTF-8') ?></span>
                <select x-model="ipamImportType" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    <option value="networks"><?= htmlspecialchars(__('ipam_import_type_networks'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="addresses"><?= htmlspecialchars(__('ipam_import_type_addresses'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </label>
            <a
                :href="ipamImportType === 'networks' ? '/api/ip-networks/import/template' : '/api/ip-addresses/import/template'"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100"
                download
            ><?= htmlspecialchars(__('import_download_template'), ENT_QUOTES, 'UTF-8') ?></a>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ipam_import_select_file'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="file" accept=".csv,text/csv,.xlsx,.xls" @change="onIpamImportFileSelected($event)" class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white">
            </label>
            <p x-show="ipamImportFileName" x-cloak class="text-xs text-zinc-500" x-text="ipamImportFileName"></p>
            <p x-show="ipamImportErrorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ipamImportErrorMessage"></p>
            <p x-show="ipamImportSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="ipamImportSuccessMessage"></p>
            <ul x-show="ipamImportResultErrors.length > 0" x-cloak class="max-h-36 space-y-1 overflow-y-auto rounded-xl border border-rose-100 bg-rose-50/50 px-4 py-3 text-xs text-rose-700">
                <template x-for="(item, index) in ipamImportResultErrors" :key="index">
                    <li x-text="formatImportError(item)"></li>
                </template>
            </ul>
        </div>
        <div class="flex justify-end gap-3 border-t border-zinc-200 px-6 py-4">
            <button type="button" @click="closeIpamImportModal()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" @click="submitIpamImport()" :disabled="isIpamImportSubmitting || !ipamImportFile" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"><?= htmlspecialchars(__('import_submit'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>
