-- IP Address Management (IPAM)

CREATE TABLE IF NOT EXISTS ip_networks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    network_address VARCHAR(45) NOT NULL,
    cidr TINYINT UNSIGNED NOT NULL,
    gateway VARCHAR(45) DEFAULT NULL,
    vlan_id INT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ip_networks_address_cidr (network_address, cidr),
    KEY idx_ip_networks_name (name),
    KEY idx_ip_networks_vlan_id (vlan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    network_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    status ENUM('available', 'reserved', 'assigned', 'dhcp') NOT NULL DEFAULT 'available',
    asset_id BIGINT UNSIGNED DEFAULT NULL,
    hostname VARCHAR(255) DEFAULT NULL,
    mac_address VARCHAR(64) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ip_addresses_network_ip (network_id, ip_address),
    KEY idx_ip_addresses_status (status),
    KEY idx_ip_addresses_asset_id (asset_id),
    CONSTRAINT fk_ip_addresses_network_id
        FOREIGN KEY (network_id) REFERENCES ip_networks (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ip_addresses_asset_id
        FOREIGN KEY (asset_id) REFERENCES assets (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
