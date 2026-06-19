-- LDAP-only auth: local role on personnel, remove stored passwords from users.
ALTER TABLE personnel
    ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'user' AFTER status;

ALTER TABLE personnel
    ADD KEY idx_personnel_role (role);

ALTER TABLE users
    DROP COLUMN password_hash;
