-- Add RBAC role column to users table

ALTER TABLE users
    ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'end_user' AFTER status,
    ADD KEY idx_users_role (role);

UPDATE users
SET role = 'super_admin'
WHERE email = 'admin@betech.local';
