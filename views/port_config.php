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
<div class="min-h-screen bg-gray-50" x-data="portConfigPage()">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-2xl items-center gap-4 px-6 py-4">
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
    </header>

    <main class="mx-auto max-w-2xl px-6 py-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <p class="text-sm text-zinc-500">
                <span class="font-medium text-zinc-800" x-text="context.switch?.name || '—'"></span>
                <span class="text-zinc-300"> · </span>
                <?= htmlspecialchars(__('switch_port_config_port'), ENT_QUOTES, 'UTF-8') ?>
                <span class="font-semibold text-sky-700" x-text="context.port_number"></span>
            </p>

            <label class="mt-6 block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('switch_port_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <textarea
                    x-model="description"
                    rows="6"
                    maxlength="2000"
                    placeholder="<?= htmlspecialchars(__('switch_port_description_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                ></textarea>
                <p class="mt-1.5 text-xs text-zinc-500"><?= htmlspecialchars(__('switch_port_description_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            </label>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    @click="saveDescription()"
                    :disabled="saving"
                    class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:opacity-60"
                ><?= htmlspecialchars(__('switch_port_description_save'), ENT_QUOTES, 'UTF-8') ?></button>

                <button
                    type="button"
                    x-show="hasSavedDescription"
                    x-cloak
                    @click="clearDescription()"
                    :disabled="saving || clearing"
                    class="inline-flex items-center rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-60"
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
    description: String(initialContext?.mapping?.description || '').trim(),
    saving: false,
    clearing: false,
    formError: '',
    formSuccess: '',

    get subtitle() {
      const sw = this.context.switch?.name || this.context.switch?.asset_tag || '';
      return <?= json_encode(__('switch_port_config_subtitle'), JSON_UNESCAPED_UNICODE) ?>
        .replace(':switch', sw)
        .replace(':port', this.context.port_number || '');
    },

    get hasSavedDescription() {
      return String(this.context?.mapping?.description || '').trim() !== '';
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
        this.description = String(this.context?.mapping?.description || '').trim();
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

      this.clearing = true;
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
        this.clearing = false;
      }
    },
  };
}
</script>
