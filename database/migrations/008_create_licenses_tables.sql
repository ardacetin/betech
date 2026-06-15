-- Software and license management (SAM)
-- Run automatically via DatabaseInitializer self-healing patch on existing databases.

CREATE TABLE IF NOT EXISTS licenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    vendor VARCHAR(255) NOT NULL,
    license_key TEXT DEFAULT NULL,
    seats INT UNSIGNED NOT NULL DEFAULT 1,
    expiration_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_licenses_vendor (vendor),
    KEY idx_licenses_name (name),
    KEY idx_licenses_expiration_date (expiration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_license_assignments_license_id (license_id),
    KEY idx_license_assignments_asset_id (asset_id),
    KEY idx_license_assignments_user_id (user_id),
    CONSTRAINT fk_license_assignments_license_id
        FOREIGN KEY (license_id) REFERENCES licenses (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_license_assignments_asset_id
        FOREIGN KEY (asset_id) REFERENCES assets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_license_assignments_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
