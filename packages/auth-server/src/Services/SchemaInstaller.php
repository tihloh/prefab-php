<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Services;

use PDO;

final class SchemaInstaller
{
    public static function install(PDO $pdo): void
    {
        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }
    }

    /** @return string[] */
    public static function statements(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS prefab_auth_server_clients (
                client_id VARCHAR(191) PRIMARY KEY,
                client_secret_hash VARCHAR(255) NULL,
                name VARCHAR(255) NOT NULL,
                redirect_uris TEXT NOT NULL,
                allowed_scopes TEXT NOT NULL,
                grant_types TEXT NOT NULL,
                confidential INTEGER NOT NULL DEFAULT 1,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )',
            'CREATE TABLE IF NOT EXISTS prefab_auth_server_auth_codes (
                id VARCHAR(191) PRIMARY KEY,
                user_id VARCHAR(191) NOT NULL,
                client_id VARCHAR(191) NOT NULL,
                redirect_uri TEXT NULL,
                scopes TEXT NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            )',
            'CREATE TABLE IF NOT EXISTS prefab_auth_server_access_tokens (
                id VARCHAR(191) PRIMARY KEY,
                user_id VARCHAR(191) NULL,
                client_id VARCHAR(191) NOT NULL,
                scopes TEXT NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            )',
            'CREATE TABLE IF NOT EXISTS prefab_auth_server_refresh_tokens (
                id VARCHAR(191) PRIMARY KEY,
                access_token_id VARCHAR(191) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL
            )',
            'CREATE TABLE IF NOT EXISTS prefab_auth_server_consents (
                user_id VARCHAR(191) NOT NULL,
                client_id VARCHAR(191) NOT NULL,
                scopes TEXT NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE (user_id, client_id)
            )',
        ];
    }
}
