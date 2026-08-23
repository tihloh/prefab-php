CREATE TABLE IF NOT EXISTS prefab_auth_social_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(191) NOT NULL,
    provider VARCHAR(64) NOT NULL,
    provider_user_id VARCHAR(191) NOT NULL,
    email VARCHAR(255) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_prefab_auth_social_provider_user (provider, provider_user_id),
    INDEX idx_prefab_auth_social_user (user_id)
);

CREATE TABLE IF NOT EXISTS prefab_auth_password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(191) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_prefab_auth_reset_user (user_id),
    UNIQUE KEY uq_prefab_auth_reset_token (token_hash)
);
