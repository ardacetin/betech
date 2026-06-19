-- Immutable system audit trail

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    action_type VARCHAR(32) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_action_type (action_type),
    KEY idx_audit_logs_entity_type (entity_type),
    KEY idx_audit_logs_entity_id (entity_id),
    KEY idx_audit_logs_created_at (created_at),
    CONSTRAINT fk_audit_logs_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
