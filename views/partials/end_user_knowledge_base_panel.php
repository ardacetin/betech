<?php

declare(strict_types=1);
?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<section x-show="activeView === 'knowledge_base'" x-cloak class="space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_knowledge_base_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_knowledge_base_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div
        x-show="!publishedKnowledgeBaseLoading && publishedKnowledgeBase.length > 0"
        x-cloak
        class="relative"
    >
        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
        </svg>
        <input
            type="search"
            x-model="publishedKnowledgeBaseSearchQuery"
            placeholder="<?= htmlspecialchars(__('portal_knowledge_base_search_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
            class="w-full rounded-2xl border border-zinc-200 bg-white py-3.5 pl-12 pr-4 text-sm text-zinc-900 shadow-sm outline-none ring-zinc-900/10 transition placeholder:text-zinc-400 focus:border-zinc-300 focus:ring-4"
        >
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
    <p
        x-show="!publishedKnowledgeBaseLoading && !publishedKnowledgeBaseError && publishedKnowledgeBase.length > 0 && filteredPublishedKnowledgeBase().length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('portal_knowledge_base_no_results'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div
        x-show="!publishedKnowledgeBaseLoading && filteredPublishedKnowledgeBase().length > 0"
        x-cloak
        class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
    >
        <template x-for="article in filteredPublishedKnowledgeBase()" :key="article.id">
            <article
                x-data="{ isOpen: false }"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md"
            >
                <div
                    @click="isOpen = !isOpen"
                    class="flex w-full cursor-pointer items-start justify-between gap-3 px-5 py-4 text-left transition-colors hover:bg-gray-50/80"
                    :aria-expanded="isOpen ? 'true' : 'false'"
                    role="button"
                    tabindex="0"
                    @keydown.enter.prevent="isOpen = !isOpen"
                    @keydown.space.prevent="isOpen = !isOpen"
                >
                    <h3 class="text-base font-semibold leading-snug text-gray-900" x-text="article.title"></h3>
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                        :class="isOpen ? 'rotate-180' : 'rotate-0'"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"></path>
                    </svg>
                </div>
                <div
                    class="hidden"
                    :class="isOpen ? 'block' : 'hidden'"
                >
                    <div class="border-t border-gray-100 px-5 pb-5 pt-3">
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700" x-text="article.content"></p>
                    </div>
                </div>
            </article>
        </template>
    </div>
</section>
