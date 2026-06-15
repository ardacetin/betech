-- Betech ITAM hybrid schema (MySQL 8.0+)
-- Run: mysql -u root -p betech < database/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    fields JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_id VARCHAR(128) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    department VARCHAR(120) DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    role VARCHAR(50) NOT NULL DEFAULT 'end_user',
    password_hash VARCHAR(255) NULL,
    auth_provider VARCHAR(32) NOT NULL DEFAULT 'local',
    provider_subject VARCHAR(255) DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_external_id (external_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_name (name),
    KEY idx_users_department (department),
    KEY idx_users_status (status),
    KEY idx_users_role (role),
    KEY idx_users_auth_provider (auth_provider),
    KEY idx_users_provider_subject (provider_subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_tag VARCHAR(64) NOT NULL,
    serial_number VARCHAR(128) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'ready',
    user_id BIGINT UNSIGNED DEFAULT NULL,
    location_id BIGINT UNSIGNED DEFAULT NULL,
    properties JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assets_asset_tag (asset_tag),
    KEY idx_assets_serial_number (serial_number),
    KEY idx_assets_category_id (category_id),
    KEY idx_assets_status (status),
    KEY idx_assets_user_id (user_id),
    KEY idx_assets_location_id (location_id),
    CONSTRAINT fk_assets_category_id
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_assets_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_assets_location_id
        FOREIGN KEY (location_id) REFERENCES locations (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_histories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    target_user_id BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_histories_asset_id (asset_id),
    KEY idx_asset_histories_action (action),
    KEY idx_asset_histories_created_at (created_at),
    CONSTRAINT fk_asset_histories_asset_id
        FOREIGN KEY (asset_id) REFERENCES assets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_histories_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_asset_histories_target_user_id
        FOREIGN KEY (target_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL,
    value LONGTEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

SET FOREIGN_KEY_CHECKS = 1;
