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

CREATE TABLE IF NOT EXISTS assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_tag VARCHAR(64) NOT NULL,
    serial_number VARCHAR(128) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'ready',
    user_id BIGINT UNSIGNED DEFAULT NULL,
    properties JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assets_asset_tag (asset_tag),
    KEY idx_assets_serial_number (serial_number),
    KEY idx_assets_category_id (category_id),
    KEY idx_assets_status (status),
    KEY idx_assets_user_id (user_id),
    CONSTRAINT fk_assets_category_id
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
