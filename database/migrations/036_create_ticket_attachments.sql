-- Ticket file attachments (help desk)

CREATE TABLE IF NOT EXISTS ticket_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    mime_type VARCHAR(128) NOT NULL DEFAULT '',
    uploaded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_attachments_ticket_id (ticket_id),
    KEY idx_ticket_attachments_uploaded_by (uploaded_by),
    CONSTRAINT fk_ticket_attachments_ticket_id
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
