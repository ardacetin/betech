<div class="relative mt-4">
    <div x-show="selectedUser" x-cloak class="mb-3 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
        <div>
            <p class="text-sm font-medium text-emerald-900" x-text="selectedUser?.name"></p>
            <p class="text-xs text-emerald-700" x-text="selectedUser?.email"></p>
        </div>
        <button type="button" @click="clearSelectedUser()" class="text-xs font-medium text-emerald-800 hover:underline">
            <?= htmlspecialchars(__('unassign_user'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <input
            type="text"
            x-model="userSearchQuery"
            @input.debounce.300ms="searchUsers()"
            @focus="showUserResults = true"
            :placeholder="window.__i18n.search_users_placeholder"
            class="min-w-0 flex-1 rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
        >
        <button
            type="button"
            @click="openManualUserForm()"
            class="shrink-0 rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"
        >
            <?= htmlspecialchars(__('add_manual_user'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
    <div
        x-show="showUserResults && (userSearchResults.length > 0 || userSearchError || (userSearchQuery !== '' && !userSearchLoading))"
        x-cloak
        @click.outside="showUserResults = false"
        class="absolute z-10 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-soft"
    >
        <template x-for="user in userSearchResults" :key="user.id">
            <button
                type="button"
                @click="selectUser(user)"
                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50"
            >
                <span class="text-sm font-medium text-zinc-900" x-text="user.name"></span>
                <span class="text-xs text-zinc-500" x-text="user.email"></span>
                <span class="text-xs text-zinc-400" x-text="user.department || ''"></span>
            </button>
        </template>
        <p
            x-show="userSearchError"
            class="px-4 py-3 text-sm text-rose-600"
            x-text="userSearchError"
        ></p>
        <p
            x-show="userSearchResults.length === 0 && userSearchQuery !== '' && !userSearchLoading && !userSearchError"
            class="px-4 py-3 text-sm text-zinc-500"
            x-text="window.__i18n.no_users_found"
        ></p>
    </div>

    <div x-show="isManualUserFormOpen" x-cloak class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block sm:col-span-1">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('manual_user_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input
                    type="text"
                    x-model="manualUserForm.name"
                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                >
            </label>
            <label class="block sm:col-span-1">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('manual_user_email_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <input
                    type="email"
                    x-model="manualUserForm.email"
                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                >
            </label>
        </div>
        <p x-show="manualUserFormError" x-cloak class="mt-3 text-sm text-rose-600" x-text="manualUserFormError"></p>
        <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button" @click="closeManualUserForm()" class="rounded-xl px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100">
                <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button
                type="button"
                @click="submitManualUser()"
                :disabled="isManualUserSubmitting"
                class="rounded-xl bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"
            >
                <?= htmlspecialchars(__('manual_user_create_button'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>
