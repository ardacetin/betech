function todoBoardState() {
    return {
        todoCards: [],
        todoUsers: [],
        todoLoading: false,
        todoSubmitting: false,
        todoError: '',
        todoSuccess: '',
        todoSearch: '',
        todoAssigneeFilter: '',
        todoPriorityFilter: '',
        todoShowArchived: false,
        todoDraggingId: null,
        todoDeepLinkHandled: false,
        isTodoModalOpen: false,
        todoFormError: '',
        todoColumns: [
            { status: 'todo', label: window.__i18n.todo_status_todo, dotClass: 'bg-amber-500' },
            { status: 'doing', label: window.__i18n.todo_status_doing, dotClass: 'bg-sky-500' },
            { status: 'done', label: window.__i18n.todo_status_done, dotClass: 'bg-emerald-500' },
        ],
        todoForm: {
            id: null,
            ticket_id: null,
            ticket_number: '',
            title: '',
            description: '',
            status: 'todo',
            priority: 'medium',
            assigned_user_id: '',
            start_date: '',
            due_date: '',
            labelsText: '',
            checklist: [],
            archived: false,
        },
        async fetchTodoBoard() {
            if (!this.canManageAssets || this.todoLoading) {
                return;
            }

            this.todoLoading = true;
            this.todoError = '';

            try {
                const response = await fetch(`/api/todos?archived=${this.todoShowArchived ? '1' : '0'}`, this.apiFetchInit('GET'));
                const result = await response.json();

                if (!response.ok) {
                    this.todoError = this.apiErrorMessage(result, window.__i18n.todo_network_error);
                    return;
                }

                this.todoCards = Array.isArray(result.data) ? result.data : [];
                this.todoUsers = Array.isArray(result.users) ? result.users : [];
                this.openTodoDeepLink();
            } catch (error) {
                this.todoError = window.__i18n.todo_network_error;
            } finally {
                this.todoLoading = false;
            }
        },
        openTodoDeepLink() {
            if (this.todoDeepLinkHandled || this.todoShowArchived) {
                return;
            }

            const cardId = Number(new URLSearchParams(window.location.search).get('card') || 0);

            if (cardId <= 0) {
                return;
            }

            const card = this.todoCards.find((item) => Number(item.id) === cardId);

            if (card) {
                this.todoDeepLinkHandled = true;
                this.openTodoModal(card);
            }
        },
        cardsForTodoColumn(status) {
            const search = String(this.todoSearch || '').trim().toLocaleLowerCase(window.__i18n.locale === 'en' ? 'en-US' : 'tr-TR');

            return this.todoCards
                .filter((card) => card.status === status)
                .filter((card) => {
                    if (this.todoAssigneeFilter === 'unassigned') {
                        return !card.assigned_user_id;
                    }

                    return this.todoAssigneeFilter === ''
                        || Number(card.assigned_user_id) === Number(this.todoAssigneeFilter);
                })
                .filter((card) => this.todoPriorityFilter === '' || card.priority === this.todoPriorityFilter)
                .filter((card) => {
                    if (search === '') {
                        return true;
                    }

                    const haystack = [
                        card.title,
                        card.description,
                        card.ticket_number,
                        card.assigned_user_name,
                        ...(Array.isArray(card.labels) ? card.labels : []),
                    ].join(' ').toLocaleLowerCase(window.__i18n.locale === 'en' ? 'en-US' : 'tr-TR');

                    return haystack.includes(search);
                })
                .sort((left, right) => Number(left.sort_order) - Number(right.sort_order));
        },
        openTodoModal(card = null, status = 'todo') {
            const checklist = Array.isArray(card?.checklist)
                ? card.checklist.map((item) => ({ ...item, done: Boolean(item.done) }))
                : [];

            this.todoForm = {
                id: card?.id ?? null,
                ticket_id: card?.ticket_id ?? null,
                ticket_number: card?.ticket_number ?? '',
                title: card?.title ?? '',
                description: card?.description ?? '',
                status: card?.status ?? status,
                priority: card?.priority ?? 'medium',
                assigned_user_id: card?.assigned_user_id ? String(card.assigned_user_id) : '',
                start_date: card?.start_date ?? '',
                due_date: card?.due_date ?? '',
                labelsText: Array.isArray(card?.labels) ? card.labels.join(', ') : '',
                checklist,
                archived: Boolean(card?.archived),
            };
            this.todoFormError = '';
            this.isTodoModalOpen = true;
        },
        closeTodoModal() {
            if (this.todoSubmitting) {
                return;
            }

            this.isTodoModalOpen = false;
            this.todoFormError = '';
        },
        addTodoChecklistItem() {
            this.todoForm.checklist.push({
                id: `item-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                text: '',
                done: false,
            });
        },
        removeTodoChecklistItem(index) {
            this.todoForm.checklist.splice(index, 1);
        },
        todoPayload() {
            return {
                title: String(this.todoForm.title || '').trim(),
                description: String(this.todoForm.description || '').trim(),
                status: this.todoForm.status,
                priority: this.todoForm.priority,
                assigned_user_id: this.todoForm.assigned_user_id ? Number(this.todoForm.assigned_user_id) : null,
                start_date: this.todoForm.start_date || null,
                due_date: this.todoForm.due_date || null,
                labels: String(this.todoForm.labelsText || '')
                    .split(',')
                    .map((label) => label.trim())
                    .filter(Boolean),
                checklist: this.todoForm.checklist
                    .map((item) => ({
                        id: String(item.id || ''),
                        text: String(item.text || '').trim(),
                        done: Boolean(item.done),
                    }))
                    .filter((item) => item.text !== ''),
            };
        },
        async submitTodoCard() {
            if (this.todoSubmitting) {
                return;
            }

            const payload = this.todoPayload();

            if (payload.title === '') {
                this.todoFormError = window.__i18n.todo_title_required;
                return;
            }

            this.todoSubmitting = true;
            this.todoFormError = '';

            try {
                const isEditing = Number(this.todoForm.id) > 0;
                const endpoint = isEditing ? `/api/todos/${this.todoForm.id}` : '/api/todos';
                const response = await fetch(endpoint, this.apiFetchJsonInit(isEditing ? 'PUT' : 'POST', payload));
                const result = await response.json();

                if (!response.ok) {
                    this.todoFormError = this.apiErrorMessage(result, isEditing ? window.__i18n.todo_update_error : window.__i18n.todo_create_error);
                    return;
                }

                this.todoSuccess = result.message || (isEditing ? window.__i18n.todo_update_success : window.__i18n.todo_create_success);
                this.isTodoModalOpen = false;
                await this.fetchTodoBoard();
            } catch (error) {
                this.todoFormError = window.__i18n.todo_network_error;
            } finally {
                this.todoSubmitting = false;
            }
        },
        startTodoDrag(card, event) {
            if (this.todoShowArchived) {
                event.preventDefault();
                return;
            }

            this.todoDraggingId = Number(card.id);
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(card.id));
        },
        endTodoDrag() {
            this.todoDraggingId = null;
        },
        async dropTodoCard(status, position) {
            const cardId = Number(this.todoDraggingId || 0);
            this.todoDraggingId = null;

            if (cardId <= 0 || this.todoShowArchived) {
                return;
            }

            this.todoError = '';

            try {
                const response = await fetch(`/api/todos/${cardId}/move`, this.apiFetchJsonInit('POST', {
                    status,
                    position,
                }));
                const result = await response.json();

                if (!response.ok) {
                    this.todoError = this.apiErrorMessage(result, window.__i18n.todo_move_error);
                    return;
                }

                this.todoSuccess = result.message || window.__i18n.todo_move_success;
                await this.fetchTodoBoard();
            } catch (error) {
                this.todoError = window.__i18n.todo_network_error;
            }
        },
        async toggleTodoArchive() {
            this.todoShowArchived = !this.todoShowArchived;
            this.todoSuccess = '';
            await this.fetchTodoBoard();
        },
        async toggleTodoCardArchive() {
            if (!this.todoForm.id || this.todoSubmitting) {
                return;
            }

            this.todoSubmitting = true;
            this.todoFormError = '';

            try {
                const archived = !this.todoForm.archived;
                const response = await fetch(`/api/todos/${this.todoForm.id}/archive`, this.apiFetchJsonInit('POST', { archived }));
                const result = await response.json();

                if (!response.ok) {
                    this.todoFormError = this.apiErrorMessage(result, window.__i18n.todo_update_error);
                    return;
                }

                this.todoSuccess = result.message || window.__i18n.todo_update_success;
                this.isTodoModalOpen = false;
                await this.fetchTodoBoard();
            } catch (error) {
                this.todoFormError = window.__i18n.todo_network_error;
            } finally {
                this.todoSubmitting = false;
            }
        },
        todoChecklistProgress(card) {
            const checklist = Array.isArray(card.checklist) ? card.checklist : [];
            const completed = checklist.filter((item) => Boolean(item.done)).length;
            return `☑ ${completed}/${checklist.length}`;
        },
        todoAssigneeInitials(card) {
            const name = String(card.assigned_user_name || '').trim();

            if (name === '') {
                return '—';
            }

            return name.split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toLocaleUpperCase();
        },
        todoLabelClass(label) {
            const classes = [
                'bg-sky-100 text-sky-800',
                'bg-emerald-100 text-emerald-800',
                'bg-amber-100 text-amber-800',
                'bg-rose-100 text-rose-800',
                'bg-indigo-100 text-indigo-800',
            ];
            const hash = Array.from(String(label)).reduce((sum, character) => sum + character.charCodeAt(0), 0);
            return classes[hash % classes.length];
        },
        todoDueLabel(card) {
            if (!card.due_date) {
                return '';
            }

            const today = new Date();
            const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            if (card.status !== 'done' && card.due_date < todayKey) {
                return window.__i18n.todo_overdue;
            }

            if (card.due_date === todayKey) {
                return window.__i18n.todo_due_today;
            }

            return new Intl.DateTimeFormat(window.__i18n.locale === 'en' ? 'en-US' : 'tr-TR', {
                day: '2-digit',
                month: 'short',
            }).format(new Date(`${card.due_date}T00:00:00`));
        },
        todoDueClass(card) {
            if (!card.due_date || card.status === 'done') {
                return 'bg-zinc-100 text-zinc-600 ring-zinc-200';
            }

            const today = new Date();
            const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            if (card.due_date < todayKey) {
                return 'bg-rose-50 text-rose-700 ring-rose-200';
            }

            return card.due_date === todayKey
                ? 'bg-amber-50 text-amber-800 ring-amber-200'
                : 'bg-zinc-100 text-zinc-600 ring-zinc-200';
        },
    };
}
