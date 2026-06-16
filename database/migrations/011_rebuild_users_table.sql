-- Fresh users table for system operator accounts (auth only).
-- Drops legacy users data; personnel lives in the personnel table.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE asset_histories DROP FOREIGN KEY fk_asset_histories_user_id;

UPDATE asset_histories SET user_id = NULL WHERE user_id IS NOT NULL;

DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'technician',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password_hash, role) VALUES (
    'Sistem Yöneticisi',
    'admin@betech.local',
    '$2y$12$Iq4I72XccafKZS3FyZACy.C8b1b1Y81WA6GrhaWuUd2CBqvSKhNV.',
    'super_admin'
);

ALTER TABLE asset_histories
    ADD CONSTRAINT fk_asset_histories_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
