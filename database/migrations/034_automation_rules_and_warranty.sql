-- Automation rule engine + asset warranty expiry support

CREATE TABLE IF NOT EXISTS automation_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    rule_type VARCHAR(64) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    config JSON NOT NULL,
    recipient_mode VARCHAR(32) NOT NULL DEFAULT 'admins',
    custom_recipients TEXT NULL,
    last_run_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_automation_rules_type (rule_type),
    KEY idx_automation_rules_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rule_firings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id BIGINT UNSIGNED NOT NULL,
    dedupe_key VARCHAR(191) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(255) NULL DEFAULT NULL,
    fired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_rule_firings_dedupe (rule_id, dedupe_key),
    KEY idx_automation_rule_firings_rule_id (rule_id),
    CONSTRAINT fk_automation_rule_firings_rule_id
        FOREIGN KEY (rule_id) REFERENCES automation_rules (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- warranty_expires_at is added by DatabaseInitializer with existence checks
-- on assets and per-type assets_* tables (MySQL has no IF NOT EXISTS for ADD COLUMN).
