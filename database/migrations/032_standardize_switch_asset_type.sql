-- Standardize switch asset type naming: Switchler / switchler

UPDATE asset_types
SET name = 'Switchler', slug = 'switchler'
WHERE slug IN ('ag_anahtarlari', 'ag-anahtarlari', 'ag-anahtari-switch');

UPDATE assets_global_registry
SET asset_type = 'switchler'
WHERE asset_type IN ('ag_anahtarlari', 'ag-anahtarlari', 'ag-anahtari-switch');
