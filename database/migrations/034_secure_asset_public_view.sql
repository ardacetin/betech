-- Secure public QR asset view: opaque revocable tokens (one active link per asset)

CREATE TABLE IF NOT EXISTS asset_public_view_tokens (
    asset_id BIGINT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rotated_at DATETIME NULL DEFAULT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (asset_id),
    UNIQUE KEY uq_asset_public_view_tokens_token (token),
    KEY idx_asset_public_view_tokens_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
