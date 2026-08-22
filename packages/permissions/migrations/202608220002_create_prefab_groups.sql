CREATE TABLE IF NOT EXISTS prefab_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prefab_group_name (name)
);

CREATE TABLE IF NOT EXISTS prefab_user_groups (
    user_id VARCHAR(191) NOT NULL,
    group_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, group_id),
    INDEX idx_prefab_user_groups_group (group_id),
    CONSTRAINT fk_prefab_user_groups_group
        FOREIGN KEY (group_id) REFERENCES prefab_groups(id)
        ON DELETE CASCADE
);
