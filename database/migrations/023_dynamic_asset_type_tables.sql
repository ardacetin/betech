-- Per-type isolated asset tables, custom field metadata, component specs, and global registry.

CREATE TABLE IF NOT EXISTS asset_registry (
    id BIGINT UNSIGNED NOT NULL,
    asset_type_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_registry_type (asset_type_id),
    CONSTRAINT fk_asset_registry_type
        FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_custom_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_type_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(255) NOT NULL,
    column_name VARCHAR(64) NOT NULL,
    field_type VARCHAR(32) NOT NULL DEFAULT 'varchar',
    options JSON NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_custom_fields_type_column (asset_type_id, column_name),
    KEY idx_asset_custom_fields_type (asset_type_id),
    CONSTRAINT fk_asset_custom_fields_type
        FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_components (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(64) NOT NULL,
    column_name VARCHAR(64) NOT NULL DEFAULT '',
    description TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_components_type_slug (asset_type_id, slug),
    KEY idx_asset_components_type (asset_type_id),
    CONSTRAINT fk_asset_components_type
        FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_component_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_type_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    component_id BIGINT UNSIGNED NOT NULL,
    value VARCHAR(512) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_component_values_unique (asset_type_id, asset_id, component_id),
    KEY idx_asset_component_values_asset (asset_type_id, asset_id),
    CONSTRAINT fk_asset_component_values_component
        FOREIGN KEY (component_id) REFERENCES asset_components (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-type physical tables are created programmatically by AssetTypeTableService during migration bootstrap.
