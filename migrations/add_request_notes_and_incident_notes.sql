SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'business_transactions'
              AND column_name = 'admin_notes'
        ),
        'SELECT 1',
        'ALTER TABLE `business_transactions` ADD COLUMN `admin_notes` TEXT DEFAULT NULL AFTER `remarks`'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'document_requests'
              AND column_name = 'admin_notes'
        ),
        'SELECT 1',
        'ALTER TABLE `document_requests` ADD COLUMN `admin_notes` TEXT DEFAULT NULL AFTER `remarks`'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `incident_notes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `incident_id` INT(11) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `note` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_incident_notes_incident_id` (`incident_id`),
    KEY `idx_incident_notes_user_id` (`user_id`),
    CONSTRAINT `fk_incident_notes_incident`
        FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_incident_notes_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `document_requests`
SET `price` = CASE
    WHEN LOWER(TRIM(`document_type`)) IN ('certificate of indigency', 'certificate of indigency (special)') THEN 0.00
    ELSE 50.00
END;
