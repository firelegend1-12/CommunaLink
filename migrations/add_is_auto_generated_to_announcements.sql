-- ============================================================
-- Migration: Add missing columns to announcements table
-- Uses information_schema guards so it is safe to re-run.
-- ============================================================

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'announcements'
              AND column_name = 'status'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''active'' AFTER `content`'
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
              AND table_name = 'announcements'
              AND column_name = 'priority'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `priority` VARCHAR(20) NOT NULL DEFAULT ''normal'' AFTER `status`'
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
              AND table_name = 'announcements'
              AND column_name = 'is_auto_generated'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `is_auto_generated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_id`'
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
              AND table_name = 'announcements'
              AND column_name = 'related_business_id'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `related_business_id` INT(11) DEFAULT NULL AFTER `is_auto_generated`'
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
              AND table_name = 'announcements'
              AND column_name = 'related_permit_number'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `related_permit_number` VARCHAR(100) DEFAULT NULL AFTER `related_business_id`'
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
              AND table_name = 'announcements'
              AND column_name = 'target_audience'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `target_audience` VARCHAR(50) NOT NULL DEFAULT ''all'' AFTER `priority`'
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
              AND table_name = 'announcements'
              AND column_name = 'publish_date'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `publish_date` DATETIME DEFAULT NULL AFTER `target_audience`'
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
              AND table_name = 'announcements'
              AND column_name = 'expiry_date'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `expiry_date` DATETIME DEFAULT NULL AFTER `publish_date`'
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
              AND table_name = 'announcements'
              AND column_name = 'read_count'
        ),
        'SELECT 1',
        'ALTER TABLE `announcements` ADD COLUMN `read_count` INT(11) NOT NULL DEFAULT 0 AFTER `expiry_date`'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
