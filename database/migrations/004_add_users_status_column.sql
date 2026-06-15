-- Add employment status to users for offboarding workflow

ALTER TABLE users
    ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'active' AFTER department;

ALTER TABLE users
    ADD KEY idx_users_status (status);
