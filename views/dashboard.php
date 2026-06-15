<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $environment
 * @var list<array<string, mixed>> $assets
 * @var array{total: int, deployed: int, in_storage: int, broken: int} $metrics
 * @var list<array<string, mixed>> $categories
 */

$statusStyles = [
    'ready' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'deployed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'storage' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'broken' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
];

$formatPropertyValue = static function (mixed $value): string {
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
};
?>
<div class="min-h-full" x-data="assetDashboard()">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 border-r border-zinc-200 bg-white lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white">B</div>
                <div>
                    <p class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-zinc-500">IT Asset Management</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 p-4">
                <a href="/" class="flex items-center gap-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-900">
                    <span class="h-2 w-2 rounded-full bg-zinc-900"></span>
                    Assets
                </a>
                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-400">
                    <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                    Categories
                </span>
                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-400">
                    <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                    Settings
                </span>
            </nav>

            <div class="border-t border-zinc-200 p-4">
                <p class="text-xs uppercase tracking-wide text-zinc-400">Environment</p>
                <p class="mt-1 text-sm font-medium text-zinc-700"><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </aside>

        <main class="flex-1">
            <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="mt-1 text-sm text-zinc-500">Monitor inventory, status, and hybrid asset properties.</p>
                    </div>
                    <button
                        type="button"
                        @click="openModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                    >
                        <span class="text-lg leading-none">+</span>
                        Add Asset
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-6 py-8">
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <?php
                    $metricCards = [
                        ['label' => 'Total Assets', 'value' => $metrics['total'], 'hint' => 'All registered items'],
                        ['label' => 'Deployed', 'value' => $metrics['deployed'], 'hint' => 'Currently in use'],
                        ['label' => 'In Storage', 'value' => $metrics['in_storage'], 'hint' => 'Ready or stored'],
                        ['label' => 'Broken', 'value' => $metrics['broken'], 'hint' => 'Needs attention'],
                    ];
                    foreach ($metricCards as $card):
                    ?>
                    <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft">
                        <p class="text-sm font-medium text-zinc-500"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900"><?= (int) $card['value'] ?></p>
                        <p class="mt-2 text-xs text-zinc-400"><?= htmlspecialchars($card['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    </article>
                    <?php endforeach; ?>
                </section>

                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
                    <div class="border-b border-zinc-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-zinc-900">Asset Inventory</h2>
                        <p class="mt-1 text-sm text-zinc-500">Core fields with dynamic JSON properties rendered as pills.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200">
                            <thead class="bg-zinc-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Asset Tag</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Properties</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 bg-white">
                                <?php if ($assets === []): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-zinc-500">
                                        No assets yet. Use <span class="font-medium text-zinc-700">Add Asset</span> to create your first record.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($assets as $asset):
                                        $status = (string) ($asset['status'] ?? 'ready');
                                        $statusClass = $statusStyles[$status] ?? 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
                                        $properties = is_array($asset['properties'] ?? null) ? $asset['properties'] : [];
                                    ?>
                                    <tr class="hover:bg-zinc-50/80">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900">
                                            <?= htmlspecialchars((string) $asset['asset_tag'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-700">
                                            <?= htmlspecialchars((string) $asset['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-600">
                                            <?= htmlspecialchars((string) ($asset['category_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset <?= $statusClass ?>">
                                                <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex max-w-xl flex-wrap gap-2">
                                                <?php if ($properties === []): ?>
                                                    <span class="text-xs text-zinc-400">No dynamic properties</span>
                                                <?php else: ?>
                                                    <?php foreach ($properties as $key => $value): ?>
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-xs text-zinc-700">
                                                        <span class="font-medium"><?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>:</span>
                                                        <span><?= htmlspecialchars($formatPropertyValue($value), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div
        x-show="isOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900">Add Asset</h3>
                    <p class="mt-1 text-sm text-zinc-500">Core fields map to columns. Extra fields become JSON properties.</p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700">Asset Tag *</span>
                        <input x-model="form.asset_tag" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700">Serial Number</span>
                        <input x-model="form.serial_number" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700">Name *</span>
                        <input x-model="form.name" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700">Category *</span>
                        <select x-model="form.category_id" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>">
                                <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700">Status</span>
                        <select x-model="form.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="ready">Ready</option>
                            <option value="deployed">Deployed</option>
                            <option value="storage">Storage</option>
                            <option value="broken">Broken</option>
                        </select>
                    </label>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900">Dynamic Properties</h4>
                    <p class="mt-1 text-xs text-zinc-500">These values are stored in the hybrid JSON properties column.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700">RAM</span>
                            <input x-model="form.ram" type="text" placeholder="16GB" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700">CPU</span>
                            <input x-model="form.cpu" type="text" placeholder="Intel i7" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700">Storage</span>
                            <input x-model="form.storage" type="text" placeholder="512GB SSD" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700">MAC Address</span>
                            <input x-model="form.mac_address" type="text" placeholder="00:11:22:33:44:55" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700">IP Address</span>
                            <input x-model="form.ip_address" type="text" placeholder="192.168.1.10" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        </label>
                    </div>
                </div>

                <div x-show="errorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="errorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100">Cancel</button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting">Saving...</span>
                        <span x-show="!isSubmitting">Create Asset</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
    function assetDashboard() {
        return {
            isOpen: false,
            isSubmitting: false,
            errorMessage: '',
            form: {
                asset_tag: '',
                serial_number: '',
                name: '',
                category_id: '',
                status: 'ready',
                ram: '',
                cpu: '',
                storage: '',
                mac_address: '',
                ip_address: '',
            },
            openModal() {
                this.errorMessage = '';
                this.isOpen = true;
            },
            closeModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isOpen = false;
            },
            buildPayload() {
                const payload = {
                    asset_tag: this.form.asset_tag.trim(),
                    name: this.form.name.trim(),
                    category_id: Number(this.form.category_id),
                    status: this.form.status,
                };

                if (this.form.serial_number.trim() !== '') {
                    payload.serial_number = this.form.serial_number.trim();
                }

                ['ram', 'cpu', 'storage', 'mac_address', 'ip_address'].forEach((field) => {
                    if (this.form[field].trim() !== '') {
                        payload[field] = this.form[field].trim();
                    }
                });

                return payload;
            },
            async submitForm() {
                this.isSubmitting = true;
                this.errorMessage = '';

                try {
                    const response = await fetch('/api/assets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.errorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.errorMessage = result.message || 'Unable to create asset.';
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.errorMessage = 'Network error while creating the asset.';
                } finally {
                    this.isSubmitting = false;
                }
            },
        };
    }
</script>
