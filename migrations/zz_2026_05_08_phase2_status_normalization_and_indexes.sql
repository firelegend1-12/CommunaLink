-- Phase 2: normalize request status values and add targeted lookup indexes.

ALTER TABLE `document_requests`
MODIFY `status` VARCHAR(50) NOT NULL;

UPDATE `document_requests`
SET `status` = CASE UPPER(`status`)
    WHEN 'PROCESSING' THEN 'Approved'
    WHEN 'READY FOR PICKUP' THEN 'Approved'
    WHEN 'PENDING' THEN 'Pending'
    WHEN 'APPROVED' THEN 'Approved'
    WHEN 'COMPLETED' THEN 'Completed'
    WHEN 'REJECTED' THEN 'Rejected'
    WHEN 'CANCELLED' THEN 'Cancelled'
    ELSE `status`
END;

ALTER TABLE `document_requests`
MODIFY `status` ENUM('Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending';

ALTER TABLE `business_transactions`
MODIFY `status` VARCHAR(50) NOT NULL;

UPDATE `business_transactions`
SET `status` = CASE UPPER(`status`)
    WHEN 'PENDING' THEN 'Pending'
    WHEN 'PROCESSING' THEN 'Approved'
    WHEN 'READY FOR PICKUP' THEN 'Approved'
    WHEN 'APPROVED' THEN 'Approved'
    WHEN 'COMPLETED' THEN 'Completed'
    WHEN 'REJECTED' THEN 'Rejected'
    WHEN 'CANCELLED' THEN 'Cancelled'
    ELSE `status`
END;

ALTER TABLE `business_transactions`
MODIFY `status` ENUM('Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending';

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'incidents'
              AND index_name = 'idx_incidents_resident_reported'
        ),
        'SELECT 1',
        'CREATE INDEX `idx_incidents_resident_reported` ON `incidents` (`resident_user_id`, `reported_at`)'
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
              AND table_name = 'notifications'
              AND index_name = 'idx_notifications_user_read_created'
        ),
        'SELECT 1',
        'CREATE INDEX `idx_notifications_user_read_created` ON `notifications` (`user_id`, `is_read`, `created_at`)'
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
              AND table_name = 'document_requests'
              AND index_name = 'idx_document_requests_requested_date'
        ),
        'SELECT 1',
        'CREATE INDEX `idx_document_requests_requested_date` ON `document_requests` (`requested_by_user_id`, `date_requested`)'
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
              AND index_name = 'idx_business_transactions_resident_application'
        ),
        'SELECT 1',
        'CREATE INDEX `idx_business_transactions_resident_application` ON `business_transactions` (`resident_id`, `application_date`)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
