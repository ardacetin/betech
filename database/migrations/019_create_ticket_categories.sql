CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    color_code VARCHAR(7) NOT NULL DEFAULT '#6366f1',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ticket_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ticket_categories (name, color_code) VALUES
    ('Ağ Sorunları', '#3b82f6'),
    ('Yazılım Hatası', '#8b5cf6'),
    ('Donanım Talebi', '#f59e0b');
