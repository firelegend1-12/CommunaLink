-- Phase 2: normalize request status values and add targeted lookup indexes.

ALTER TABLE `document_requests`
MODIFY `status` ENUM('Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled', 'Processing', 'Ready for Pickup') NOT NULL DEFAULT 'Pending';

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
MODIFY `status` ENUM('Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled', 'Processing', 'Ready for Pickup', 'PENDING', 'APPROVED', 'COMPLETED', 'REJECTED', 'CANCELLED', 'PROCESSING', 'READY FOR PICKUP') NOT NULL DEFAULT 'Pending';

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

CREATE INDEX IF NOT EXISTS `idx_incidents_resident_reported` ON `incidents` (`resident_user_id`, `reported_at`);
CREATE INDEX IF NOT EXISTS `idx_notifications_user_read_created` ON `notifications` (`user_id`, `is_read`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_document_requests_requested_date` ON `document_requests` (`requested_by_user_id`, `date_requested`);
CREATE INDEX IF NOT EXISTS `idx_business_transactions_resident_application` ON `business_transactions` (`resident_id`, `application_date`);
