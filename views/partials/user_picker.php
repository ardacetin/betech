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
    <input
        type="text"
        x-model="userSearchQuery"
        @input.debounce.300ms="searchUsers()"
        @focus="showUserResults = true"
        :placeholder="window.__i18n.search_users_placeholder"
        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
    >
    <div
        x-show="showUserResults && (userSearchResults.length > 0 || (userSearchQuery !== '' && !userSearchLoading))"
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
            x-show="userSearchResults.length === 0 && userSearchQuery !== '' && !userSearchLoading"
            class="px-4 py-3 text-sm text-zinc-500"
            x-text="window.__i18n.no_users_found"
        ></p>
    </div>
</div>
