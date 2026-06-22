<?php

declare(strict_types=1);

/** @var array{pagination: string, loading: string, goToPage: string, pageNumbers: string, label: string} $listPagination */
$listPagination = $listPagination ?? null;

if (!is_array($listPagination)) {
    return;
}

$paginationVar = (string) ($listPagination['pagination'] ?? '');
$loadingVar = (string) ($listPagination['loading'] ?? '');
$goToPageFn = (string) ($listPagination['goToPage'] ?? '');
$pageNumbersFn = (string) ($listPagination['pageNumbers'] ?? '');
$labelFn = (string) ($listPagination['label'] ?? '');

if ($paginationVar === '' || $loadingVar === '' || $goToPageFn === '' || $pageNumbersFn === '' || $labelFn === '') {
    return;
}
?>
<div
    x-show="!<?= $loadingVar ?> && <?= $paginationVar ?>.total_pages > 1"
    x-cloak
    class="flex flex-col gap-3 border-t border-zinc-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
>
    <p class="text-xs text-zinc-500" x-text="<?= $labelFn ?>()"></p>
    <div class="flex flex-wrap items-center gap-1">
        <button
            type="button"
            @click="<?= $goToPageFn ?>(<?= $paginationVar ?>.page - 1)"
            :disabled="<?= $paginationVar ?>.page <= 1 || <?= $loadingVar ?>"
            class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <?= htmlspecialchars(__('list_pagination_prev'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <template x-for="pageNumber in <?= $pageNumbersFn ?>()" :key="'<?= $paginationVar ?>-page-' + pageNumber">
            <button
                type="button"
                @click="<?= $goToPageFn ?>(pageNumber)"
                :disabled="<?= $loadingVar ?>"
                class="min-w-[2rem] rounded-lg border px-3 py-1.5 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="pageNumber === <?= $paginationVar ?>.page ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50'"
                x-text="pageNumber"
            ></button>
        </template>
        <button
            type="button"
            @click="<?= $goToPageFn ?>(<?= $paginationVar ?>.page + 1)"
            :disabled="<?= $paginationVar ?>.page >= <?= $paginationVar ?>.total_pages || <?= $loadingVar ?>"
            class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <?= htmlspecialchars(__('list_pagination_next'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</div>
