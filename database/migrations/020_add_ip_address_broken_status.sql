ALTER TABLE ip_addresses
    MODIFY COLUMN status ENUM('available', 'reserved', 'assigned', 'dhcp', 'broken') NOT NULL DEFAULT 'available';
