<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'todo'" x-cloak class="space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-balance text-xl font-semibold text-zinc-900"><?= htmlspecialchars(__('todo_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 max-w-3xl text-pretty text-sm text-zinc-500"><?= htmlspecialchars(__('todo_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="openTodoModal()"
            class="btn-brand inline-flex shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium shadow-sm"
        >
            <span class="text-lg leading-none" aria-hidden="true">+</span>
            <?= htmlspecialchars(__('todo_add'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <div class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_14rem_12rem_auto]">
        <label class="min-w-0">
            <span class="sr-only"><?= htmlspecialchars(__('todo_search_placeholder'), ENT_QUOTES, 'UTF-8') ?></span>
            <input
                type="search"
                x-model.debounce.200ms="todoSearch"
                placeholder="<?= htmlspecialchars(__('todo_search_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"
            >
        </label>
        <label>
            <span class="sr-only"><?= htmlspecialchars(__('todo_assignee'), ENT_QUOTES, 'UTF-8') ?></span>
            <select x-model="todoAssigneeFilter" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                <option value=""><?= htmlspecialchars(__('todo_filter_all_users'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="unassigned"><?= htmlspecialchars(__('todo_unassigned'), ENT_QUOTES, 'UTF-8') ?></option>
                <template x-for="user in todoUsers" :key="user.id">
                    <option :value="String(user.id)" x-text="user.name || user.email"></option>
                </template>
            </select>
        </label>
        <label>
            <span class="sr-only"><?= htmlspecialchars(__('todo_priority'), ENT_QUOTES, 'UTF-8') ?></span>
            <select x-model="todoPriorityFilter" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                <option value=""><?= htmlspecialchars(__('todo_filter_all_priorities'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="critical"><?= htmlspecialchars(__('ticket_priority_critical'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
        </label>
        <button
            type="button"
            @click="toggleTodoArchive()"
            class="rounded-xl border border-zinc-300 px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-900/10"
            x-text="todoShowArchived ? window.__i18n.todo_show_active : window.__i18n.todo_show_archived"
        ></button>
    </div>

    <p x-show="todoLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('todo_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="todoError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="todoError"></p>
    <p x-show="todoSuccess" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="todoSuccess"></p>

    <div
        x-show="!todoLoading && !todoError"
        x-cloak
        class="flex snap-x snap-mandatory items-start gap-4 overflow-x-auto pb-4 lg:snap-none"
        aria-label="<?= htmlspecialchars(__('todo_page_title'), ENT_QUOTES, 'UTF-8') ?>"
    >
        <template x-for="column in todoColumns" :key="column.status">
            <section
                class="w-[85vw] max-w-sm shrink-0 snap-start rounded-2xl border border-zinc-200 bg-zinc-100/80 p-3 lg:min-w-0 lg:max-w-none lg:flex-1"
                @dragover.prevent
                @drop.prevent="dropTodoCard(column.status, cardsForTodoColumn(column.status).length)"
            >
                <header class="mb-3 flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full" :class="column.dotClass" aria-hidden="true"></span>
                        <h3 class="text-sm font-semibold text-zinc-900" x-text="column.label"></h3>
                    </div>
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-500" x-text="cardsForTodoColumn(column.status).length"></span>
                </header>

                <div class="space-y-3 min-h-[8rem]">
                    <template x-for="(card, index) in cardsForTodoColumn(column.status)" :key="card.id">
                        <article
                            :draggable="!todoShowArchived"
                            @dragstart="startTodoDrag(card, $event)"
                            @dragend="endTodoDrag()"
                            @dragover.prevent
                            @drop.prevent.stop="dropTodoCard(column.status, index)"
                            class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm"
                            :class="todoDraggingId === card.id ? 'opacity-50' : ''"
                        >
                            <div x-show="card.labels?.length" class="mb-2 flex flex-wrap gap-1.5">
                                <template x-for="label in card.labels" :key="label">
                                    <span class="max-w-full truncate rounded-full px-2 py-0.5 text-[11px] font-medium" :class="todoLabelClass(label)" x-text="label"></span>
                                </template>
                            </div>

                            <button type="button" @click="openTodoModal(card)" class="block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900/20">
                                <span class="line-clamp-2 text-sm font-semibold text-zinc-900" x-text="card.title"></span>
                                <span x-show="card.description" class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500" x-text="card.description"></span>
                            </button>

                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset" :class="ticketPriorityClass(card.priority)" x-text="resolveTicketPriority(card.priority)"></span>
                                <span x-show="card.ticket_number" class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700 ring-1 ring-inset ring-sky-200" x-text="card.ticket_number"></span>
                                <span
                                    x-show="card.due_date"
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                    :class="todoDueClass(card)"
                                    x-text="todoDueLabel(card)"
                                ></span>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-2.5">
                                <div class="min-w-0 text-xs text-zinc-500">
                                    <span x-show="card.checklist?.length" class="tabular-nums" x-text="todoChecklistProgress(card)"></span>
                                    <span x-show="!card.checklist?.length && card.ticket_id"><?= htmlspecialchars(__('todo_ticket_synced'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-semibold text-white"
                                    :class="!card.assigned_user_id ? 'bg-zinc-200 text-zinc-500' : ''"
                                    :title="card.assigned_user_name || window.__i18n.todo_unassigned"
                                    x-text="todoAssigneeInitials(card)"
                                ></span>
                            </div>
                        </article>
                    </template>

                    <button
                        x-show="cardsForTodoColumn(column.status).length === 0 && column.status === 'todo' && !todoShowArchived"
                        type="button"
                        @click="openTodoModal(null, column.status)"
                        class="w-full rounded-xl border border-dashed border-zinc-300 bg-white/60 px-3 py-6 text-sm text-zinc-500 hover:border-zinc-400 hover:bg-white"
                    ><?= htmlspecialchars(__('todo_add'), ENT_QUOTES, 'UTF-8') ?></button>
                    <p
                        x-show="cardsForTodoColumn(column.status).length === 0 && (column.status !== 'todo' || todoShowArchived)"
                        class="rounded-xl border border-dashed border-zinc-300 bg-white/40 px-3 py-6 text-center text-sm text-zinc-500"
                    ><?= htmlspecialchars(__('todo_empty_column'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </section>
        </template>
    </div>

    <p class="text-xs text-zinc-500 lg:hidden"><?= htmlspecialchars(__('todo_drag_hint'), ENT_QUOTES, 'UTF-8') ?></p>
</section>

<div
    x-show="isTodoModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
    @keydown.escape.window="if (isTodoModalOpen) closeTodoModal()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="todo-modal-title"
>
    <button type="button" class="absolute inset-0 cursor-default bg-zinc-900/40" @click="closeTodoModal()" aria-label="<?= htmlspecialchars(__('portal_close'), ENT_QUOTES, 'UTF-8') ?>"></button>
    <form @submit.prevent="submitTodoCard()" class="relative z-10 max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:max-w-2xl sm:rounded-2xl">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-zinc-200 bg-white px-4 py-4 sm:px-6">
            <div class="min-w-0">
                <p x-show="todoForm.ticket_number" class="text-xs font-semibold text-sky-700" x-text="window.__i18n.todo_linked_ticket + ': ' + todoForm.ticket_number"></p>
                <h3 id="todo-modal-title" class="text-balance text-lg font-semibold text-zinc-900" x-text="todoForm.id ? window.__i18n.todo_save : window.__i18n.todo_add"></h3>
            </div>
            <button type="button" @click="closeTodoModal()" class="flex size-10 shrink-0 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-zinc-900/20" aria-label="<?= htmlspecialchars(__('portal_close'), ENT_QUOTES, 'UTF-8') ?>">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="space-y-5 px-4 py-5 sm:px-6">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_title_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="todoForm.title" maxlength="255" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
            </label>

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_description'), ENT_QUOTES, 'UTF-8') ?></span>
                <textarea x-model="todoForm.description" rows="4" maxlength="20000" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"></textarea>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_status'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="todoForm.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                        <template x-for="column in todoColumns" :key="column.status"><option :value="column.status" x-text="column.label"></option></template>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_priority'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="todoForm.priority" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                        <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="critical"><?= htmlspecialchars(__('ticket_priority_critical'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
            </div>

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_assignee'), ENT_QUOTES, 'UTF-8') ?></span>
                <select x-model="todoForm.assigned_user_id" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value=""><?= htmlspecialchars(__('todo_unassigned'), ENT_QUOTES, 'UTF-8') ?></option>
                    <template x-for="user in todoUsers" :key="user.id"><option :value="String(user.id)" x-text="user.name ? `${user.name} — ${user.email}` : user.email"></option></template>
                </select>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_start_date'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="date" x-model="todoForm.start_date" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                </label>
                <label>
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_due_date'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="date" x-model="todoForm.due_date" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                </label>
            </div>

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_labels'), ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" x-model="todoForm.labelsText" maxlength="263" placeholder="backend, acil, kampüs" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                <span class="mt-1 block text-xs text-zinc-500"><?= htmlspecialchars(__('todo_labels_hint'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <fieldset>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <legend class="text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('todo_checklist'), ENT_QUOTES, 'UTF-8') ?></legend>
                    <button type="button" @click="addTodoChecklistItem()" class="rounded-lg border border-zinc-200 px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('todo_add_checklist_item'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div class="space-y-2">
                    <template x-for="(item, index) in todoForm.checklist" :key="item.id">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" x-model="item.done" class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                            <input type="text" x-model="item.text" maxlength="255" placeholder="<?= htmlspecialchars(__('todo_checklist_placeholder'), ENT_QUOTES, 'UTF-8') ?>" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                            <button type="button" @click="removeTodoChecklistItem(index)" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-zinc-400 hover:bg-rose-50 hover:text-rose-700" aria-label="<?= htmlspecialchars(__('todo_remove_checklist_item'), ENT_QUOTES, 'UTF-8') ?>">&times;</button>
                        </div>
                    </template>
                </div>
            </fieldset>

            <p x-show="todoFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="todoFormError"></p>
        </div>

        <div class="sticky bottom-0 flex flex-col-reverse gap-2 border-t border-zinc-200 bg-white px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <button
                x-show="todoForm.id"
                type="button"
                @click="toggleTodoCardArchive()"
                :disabled="todoSubmitting"
                class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-50"
                x-text="todoForm.archived ? window.__i18n.todo_restore : window.__i18n.todo_archive"
            ></button>
            <div class="flex justify-end gap-2 sm:ml-auto">
                <button type="button" @click="closeTodoModal()" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="submit" :disabled="todoSubmitting" class="btn-brand rounded-xl px-4 py-2.5 text-sm font-medium shadow-sm disabled:cursor-not-allowed disabled:opacity-50" x-text="todoSubmitting ? window.__i18n.saving : (todoForm.id ? window.__i18n.todo_save : window.__i18n.todo_create)"></button>
            </div>
        </div>
    </form>
</div>
