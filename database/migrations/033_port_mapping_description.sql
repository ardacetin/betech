-- Free-text port explanations (no inventory device link required)

ALTER TABLE network_port_mappings
    ADD COLUMN description TEXT NULL AFTER port_number;

ALTER TABLE network_port_mappings
    MODIFY COLUMN source_asset_type VARCHAR(64) NULL,
    MODIFY COLUMN source_asset_id INT UNSIGNED NULL;
