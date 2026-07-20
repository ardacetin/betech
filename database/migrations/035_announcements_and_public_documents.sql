CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    category VARCHAR(64) NOT NULL DEFAULT '',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL DEFAULT NULL,
    created_by BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_published (is_published, published_at),
    KEY idx_announcements_created_by (created_by),
    CONSTRAINT fk_announcements_created_by
        FOREIGN KEY (created_by) REFERENCES personnel (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE quality_documents
    ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER uploaded_by,
    ADD KEY idx_quality_documents_is_public (is_public);
