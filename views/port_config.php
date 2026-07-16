<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var string $csrfToken
 * @var string $contextJson
 * @var string $backUrl
 */
?>
<div class="min-h-screen bg-gray-50" x-data="portConfigPage()" x-init="init()">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-6 py-4">
            <div class="flex min-w-0 items-center gap-4">
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600" aria-label="<?= htmlspecialchars(__('switch_port_config_back'), ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_port_config_page_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-0.5 text-sm text-zinc-500" x-text="subtitle"></p>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-6 py-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h2 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_port_config_switch_info'), ENT_QUOTES, 'UTF-8') ?></h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-zinc-500"><?= htmlspecialchars(__('switch_port_config_switch_name'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="font-medium text-zinc-900" x-text="context.switch?.name || '—'"></dd>
                </div>
                <div>
                    <dt class="text-zinc-500"><?= htmlspecialchars(__('switch_port_asset_tag'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="font-mono text-zinc-900" x-text="context.switch?.asset_tag || '—'"></dd>
                </div>
                <div>
                    <dt class="text-zinc-500"><?= htmlspecialchars(__('switch_port_config_port'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="font-semibold text-sky-700" x-text="context.port_number"></dd>
                </div>
                <div>
                    <dt class="text-zinc-500"><?= htmlspecialchars(__('switch_port_config_location'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd class="text-zinc-900">
                        <span x-text="context.switch?.location || '—'"></span>
                        <span x-show="context.switch?.building"> · <span x-text="context.switch?.building"></span></span>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h2 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('switch_port_config_assign_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('switch_port_config_assign_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>

            <label class="mt-4 block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('switch_port_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <textarea
                    x-model="description"
                    rows="5"
                    maxlength="2000"
                    placeholder="<?= htmlspecialchars(__('switch_port_description_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                ></textarea>
                <p class="mt-1.5 text-xs text-zinc-500"><?= htmlspecialchars(__('switch_port_description_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            </label>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    @click="saveDescription()"
                    :disabled="saving"
                    class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:opacity-60"
                ><?= htmlspecialchars(__('switch_port_description_save'), ENT_QUOTES, 'UTF-8') ?></button>

                <button
                    type="button"
                    x-show="hasDescription"
                    x-cloak
                    @click="clearDescription()"
                    :disabled="saving || disconnecting"
                    class="inline-flex items-center rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:opacity-60"
                ><?= htmlspecialchars(__('switch_port_disconnect'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>

            <p x-show="formError" x-cloak class="mt-4 text-sm text-rose-600" x-text="formError"></p>
            <p x-show="formSuccess" x-cloak class="mt-4 text-sm text-emerald-700" x-text="formSuccess"></p>
        </section>
    </main>
</div>

<script>
function portConfigPage() {
  const initialContext = <?= $contextJson ?>;

  return {
    context: initialContext,
    description: initialContext?.mapping?.description || '',
    saving: false,
    disconnecting: false,
    formError: '',
    formSuccess: '',

    get subtitle() {
      const sw = this.context.switch?.name || this.context.switch?.asset_tag || '';
      return <?= json_encode(__('switch_port_config_subtitle'), JSON_UNESCAPED_UNICODE) ?>
        .replace(':switch', sw)
        .replace(':port', this.context.port_number || '');
    },

    get hasDescription() {
      return String(this.context?.mapping?.description || '').trim() !== '';
    },

    init() {
      // context loaded from server
    },

    async saveDescription() {
      this.saving = true;
      this.formError = '';
      this.formSuccess = '';

      try {
        const response = await fetch('/api/network/port-mappings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            switch_asset_id: this.context.switch.id,
            port_number: this.context.port_number,
            description: this.description,
          }),
        });
        const payload = await response.json();
        if (!response.ok || payload.status !== 'success') {
          throw new Error(payload.message || 'Failed');
        }
        this.context = payload.data || this.context;
        this.description = this.context?.mapping?.description || '';
        this.formSuccess = payload.message || <?= json_encode(__('switch_port_assign_success'), JSON_UNESCAPED_UNICODE) ?>;
      } catch (error) {
        this.formError = error?.message || <?= json_encode(__('switch_port_assign_error'), JSON_UNESCAPED_UNICODE) ?>;
      } finally {
        this.saving = false;
      }
    },

    async clearDescription() {
      if (!confirm(<?= json_encode(__('switch_port_disconnect_confirm'), JSON_UNESCAPED_UNICODE) ?>)) {
        return;
      }

      this.disconnecting = true;
      this.formError = '';
      this.formSuccess = '';
      this.description = '';

      try {
        const response = await fetch('/api/network/port-mappings/disconnect', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            switch_asset_id: this.context.switch.id,
            port_number: this.context.port_number,
          }),
        });
        const payload = await response.json();
        if (!response.ok || payload.status !== 'success') {
          throw new Error(payload.message || 'Failed');
        }
        this.context.mapping = null;
        this.formSuccess = payload.message || <?= json_encode(__('switch_port_disconnect_success'), JSON_UNESCAPED_UNICODE) ?>;
      } catch (error) {
        this.formError = error?.message || <?= json_encode(__('switch_port_disconnect_error'), JSON_UNESCAPED_UNICODE) ?>;
      } finally {
        this.disconnecting = false;
      }
    },
  };
}
</script>
