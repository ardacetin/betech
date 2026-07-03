-- Performance indexes for polymorphic asset tables (assets_{slug}).
-- Applied idempotently at runtime via DatabaseInitializer::patchPolymorphicAssetPerformanceIndexes().

-- Per table (example for assets_monitorler):
-- ALTER TABLE assets_monitorler ADD INDEX idx_status (status);
-- ALTER TABLE assets_monitorler ADD INDEX idx_assigned (assigned_to);
-- ALTER TABLE assets_monitorler ADD INDEX idx_serial (serial_number);
-- ALTER TABLE assets_monitorler ADD INDEX idx_created_at (created_at);
-- ALTER TABLE assets_monitorler ADD INDEX idx_brand (brand);
-- ALTER TABLE assets_monitorler ADD INDEX idx_model (model);
