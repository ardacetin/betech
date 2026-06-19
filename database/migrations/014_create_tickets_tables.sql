-- Help desk / ticketing module

CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_number VARCHAR(20) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    personnel_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    priority VARCHAR(32) NOT NULL DEFAULT 'medium',
    assigned_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_ticket_number (ticket_number),
    KEY idx_tickets_status (status),
    KEY idx_tickets_priority (priority),
    KEY idx_tickets_personnel_id (personnel_id),
    KEY idx_tickets_asset_id (asset_id),
    KEY idx_tickets_created_at (created_at),
    CONSTRAINT fk_tickets_personnel_id
        FOREIGN KEY (personnel_id) REFERENCES personnel (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_asset_id
        FOREIGN KEY (asset_id) REFERENCES assets (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_assigned_user_id
        FOREIGN KEY (assigned_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_created_by_user_id
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    author_name VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_comments_ticket_id (ticket_id),
    KEY idx_ticket_comments_created_at (created_at),
    CONSTRAINT fk_ticket_comments_ticket_id
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ticket_comments_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
