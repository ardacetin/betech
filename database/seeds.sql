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

INSERT INTO locations (name, building, description)
SELECT seed.name, seed.building, seed.description
FROM (
    SELECT '101 Nolu Sınıf' AS name, 'Kavacık Kampüsü' AS building, 'Ana bina zemin kat derslik' AS description
    UNION ALL
    SELECT 'Sunucu Odası', 'Kavacık Kampüsü', 'Veri merkezi ve ağ altyapısı'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM locations LIMIT 1);

INSERT INTO users (name, email, role) VALUES
('Sistem Yöneticisi', 'admin@betech.local', 'super_admin')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    role = 'super_admin';

INSERT INTO personnel (external_id, name, email, department, provider, status) VALUES
('USR-001', 'Ayşe Yılmaz', 'ayse.yilmaz@betech.local', 'IT', 'local', 'active'),
('USR-002', 'Mehmet Demir', 'mehmet.demir@betech.local', 'Finans', 'local', 'active'),
('USR-003', 'Zeynep Kaya', 'zeynep.kaya@betech.local', 'İnsan Kaynakları', 'local', 'active'),
('USR-004', 'Can Öztürk', 'can.ozturk@betech.local', 'Operasyon', 'local', 'active'),
('USR-005', 'Elif Arslan', 'elif.arslan@betech.local', 'IT', 'local', 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    department = VALUES(department),
    provider = VALUES(provider);

INSERT INTO settings (`key`, value) VALUES
(
    'active_auth_driver',
    'local'
),
(
    'zimmet_template',
    '<p><strong>ZİMMET TESLİM FORMU</strong></p><p>Sayın {personnel_name},</p><p>Tarafınıza aşağıdaki IT varlığı zimmetlenmiştir:</p><ul><li><strong>Varlık Adı:</strong> {asset_name}</li><li><strong>Seri Numarası:</strong> {serial_number}</li><li><strong>Teslim Tarihi:</strong> {date}</li></ul><p>Varlığı kurumsal IT politikalarına uygun kullanacağınızı beyan edersiniz.</p>'
),
(
    'custom_fields',
    '[]'
),
(
    'ldap_host',
    ''
),
(
    'ldap_port',
    '389'
),
(
    'ldap_base_dn',
    ''
),
(
    'ldap_bind_dn',
    ''
),
(
    'ldap_bind_password',
    ''
),
(
    'ldap_use_tls',
    '0'
),
(
    'google_domain',
    ''
),
(
    'google_admin_email',
    ''
),
(
    'google_auth_mode',
    'service_account'
),
(
    'google_service_account_json',
    ''
),
(
    'google_oauth_token_json',
    ''
),
(
    'login_local_enabled',
    '1'
),
(
    'login_ldap_enabled',
    '0'
),
(
    'login_google_enabled',
    '0'
),
(
    'login_microsoft_enabled',
    '0'
),
(
    'google_sso_client_id',
    ''
),
(
    'google_sso_client_secret',
    ''
),
(
    'azure_sso_tenant_id',
    ''
),
(
    'azure_sso_client_id',
    ''
),
(
    'azure_sso_client_secret',
    ''
)
ON DUPLICATE KEY UPDATE
    value = IF(settings.value IS NULL OR settings.value = '', VALUES(value), settings.value);
