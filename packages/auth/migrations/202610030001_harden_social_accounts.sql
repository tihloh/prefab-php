ALTER TABLE prefab_auth_social_accounts
    ADD UNIQUE KEY uq_prefab_auth_social_user_provider (user_id, provider);
