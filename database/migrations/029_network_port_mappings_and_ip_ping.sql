-- Switch port mappings + IP ping heartbeat tracking

CREATE TABLE IF NOT EXISTS network_port_mappings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_asset_type VARCHAR(64) NOT NULL,
    source_asset_id INT UNSIGNED NOT NULL,
    switch_asset_id INT UNSIGNED NOT NULL,
    port_number VARCHAR(32) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_network_port_mappings_source (source_asset_type, source_asset_id),
    KEY idx_network_port_mappings_switch (switch_asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ip_addresses
    ADD COLUMN ping_status ENUM('online', 'offline') DEFAULT NULL AFTER notes,
    ADD COLUMN last_seen_at DATETIME DEFAULT NULL AFTER ping_status;

ALTER TABLE ip_addresses
    ADD KEY idx_ip_addresses_ping_status (ping_status);

INSERT INTO asset_types (name, slug, sort_order) VALUES
    ('Ağ Anahtarları', 'ag_anahtarlari', 10)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sort_order = VALUES(sort_order);
