-- Track physical column names for component specs that map to DDL columns on per-type asset tables.

ALTER TABLE asset_components
    ADD COLUMN column_name VARCHAR(64) NOT NULL DEFAULT '' AFTER slug;
