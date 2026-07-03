-- Rogue device detection for available IPs responding to ICMP ping

ALTER TABLE ip_addresses
    ADD COLUMN is_rogue TINYINT(1) NOT NULL DEFAULT 0 AFTER last_seen_at,
    ADD KEY idx_ip_addresses_is_rogue (is_rogue);
