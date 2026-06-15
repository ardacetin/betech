-- Separate personnel records from system users (authentication accounts)

CREATE TABLE IF NOT EXISTS personnel (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    department VARCHAR(120) DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    external_id VARCHAR(128) DEFAULT NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'local',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_personnel_email (email),
    KEY idx_personnel_name (name),
    KEY idx_personnel_department (department),
    KEY idx_personnel_external_id (external_id),
    KEY idx_personnel_provider (provider),
    KEY idx_personnel_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
