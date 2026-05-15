-- Add rejection reason storage for incident reports

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'incidents'
              AND column_name = 'rejection_reason'
        ),
        'SELECT 1',
        'ALTER TABLE `incidents` ADD COLUMN `rejection_reason` TEXT DEFAULT NULL AFTER `admin_remarks`'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
