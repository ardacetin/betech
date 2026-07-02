-- Global asset index, soft custom field deactivation, polymorphic ticket asset type.

CREATE TABLE IF NOT EXISTS assets_global_registry (
    id BIGINT UNSIGNED NOT NULL,
    asset_tag VARCHAR(64) NOT NULL DEFAULT '',
    serial_number VARCHAR(128) DEFAULT NULL,
    mac_address VARCHAR(255) DEFAULT NULL,
    asset_type VARCHAR(255) NOT NULL DEFAULT '',
    assigned_to VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    status VARCHAR(32) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_agr_asset_tag (asset_tag),
    KEY idx_agr_serial_number (serial_number),
    KEY idx_agr_mac_address (mac_address),
    KEY idx_agr_asset_type (asset_type),
    KEY idx_agr_assigned_to (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE asset_custom_fields
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order;

ALTER TABLE tickets
    ADD COLUMN asset_type VARCHAR(255) NULL DEFAULT NULL AFTER asset_id;
