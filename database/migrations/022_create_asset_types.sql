-- GLPi-style dynamic asset sections (asset_types) with shared flat assets schema.

CREATE TABLE IF NOT EXISTS asset_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_types_slug (slug),
    KEY idx_asset_types_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO asset_types (id, name, slug, sort_order) VALUES
    (1, 'Bilgisayarlar', 'bilgisayarlar', 1),
    (2, 'Monitörler', 'monitorler', 2),
    (3, 'Yazıcılar', 'yazicilar', 3)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    slug = VALUES(slug),
    sort_order = VALUES(sort_order);

ALTER TABLE assets
    ADD COLUMN asset_type_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id;

ALTER TABLE assets
    ADD KEY idx_assets_asset_type_id (asset_type_id);

UPDATE assets SET asset_type_id = 1 WHERE asset_type_id = 0;

ALTER TABLE assets
    ADD CONSTRAINT fk_assets_asset_type
        FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
        ON UPDATE CASCADE;
