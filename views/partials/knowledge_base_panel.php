<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'knowledge_base'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('kb_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('kb_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="openKnowledgeBaseModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
        >
            <span class="text-lg leading-none">+</span>
            <?= htmlspecialchars(__('kb_add_article'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <p x-show="knowledgeBaseLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('kb_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="knowledgeBaseError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="knowledgeBaseError"></p>
    <p x-show="knowledgeBaseSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="knowledgeBaseSuccessMessage"></p>

    <p
        x-show="!knowledgeBaseLoading && !knowledgeBaseError && knowledgeBaseArticles.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('kb_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!knowledgeBaseLoading && knowledgeBaseArticles.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('kb_col_title'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('kb_col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('kb_col_author'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('kb_col_updated'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="article in knowledgeBaseArticles" :key="article.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-zinc-900" x-text="article.title"></p>
                            <p class="mt-1 line-clamp-2 text-xs text-zinc-500" x-text="article.content"></p>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                :class="article.is_published ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-zinc-100 text-zinc-600 ring-zinc-500/20'"
                                x-text="article.is_published ? '<?= htmlspecialchars(__('kb_status_published'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('kb_status_draft'), ENT_QUOTES, 'UTF-8') ?>'"
                            ></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="article.author_name || '—'"></td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="formatKnowledgeBaseDate(article.updated_at)"></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="openKnowledgeBaseModal(article)"
                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                ><?= htmlspecialchars(__('kb_edit_article'), ENT_QUOTES, 'UTF-8') ?></button>
                                <button
                                    type="button"
                                    @click="deleteKnowledgeBaseArticle(article)"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100"
                                ><?= htmlspecialchars(__('kb_delete_article'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</section>
