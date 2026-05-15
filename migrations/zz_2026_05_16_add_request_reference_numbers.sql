SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'document_requests'
              AND column_name = 'reference_number'
        ),
        'SELECT 1',
        'ALTER TABLE `document_requests` ADD COLUMN `reference_number` VARCHAR(50) NULL AFTER `or_number`'
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
              AND table_name = 'business_transactions'
              AND column_name = 'reference_number'
        ),
        'SELECT 1',
        'ALTER TABLE `business_transactions` ADD COLUMN `reference_number` VARCHAR(50) NULL AFTER `or_number`'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `document_requests`
SET `reference_number` = CONCAT(
    'REF-',
    DATE_FORMAT(COALESCE(`date_requested`, NOW()), '%Y%m%d'),
    '-DOC-',
    `id`
)
WHERE `reference_number` IS NULL OR `reference_number` = '';

UPDATE `business_transactions`
SET `reference_number` = CONCAT(
    'REF-',
    DATE_FORMAT(COALESCE(`application_date`, NOW()), '%Y%m%d'),
    '-BIZ-',
    `id`
)
WHERE `reference_number` IS NULL OR `reference_number` = '';

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'document_requests'
              AND index_name = 'uniq_document_requests_reference_number'
        ),
        'SELECT 1',
        'CREATE UNIQUE INDEX `uniq_document_requests_reference_number` ON `document_requests` (`reference_number`)'
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
              AND index_name = 'uniq_business_transactions_reference_number'
        ),
        'SELECT 1',
        'CREATE UNIQUE INDEX `uniq_business_transactions_reference_number` ON `business_transactions` (`reference_number`)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
