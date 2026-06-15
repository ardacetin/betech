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

INSERT INTO users (external_id, name, email, department) VALUES
('USR-001', 'Ayşe Yılmaz', 'ayse.yilmaz@betech.local', 'IT'),
('USR-002', 'Mehmet Demir', 'mehmet.demir@betech.local', 'Finans'),
('USR-003', 'Zeynep Kaya', 'zeynep.kaya@betech.local', 'İnsan Kaynakları'),
('USR-004', 'Can Öztürk', 'can.ozturk@betech.local', 'Operasyon'),
('USR-005', 'Elif Arslan', 'elif.arslan@betech.local', 'IT')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    department = VALUES(department);

INSERT INTO settings (`key`, value) VALUES
(
    'active_auth_driver',
    'local'
),
(
    'zimmet_template',
    'ZİMMET TESLİM FORMU\n\nSayın {personnel_name},\n\nTarafınıza aşağıdaki IT varlığı zimmetlenmiştir:\n\nVarlık Adı: {asset_name}\nSeri Numarası: {serial_number}\nTeslim Tarihi: {date}\n\nVarlığı kurumsal IT politikalarına uygun kullanacağınızı beyan edersiniz.'
),
(
    'custom_fields',
    '[]'
)
ON DUPLICATE KEY UPDATE
    value = IF(settings.value IS NULL OR settings.value = '', VALUES(value), settings.value);
