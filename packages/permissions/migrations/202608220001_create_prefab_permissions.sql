CREATE TABLE IF NOT EXISTS prefab_subject_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_type VARCHAR(32) NOT NULL,
    subject_id VARCHAR(191) NOT NULL,
    permissions JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_prefab_permission_subject (subject_type, subject_id),
    INDEX idx_prefab_permission_subject_type (subject_type)
);
