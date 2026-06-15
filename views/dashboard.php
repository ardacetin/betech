<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $environment
 * @var string $locale
 * @var list<array<string, mixed>> $assets
 * @var array{total: int, deployed: int, in_storage: int, broken: int} $metrics
 * @var list<array<string, mixed>> $categories
 * @var string $categoryFieldsJson
 */

$statusStyles = [
    'ready' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'deployed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'storage' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'broken' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
];

$translateStatus = static function (string $status): string {
    $key = 'status_' . $status;

    return __($key);
};

$formatPropertyValue = static function (mixed $value): string {
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
};

$metricCards = [
    ['label' => __('metric_total_assets'), 'value' => $metrics['total'], 'hint' => __('metric_total_hint')],
    ['label' => __('metric_deployed'), 'value' => $metrics['deployed'], 'hint' => __('metric_deployed_hint')],
    ['label' => __('metric_in_storage'), 'value' => $metrics['in_storage'], 'hint' => __('metric_in_storage_hint')],
    ['label' => __('metric_broken'), 'value' => $metrics['broken'], 'hint' => __('metric_broken_hint')],
];

$i18nScript = json_encode([
    'create_error' => __('create_error'),
    'update_error' => __('update_error'),
    'network_error' => __('network_error'),
    'locale' => $locale ?? 'tr',
    'no_category_fields' => __('no_category_fields'),
    'no_users_found' => __('no_users_found'),
    'search_users_placeholder' => __('search_users_placeholder'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
?>
<div class="min-h-full" x-data="assetDashboard()">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 border-r border-zinc-200 bg-white lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white">B</div>
                <div>
                    <p class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-zinc-500"><?= htmlspecialchars(__('app_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 p-4">
                <a href="/" class="flex items-center gap-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-900">
                    <span class="h-2 w-2 rounded-full bg-zinc-900"></span>
                    <?= htmlspecialchars(__('nav_assets'), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-400">
                    <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                    <?= htmlspecialchars(__('nav_categories'), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-400">
                    <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                    <?= htmlspecialchars(__('nav_settings'), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </nav>

            <div class="border-t border-zinc-200 p-4">
                <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('environment'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-sm font-medium text-zinc-700"><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </aside>

        <main class="flex-1">
            <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center rounded-xl border border-zinc-200 bg-white p-1 shadow-soft">
                            <span class="sr-only"><?= htmlspecialchars(__('language'), ENT_QUOTES, 'UTF-8') ?></span>
                            <a
                                href="<?= htmlspecialchars(lang_url('tr'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= ($locale ?? 'tr') === 'tr' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100' ?> rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            >TR</a>
                            <a
                                href="<?= htmlspecialchars(lang_url('en'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= ($locale ?? 'tr') === 'en' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100' ?> rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            >EN</a>
                        </div>
                        <button
                            type="button"
                            @click="openAddModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-6 py-8">
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ($metricCards as $card): ?>
                    <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-soft">
                        <p class="text-sm font-medium text-zinc-500"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900"><?= (int) $card['value'] ?></p>
                        <p class="mt-2 text-xs text-zinc-400"><?= htmlspecialchars($card['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                    </article>
                    <?php endforeach; ?>
                </section>

                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
                    <div class="border-b border-zinc-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('inventory_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200">
                            <thead class="bg-zinc-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_properties'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 bg-white">
                                <?php if ($assets === []): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-zinc-500">
                                        <?= htmlspecialchars(__('empty_assets_prefix'), ENT_QUOTES, 'UTF-8') ?>
                                        <span class="font-medium text-zinc-700"><?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?= htmlspecialchars(__('empty_assets_suffix'), ENT_QUOTES, 'UTF-8') ?>
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
                                            <?= htmlspecialchars((string) ($asset['category_name'] ?? __('unknown_category')), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset <?= $statusClass ?>">
                                                <?= htmlspecialchars($translateStatus($status), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-600">
                                            <?php if (!empty($asset['user_name'])): ?>
                                                <?= htmlspecialchars((string) $asset['user_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-zinc-400"><?= htmlspecialchars(__('not_assigned'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex max-w-xl flex-wrap gap-2">
                                                <?php if ($properties === []): ?>
                                                    <span class="text-xs text-zinc-400"><?= htmlspecialchars(__('no_properties'), ENT_QUOTES, 'UTF-8') ?></span>
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
                                        <td class="px-6 py-4">
                                            <button
                                                type="button"
                                                @click='openEditModal(<?= json_encode([
                                                    'id' => (int) $asset['id'],
                                                    'asset_tag' => (string) $asset['asset_tag'],
                                                    'name' => (string) $asset['name'],
                                                    'status' => $status,
                                                    'user_id' => $asset['user_id'] ?? null,
                                                    'user_name' => $asset['user_name'] ?? null,
                                                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>)'
                                                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                            >
                                                <?= htmlspecialchars(__('action_assign'), ENT_QUOTES, 'UTF-8') ?>
                                            </button>
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
        x-show="isAddOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeAddModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeAddModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_add_asset'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeAddModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitAddForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_asset_tag'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.asset_tag" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_serial_number'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.serial_number" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_name'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.name" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_category'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select
                            x-model="form.category_id"
                            @change="loadCategoryFields(form.category_id)"
                            required
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                            <option value=""><?= htmlspecialchars(__('select_category'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>">
                                <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block sm:col-span-1">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="form.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require __DIR__ . '/partials/user_picker.php'; ?>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('technical_specifications'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('technical_specifications_hint'), ENT_QUOTES, 'UTF-8') ?></p>

                    <p
                        x-show="dynamicFields.length === 0"
                        x-cloak
                        class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"
                    >
                        <?= htmlspecialchars(__('no_category_fields'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div x-show="dynamicFields.length > 0" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                        <template x-for="field in dynamicFields" :key="field.name">
                            <label class="block" :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
                                <span class="mb-1.5 block text-sm font-medium text-zinc-700" x-text="resolveFieldLabel(field)"></span>
                                <input
                                    x-show="field.type !== 'textarea'"
                                    :type="field.type === 'number' ? 'number' : 'text'"
                                    x-model="dynamicValues[field.name]"
                                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                                >
                                <textarea
                                    x-show="field.type === 'textarea'"
                                    x-model="dynamicValues[field.name]"
                                    rows="3"
                                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                                ></textarea>
                            </label>
                        </template>
                    </div>
                </div>

                <div x-show="addErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="addErrorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeAddModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isSubmitting"><?= htmlspecialchars(__('create_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isEditOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeEditModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeEditModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_edit_asset'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_edit_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeEditModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitEditForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="editAsset?.asset_tag"></p>
                    <p class="mt-3 text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm text-zinc-700" x-text="editAsset?.name"></p>
                </div>

                <label class="mt-5 block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="editForm.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require __DIR__ . '/partials/user_picker.php'; ?>
                </div>

                <div x-show="editErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="editErrorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeEditModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isSubmitting"><?= htmlspecialchars(__('save_changes'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
    window.__i18n = <?= $i18nScript ?>;
    window.__categoryFields = <?= $categoryFieldsJson ?>;

    function assetDashboard() {
        return {
            isAddOpen: false,
            isEditOpen: false,
            isSubmitting: false,
            addErrorMessage: '',
            editErrorMessage: '',
            categoryFields: window.__categoryFields || {},
            dynamicFields: [],
            dynamicValues: {},
            userSearchQuery: '',
            userSearchResults: [],
            userSearchLoading: false,
            showUserResults: false,
            selectedUser: null,
            editAsset: null,
            editForm: {
                status: 'ready',
            },
            form: {
                asset_tag: '',
                serial_number: '',
                name: '',
                category_id: '',
                status: 'ready',
            },
            openAddModal() {
                this.addErrorMessage = '';
                this.resetDynamicFields();
                this.resetUserSearch();
                this.isAddOpen = true;
            },
            closeAddModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isAddOpen = false;
            },
            openEditModal(asset) {
                this.editErrorMessage = '';
                this.editAsset = asset;
                this.editForm.status = asset.status || 'ready';
                this.resetUserSearch();

                if (asset.user_id) {
                    this.selectedUser = {
                        id: String(asset.user_id),
                        name: asset.user_name || '',
                        email: '',
                        department: null,
                    };
                } else {
                    this.selectedUser = null;
                }

                this.isEditOpen = true;
            },
            closeEditModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isEditOpen = false;
                this.editAsset = null;
            },
            resetDynamicFields() {
                this.dynamicFields = [];
                this.dynamicValues = {};
            },
            resetUserSearch() {
                this.userSearchQuery = '';
                this.userSearchResults = [];
                this.userSearchLoading = false;
                this.showUserResults = false;
                this.selectedUser = null;
            },
            async searchUsers() {
                this.userSearchLoading = true;

                try {
                    const query = encodeURIComponent(this.userSearchQuery.trim());
                    const response = await fetch(`/api/users/search?q=${query}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.userSearchResults = [];
                        return;
                    }

                    this.userSearchResults = Array.isArray(result.data) ? result.data : [];
                    this.showUserResults = true;
                } catch (error) {
                    this.userSearchResults = [];
                } finally {
                    this.userSearchLoading = false;
                }
            },
            selectUser(user) {
                this.selectedUser = user;
                this.userSearchQuery = '';
                this.userSearchResults = [];
                this.showUserResults = false;
            },
            clearSelectedUser() {
                this.selectedUser = null;
            },
            loadCategoryFields(categoryId) {
                const normalizedId = String(categoryId || '');
                this.resetDynamicFields();

                if (normalizedId === '') {
                    return;
                }

                const fields = this.categoryFields[normalizedId] || this.categoryFields[Number(normalizedId)] || [];
                this.dynamicFields = Array.isArray(fields) ? fields : [];

                this.dynamicFields.forEach((field) => {
                    if (!field || !field.name) {
                        return;
                    }

                    this.dynamicValues[field.name] = '';
                });
            },
            resolveFieldLabel(field) {
                if (window.__i18n.locale === 'en' && field.label_en) {
                    return field.label_en;
                }

                return field.label || field.name;
            },
            buildAddPayload() {
                const payload = {
                    asset_tag: this.form.asset_tag.trim(),
                    name: this.form.name.trim(),
                    category_id: Number(this.form.category_id),
                    status: this.form.status,
                };

                if (this.form.serial_number.trim() !== '') {
                    payload.serial_number = this.form.serial_number.trim();
                }

                if (this.selectedUser?.id) {
                    payload.user_id = Number(this.selectedUser.id);
                }

                this.dynamicFields.forEach((field) => {
                    if (!field || !field.name) {
                        return;
                    }

                    const rawValue = this.dynamicValues[field.name];

                    if (rawValue === undefined || rawValue === null || String(rawValue).trim() === '') {
                        return;
                    }

                    payload[field.name] = field.type === 'number'
                        ? Number(rawValue)
                        : String(rawValue).trim();
                });

                return payload;
            },
            buildEditPayload() {
                return {
                    status: this.editForm.status,
                    user_id: this.selectedUser?.id ? Number(this.selectedUser.id) : null,
                };
            },
            async submitAddForm() {
                this.isSubmitting = true;
                this.addErrorMessage = '';

                try {
                    const response = await fetch('/api/assets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildAddPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.addErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.addErrorMessage = result.message || window.__i18n.create_error;
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.addErrorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
            async submitEditForm() {
                if (!this.editAsset?.id) {
                    return;
                }

                this.isSubmitting = true;
                this.editErrorMessage = '';

                try {
                    const response = await fetch(`/api/assets/${this.editAsset.id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildEditPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.editErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.editErrorMessage = result.message || window.__i18n.update_error;
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.editErrorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
        };
    }
</script>
