-- IT-only Kanban task board with optional helpdesk ticket linkage.

CREATE TABLE IF NOT EXISTS todo_cards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'todo',
    priority VARCHAR(32) NOT NULL DEFAULT 'medium',
    assigned_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    labels JSON DEFAULT NULL,
    checklist JSON DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    source VARCHAR(16) NOT NULL DEFAULT 'manual',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_todo_cards_ticket_id (ticket_id),
    KEY idx_todo_cards_board (archived, status, sort_order),
    KEY idx_todo_cards_assigned_user_id (assigned_user_id),
    KEY idx_todo_cards_due_date (due_date),
    CONSTRAINT fk_todo_cards_ticket_id
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_todo_cards_assigned_user_id
        FOREIGN KEY (assigned_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_todo_cards_created_by_user_id
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill existing helpdesk requests so the board is complete immediately.
INSERT INTO todo_cards (
    ticket_id,
    title,
    description,
    status,
    priority,
    assigned_user_id,
    created_by_user_id,
    sort_order,
    source,
    created_at,
    updated_at
)
SELECT
    tickets.id,
    tickets.subject,
    tickets.description,
    CASE
        WHEN tickets.status = 'in_progress' THEN 'doing'
        WHEN tickets.status IN ('resolved', 'closed') THEN 'done'
        ELSE 'todo'
    END,
    tickets.priority,
    tickets.assigned_user_id,
    tickets.created_by_user_id,
    tickets.id,
    'ticket',
    tickets.created_at,
    tickets.updated_at
FROM tickets
LEFT JOIN todo_cards ON todo_cards.ticket_id = tickets.id
WHERE todo_cards.id IS NULL;
