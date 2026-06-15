-- Physical location tracking for assets

CREATE TABLE IF NOT EXISTS locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    building VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_locations_building (building),
    KEY idx_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assets
    ADD COLUMN location_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id,
    ADD KEY idx_assets_location_id (location_id),
    ADD CONSTRAINT fk_assets_location_id
        FOREIGN KEY (location_id) REFERENCES locations (id)
        ON DELETE SET NULL ON UPDATE CASCADE;
