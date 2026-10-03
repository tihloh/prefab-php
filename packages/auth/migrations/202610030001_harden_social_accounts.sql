ALTER TABLE prefab_auth_social_accounts
    ADD COLUMN email_verified TINYINT(1) NULL AFTER email,
    ADD COLUMN username VARCHAR(191) NULL AFTER email_verified,
    ADD COLUMN display_name VARCHAR(255) NULL AFTER username,
    ADD COLUMN avatar_url TEXT NULL AFTER display_name,
    ADD COLUMN last_login_at DATETIME NULL AFTER expires_at,
    ADD UNIQUE KEY uq_prefab_auth_social_user_provider (user_id, provider);
