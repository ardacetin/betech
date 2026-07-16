-- Asset maintenance / repair tracking

CREATE TABLE IF NOT EXISTS maintenance_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id BIGINT UNSIGNED NOT NULL,
    provider_name VARCHAR(255) NOT NULL,
    issue_description TEXT NOT NULL,
    repair_cost DECIMAL(12, 2) DEFAULT NULL,
    sent_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'under_repair',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenance_logs_asset_id (asset_id),
    KEY idx_maintenance_logs_status (status),
    KEY idx_maintenance_logs_sent_date (sent_date),
    CONSTRAINT fk_maintenance_logs_asset_id
        FOREIGN KEY (asset_id) REFERENCES assets (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
