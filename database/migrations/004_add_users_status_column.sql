-- Add employment status to users for offboarding workflow

-- Legacy manual migration only. DatabaseInitializer skips this when personnel exists
-- or when users.department was already dropped during personnel separation.
ALTER TABLE users
    ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'active' AFTER email;

ALTER TABLE users
    ADD KEY idx_users_status (status);
