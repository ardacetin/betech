-- Switch port capacity column + unique switch/port constraint

ALTER TABLE network_port_mappings
    ADD UNIQUE KEY uq_network_port_mappings_switch_port (switch_asset_id, port_number);
