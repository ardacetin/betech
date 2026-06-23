-- Flatten assets: native columns for inventory attributes (no JSON / relation FKs)

ALTER TABLE assets
    ADD COLUMN model VARCHAR(255) DEFAULT NULL AFTER name,
    ADD COLUMN brand VARCHAR(255) DEFAULT NULL AFTER model,
    ADD COLUMN type VARCHAR(255) DEFAULT NULL AFTER serial_number,
    ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER status,
    ADD COLUMN building VARCHAR(255) DEFAULT NULL AFTER location,
    ADD COLUMN assigned_to VARCHAR(255) DEFAULT NULL AFTER building,
    ADD COLUMN mac_address_1 VARCHAR(255) DEFAULT NULL AFTER assigned_to,
    ADD COLUMN mac_address_2 VARCHAR(255) DEFAULT NULL AFTER mac_address_1;

UPDATE assets a
LEFT JOIN categories c ON c.id = a.category_id
LEFT JOIN locations l ON l.id = a.location_id
LEFT JOIN personnel p ON p.id = a.personnel_id
SET
    a.model = COALESCE(NULLIF(a.model, ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.model')), '')),
    a.brand = COALESCE(NULLIF(a.brand, ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.brand')), '')),
    a.type = COALESCE(NULLIF(a.type, ''), c.name),
    a.location = COALESCE(NULLIF(a.location, ''), l.name),
    a.building = COALESCE(NULLIF(a.building, ''), l.building),
    a.assigned_to = COALESCE(
        NULLIF(a.assigned_to, ''),
        NULLIF(p.email, ''),
        NULLIF(p.name, '')
    ),
    a.mac_address_1 = COALESCE(
        NULLIF(a.mac_address_1, ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.custom_fields.mac_adresi_1')), ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.mac_adresi_1')), '')
    ),
    a.mac_address_2 = COALESCE(
        NULLIF(a.mac_address_2, ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.custom_fields.mac_adresi_2')), ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.mac_adresi_2')), '')
    );

ALTER TABLE assets DROP FOREIGN KEY fk_assets_category_id;
ALTER TABLE assets DROP FOREIGN KEY fk_assets_personnel_id;
ALTER TABLE assets DROP FOREIGN KEY fk_assets_location_id;

ALTER TABLE assets
    DROP COLUMN category_id,
    DROP COLUMN personnel_id,
    DROP COLUMN location_id,
    DROP COLUMN properties;
