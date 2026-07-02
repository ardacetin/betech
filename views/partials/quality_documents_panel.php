<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'documents'" x-cloak class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 p-6 text-white shadow-soft">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-400"><?= htmlspecialchars(__('quality_documents_badge'), ENT_QUOTES, 'UTF-8') ?></p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight"><?= htmlspecialchars(__('quality_documents_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-300"><?= htmlspecialchars(__('quality_documents_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button
                type="button"
                @click="openQualityDocumentModal()"
                class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"></path>
                </svg>
                <?= htmlspecialchars(__('quality_documents_upload_button'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <p x-show="qualityDocumentsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="qualityDocumentsSuccessMessage"></p>
    <p x-show="qualityDocumentsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('quality_documents_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="qualityDocumentsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="qualityDocumentsError"></p>
    <p
        x-show="!qualityDocumentsLoading && !qualityDocumentsError && qualityDocuments.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('quality_documents_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!qualityDocumentsLoading && qualityDocuments.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                            <button type="button" @click="setDocumentsSort('title')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                                <span><?= htmlspecialchars(__('quality_documents_col_title'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(documentsSort, 'title')" x-text="sortIndicatorSymbol(documentsSort, 'title')" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th class="whitespace-nowrap px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                            <button type="button" @click="setDocumentsSort('created_at')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                                <span><?= htmlspecialchars(__('quality_documents_col_upload_date'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(documentsSort, 'created_at')" x-text="sortIndicatorSymbol(documentsSort, 'created_at')" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th class="whitespace-nowrap px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                            <button type="button" @click="setDocumentsSort('file_size')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                                <span><?= htmlspecialchars(__('quality_documents_col_file_size'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(documentsSort, 'file_size')" x-text="sortIndicatorSymbol(documentsSort, 'file_size')" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th class="whitespace-nowrap px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                            <button type="button" @click="setDocumentsSort('uploaded_by')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                                <span><?= htmlspecialchars(__('quality_documents_col_uploaded_by'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(documentsSort, 'uploaded_by')" x-text="sortIndicatorSymbol(documentsSort, 'uploaded_by')" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th class="whitespace-nowrap px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <template x-for="document in qualityDocuments" :key="document.id">
                        <tr class="hover:bg-zinc-50/80">
                            <td class="px-4 py-1.5 font-medium text-zinc-900" x-text="document.title"></td>
                            <td class="px-4 py-1.5 tabular-nums text-zinc-600" x-text="formatQualityDocumentDate(document.created_at)"></td>
                            <td class="px-4 py-1.5 tabular-nums text-zinc-600" x-text="document.file_size || '—'"></td>
                            <td class="px-4 py-1.5 text-zinc-600" x-text="document.uploaded_by_name || '—'"></td>
                            <td class="px-4 py-1.5">
                                <div class="flex items-center justify-end gap-1">
                                    <a
                                        :href="`/api/quality-documents/${document.id}/download`"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-900"
                                        :title="'<?= htmlspecialchars(__('quality_documents_action_download'), ENT_QUOTES, 'UTF-8') ?>'"
                                        :aria-label="'<?= htmlspecialchars(__('quality_documents_action_download'), ENT_QUOTES, 'UTF-8') ?>'"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"></path>
                                        </svg>
                                    </a>
                                    <button
                                        type="button"
                                        @click="deleteQualityDocument(document)"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700"
                                        :title="'<?= htmlspecialchars(__('quality_documents_action_delete'), ENT_QUOTES, 'UTF-8') ?>'"
                                        :aria-label="'<?= htmlspecialchars(__('quality_documents_action_delete'), ENT_QUOTES, 'UTF-8') ?>'"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <?php
        $listPagination = [
            'pagination' => 'documentsPagination',
            'loading' => 'qualityDocumentsLoading',
            'goToPage' => 'goToDocumentsPage',
            'pageNumbers' => 'documentsPageNumbers',
            'label' => 'resolveDocumentsPaginationLabel',
        ];
        require __DIR__ . '/list_pagination.php';
        ?>
    </div>

    <div
        x-show="isQualityDocumentModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeQualityDocumentModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeQualityDocumentModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('quality_documents_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('quality_documents_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeQualityDocumentModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitQualityDocumentForm" class="px-6 py-5">
                <div class="grid gap-4">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('quality_documents_title_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="text" x-model="qualityDocumentForm.title" required maxlength="255" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('quality_documents_file_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input
                            type="file"
                            accept=".pdf,.docx,.xlsx,.pptx,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                            @change="qualityDocumentForm.file = $event.target.files[0] || null"
                            required
                            class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200"
                        >
                        <span class="mt-1.5 block text-xs text-zinc-500"><?= htmlspecialchars(__('quality_documents_allowed_types'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </div>

                <p x-show="qualityDocumentFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="qualityDocumentFormError"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeQualityDocumentModal()" :disabled="isQualityDocumentSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isQualityDocumentSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isQualityDocumentSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isQualityDocumentSubmitting"><?= htmlspecialchars(__('quality_documents_upload_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
