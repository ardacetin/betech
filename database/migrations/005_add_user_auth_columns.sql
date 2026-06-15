-- Add authentication columns to users table

ALTER TABLE users
    ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email,
    ADD COLUMN auth_provider VARCHAR(32) NOT NULL DEFAULT 'local' AFTER password_hash,
    ADD COLUMN provider_subject VARCHAR(255) DEFAULT NULL AFTER auth_provider,
    ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER status,
    ADD KEY idx_users_auth_provider (auth_provider),
    ADD KEY idx_users_provider_subject (provider_subject);
