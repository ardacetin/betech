-- Bilgi İşlem Kalite Yönetim Dokümanları

CREATE TABLE IF NOT EXISTS quality_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_size VARCHAR(64) NOT NULL,
    uploaded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quality_documents_created_at (created_at),
    KEY idx_quality_documents_uploaded_by (uploaded_by),
    CONSTRAINT fk_quality_documents_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES personnel (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
