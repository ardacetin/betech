-- Consumables (sarf malzeme) inventory tracking

CREATE TABLE IF NOT EXISTS consumables (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    min_stock_level INT UNSIGNED NOT NULL DEFAULT 0,
    location_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consumables_name (name),
    KEY idx_consumables_quantity (quantity),
    KEY idx_consumables_location_id (location_id),
    CONSTRAINT fk_consumables_location_id
        FOREIGN KEY (location_id) REFERENCES locations (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
