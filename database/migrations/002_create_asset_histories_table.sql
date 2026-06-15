-- Asset lifecycle audit log table for existing Betech installations

CREATE TABLE IF NOT EXISTS asset_histories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    target_user_id BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_histories_asset_id (asset_id),
    KEY idx_asset_histories_action (action),
    KEY idx_asset_histories_created_at (created_at),
    CONSTRAINT fk_asset_histories_asset_id
        FOREIGN KEY (asset_id) REFERENCES assets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_histories_user_id
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_asset_histories_target_user_id
        FOREIGN KEY (target_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
