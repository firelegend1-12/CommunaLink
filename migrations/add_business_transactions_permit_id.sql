SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'business_transactions'
              AND column_name = 'permit_id'
        ),
        'SELECT 1',
        'ALTER TABLE `business_transactions` ADD COLUMN `permit_id` INT(11) DEFAULT NULL AFTER `resident_id`'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'business_transactions'
              AND index_name = 'idx_business_transactions_permit_id'
        ),
        'SELECT 1',
        'CREATE INDEX `idx_business_transactions_permit_id` ON `business_transactions` (`permit_id`)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
