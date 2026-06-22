<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'knowledge_base'" x-cloak class="space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_knowledge_base_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_knowledge_base_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <p x-show="publishedKnowledgeBaseLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('portal_knowledge_base_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="publishedKnowledgeBaseError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="publishedKnowledgeBaseError"></p>
    <p
        x-show="!publishedKnowledgeBaseLoading && !publishedKnowledgeBaseError && publishedKnowledgeBase.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('portal_knowledge_base_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div
        x-show="!publishedKnowledgeBaseLoading && publishedKnowledgeBase.length > 0"
        x-cloak
        class="grid grid-cols-1 gap-4 md:grid-cols-3"
    >
        <template x-for="article in publishedKnowledgeBase" :key="article.id">
            <div class="break-inside-avoid rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                <h3 class="mb-2 text-lg font-semibold text-gray-900" x-text="article.title"></h3>
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700" x-text="article.content"></p>
            </div>
        </template>
    </div>
</section>
