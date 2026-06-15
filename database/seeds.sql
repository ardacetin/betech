-- Betech default category seeds with hybrid JSON field definitions

INSERT INTO categories (name, slug, fields) VALUES
(
    'Dizüstü Bilgisayar',
    'dizustu-bilgisayar',
    JSON_ARRAY(
        JSON_OBJECT('name', 'ram', 'label', 'RAM', 'label_en', 'RAM', 'type', 'text'),
        JSON_OBJECT('name', 'cpu', 'label', 'CPU', 'label_en', 'CPU', 'type', 'text'),
        JSON_OBJECT('name', 'storage', 'label', 'Depolama', 'label_en', 'Storage', 'type', 'text')
    )
),
(
    'Ağ Anahtarı (Switch)',
    'ag-anahtari-switch',
    JSON_ARRAY(
        JSON_OBJECT('name', 'ports', 'label', 'Port Sayısı', 'label_en', 'Port Count', 'type', 'number'),
        JSON_OBJECT('name', 'ip_address', 'label', 'IP Adresi', 'label_en', 'IP Address', 'type', 'text')
    )
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    fields = IF(categories.fields IS NULL, VALUES(fields), categories.fields);
