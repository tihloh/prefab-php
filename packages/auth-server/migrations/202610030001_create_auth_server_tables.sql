CREATE TABLE IF NOT EXISTS prefab_auth_server_clients (
    client_id VARCHAR(191) PRIMARY KEY,
    client_secret_hash VARCHAR(255) NULL,
    name VARCHAR(255) NOT NULL,
    redirect_uris TEXT NOT NULL,
    allowed_scopes TEXT NOT NULL,
    grant_types TEXT NOT NULL,
    confidential TINYINT(1) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS prefab_auth_server_auth_codes (
    id VARCHAR(191) PRIMARY KEY,
    user_id VARCHAR(191) NOT NULL,
    client_id VARCHAR(191) NOT NULL,
    redirect_uri TEXT NULL,
    scopes TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS prefab_auth_server_access_tokens (
    id VARCHAR(191) PRIMARY KEY,
    user_id VARCHAR(191) NULL,
    client_id VARCHAR(191) NOT NULL,
    scopes TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS prefab_auth_server_refresh_tokens (
    id VARCHAR(191) PRIMARY KEY,
    access_token_id VARCHAR(191) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS prefab_auth_server_consents (
    user_id VARCHAR(191) NOT NULL,
    client_id VARCHAR(191) NOT NULL,
    scopes TEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_prefab_auth_server_consent (user_id, client_id)
);
