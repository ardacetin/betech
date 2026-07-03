-- Track which polymorphic asset section (slug) an audit or history row refers to.

ALTER TABLE audit_logs
    ADD COLUMN asset_type VARCHAR(255) NULL DEFAULT NULL AFTER entity_id;

ALTER TABLE audit_logs
    ADD KEY idx_audit_logs_asset_type (asset_type);

ALTER TABLE asset_histories
    ADD COLUMN asset_type VARCHAR(255) NULL DEFAULT NULL AFTER asset_id;

ALTER TABLE asset_histories
    ADD KEY idx_asset_histories_asset_type (asset_type);
