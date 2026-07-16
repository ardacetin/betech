-- Ticket comment attachments, internal notes, followers/CC, and email threading

ALTER TABLE tickets
    ADD COLUMN source VARCHAR(32) NOT NULL DEFAULT 'web' AFTER created_by_user_id,
    ADD COLUMN email_message_id VARCHAR(255) NULL DEFAULT NULL AFTER source,
    ADD COLUMN email_references TEXT NULL DEFAULT NULL AFTER email_message_id;

ALTER TABLE tickets
    ADD UNIQUE KEY uq_tickets_email_message_id (email_message_id);

ALTER TABLE ticket_comments
    ADD COLUMN is_internal TINYINT(1) NOT NULL DEFAULT 0 AFTER body,
    ADD COLUMN email_message_id VARCHAR(255) NULL DEFAULT NULL AFTER is_internal,
    ADD COLUMN email_in_reply_to VARCHAR(255) NULL DEFAULT NULL AFTER email_message_id;

CREATE TABLE IF NOT EXISTS ticket_comment_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_size VARCHAR(64) NOT NULL,
    mime_type VARCHAR(128) DEFAULT NULL,
    uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_comment_attachments_comment_id (comment_id),
    KEY idx_ticket_comment_attachments_ticket_id (ticket_id),
    CONSTRAINT fk_ticket_comment_attachments_comment_id
        FOREIGN KEY (comment_id) REFERENCES ticket_comments (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ticket_comment_attachments_ticket_id
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_followers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    personnel_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_followers_ticket_id (ticket_id),
    KEY idx_ticket_followers_email (email),
    CONSTRAINT fk_ticket_followers_ticket_id
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_email_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    comment_id BIGINT UNSIGNED DEFAULT NULL,
    message_id VARCHAR(255) NOT NULL,
    in_reply_to VARCHAR(255) DEFAULT NULL,
    direction VARCHAR(16) NOT NULL,
    from_address VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_email_messages_message_id (message_id),
    KEY idx_ticket_email_messages_ticket_id (ticket_id),
    KEY idx_ticket_email_messages_in_reply_to (in_reply_to),
    CONSTRAINT fk_ticket_email_messages_ticket_id
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ticket_email_messages_comment_id
        FOREIGN KEY (comment_id) REFERENCES ticket_comments (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
