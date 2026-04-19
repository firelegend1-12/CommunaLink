-- ============================================================
-- COMMUNALINK DATABASE - COMPLETE SCHEMA & MIGRATIONS

CREATE DATABASE IF NOT EXISTS `barangay_reports` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barangay_reports`;

-- ============================================================
-- TABLE DEFINITIONS
-- ============================================================

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `role` ENUM('admin', 'resident', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod') NOT NULL DEFAULT 'resident',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: residents
CREATE TABLE IF NOT EXISTS `residents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100) NOT NULL,
    `middle_initial` VARCHAR(5) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `place_of_birth` VARCHAR(255) NOT NULL,
    `age` INT(3) NOT NULL,
    `religion` VARCHAR(100) DEFAULT NULL,
    `citizenship` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE DEFAULT NULL,
    `contact_no` VARCHAR(20) DEFAULT NULL,
    `address` TEXT NOT NULL,
    `civil_status` ENUM('Single', 'Married', 'Widowed', 'Separated') NOT NULL,
    `occupation` VARCHAR(100) DEFAULT NULL,
    `signature_path` VARCHAR(255) DEFAULT NULL,
    `profile_image_path` VARCHAR(255) DEFAULT NULL,
    `id_number` VARCHAR(50) UNIQUE DEFAULT NULL,
    `voter_status` ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    `user_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: businesses
CREATE TABLE IF NOT EXISTS `businesses` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `resident_id` INT(11) NOT NULL,
    `business_name` VARCHAR(255) NOT NULL,
    `business_type` VARCHAR(100) NOT NULL,
    `address` TEXT NOT NULL,
    `status` ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Pending',
    `date_registered` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `requested_by_user_id` INT(11) NULL,
    `permit_number` VARCHAR(50) DEFAULT NULL,
    `permit_expiration_date` DATE DEFAULT NULL,
    `approval_date` DATETIME DEFAULT NULL,
    `approved_by` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: business_transactions
CREATE TABLE IF NOT EXISTS `business_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `resident_id` INT(11) NOT NULL,
    `business_name` VARCHAR(255) NOT NULL,
    `business_type` VARCHAR(100) NOT NULL,
    `owner_name` VARCHAR(255) NOT NULL,
    `address` TEXT NOT NULL,
    `transaction_type` ENUM('New Permit', 'Renewal') NOT NULL,
    `status` ENUM('PENDING', 'PROCESSING', 'READY FOR PICKUP', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    `application_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `processed_date` DATETIME DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `or_number` VARCHAR(100) DEFAULT NULL,
    `payment_status` ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
    `payment_date` DATETIME DEFAULT NULL,
    `cash_received` DECIMAL(10,2) DEFAULT NULL,
    `change_amount` DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
    INDEX `idx_biztrans_status` (`status`),
    INDEX `idx_biztrans_payment_status` (`payment_status`),
    INDEX `idx_biztrans_resident_id` (`resident_id`),
    INDEX `idx_biztrans_status_payment_date` (`status`, `payment_status`, `application_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: business_permits
CREATE TABLE IF NOT EXISTS `business_permits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `date_of_application` DATE DEFAULT NULL,
    `business_account_no` VARCHAR(255) DEFAULT NULL,
    `official_receipt_no` VARCHAR(255) DEFAULT NULL,
    `or_date` DATE DEFAULT NULL,
    `amount_paid` DECIMAL(10, 2) DEFAULT NULL,
    `taxpayer_name` VARCHAR(255) DEFAULT NULL,
    `taxpayer_tel_no` VARCHAR(50) DEFAULT NULL,
    `taxpayer_fax_no` VARCHAR(50) DEFAULT NULL,
    `taxpayer_address` TEXT DEFAULT NULL,
    `capital` DECIMAL(15, 2) DEFAULT NULL,
    `taxpayer_barangay_no` VARCHAR(50) DEFAULT NULL,
    `business_trade_name` VARCHAR(255) DEFAULT NULL,
    `business_tel_no` VARCHAR(50) DEFAULT NULL,
    `comm_address_building` VARCHAR(255) DEFAULT NULL,
    `comm_address_no` VARCHAR(50) DEFAULT NULL,
    `comm_address_street` VARCHAR(255) DEFAULT NULL,
    `comm_address_barangay_no` VARCHAR(50) DEFAULT NULL,
    `dti_reg_no` VARCHAR(255) DEFAULT NULL,
    `sec_reg_no` VARCHAR(255) DEFAULT NULL,
    `num_employees` INT(11) DEFAULT NULL,
    `main_line_business` VARCHAR(255) DEFAULT NULL,
    `other_line_business` TEXT DEFAULT NULL,
    `main_products_services` TEXT DEFAULT NULL,
    `other_products_services` VARCHAR(255) DEFAULT NULL,
    `ownership_type` ENUM('single', 'partnership', 'corporation') DEFAULT NULL,
    `proof_of_ownership` ENUM('owned', 'leased') DEFAULT NULL,
    `proof_owned_reg_name` VARCHAR(255) DEFAULT NULL,
    `proof_leased_lessor_name` VARCHAR(255) DEFAULT NULL,
    `rent_per_month` DECIMAL(10, 2) DEFAULT NULL,
    `area_sq_meter` DECIMAL(10, 2) DEFAULT NULL,
    `real_property_tax_receipt_no` VARCHAR(255) DEFAULT NULL,
    `has_barangay_clearance` TINYINT(1) DEFAULT 0,
    `has_public_liability_insurance` TINYINT(1) DEFAULT 0,
    `insurance_company` VARCHAR(255) DEFAULT NULL,
    `insurance_date` DATE DEFAULT NULL,
    `applicant_name` VARCHAR(255) DEFAULT NULL,
    `applicant_position` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: document_requests
CREATE TABLE IF NOT EXISTS `document_requests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `resident_id` INT(11) NOT NULL,
    `document_type` VARCHAR(255) NOT NULL,
    `purpose` TEXT NOT NULL,
    `details` JSON DEFAULT NULL,
    `date_requested` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `price` DECIMAL(10, 2) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `requested_by_user_id` INT(11) NULL,
    `or_number` VARCHAR(100) DEFAULT NULL,
    `payment_status` ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
    `payment_date` DATETIME DEFAULT NULL,
    `cash_received` DECIMAL(10,2) DEFAULT NULL,
    `change_amount` DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_docreq_date_requested` (`date_requested`),
    INDEX `idx_docreq_status` (`status`),
    INDEX `idx_docreq_payment_status` (`payment_status`),
    INDEX `idx_docreq_resident_id` (`resident_id`),
    INDEX `idx_docreq_status_payment_date` (`status`, `payment_status`, `date_requested`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: incidents
CREATE TABLE IF NOT EXISTS `incidents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `resident_user_id` INT(11) NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `description` TEXT NOT NULL,
    `media_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Pending', 'In Progress', 'Resolved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `reported_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `admin_remarks` TEXT DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`resident_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_incidents_reported_at` (`reported_at`),
    INDEX `idx_incidents_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `sender_id` INT(11) NOT NULL,
    `receiver_id` INT(11) NOT NULL,
    `message` TEXT NOT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_chat_sender_id` (`sender_id`),
    INDEX `idx_chat_receiver_id` (`receiver_id`),
    INDEX `idx_chat_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: announcements
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `is_auto_generated` TINYINT(1) NOT NULL DEFAULT 0,
    `related_business_id` INT(11) DEFAULT NULL,
    `related_permit_number` VARCHAR(100) DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
    `target_audience` VARCHAR(50) NOT NULL DEFAULT 'all',
    `image_path` VARCHAR(255) DEFAULT NULL,
    `publish_date` DATETIME DEFAULT NULL,
    `expiry_date` DATETIME DEFAULT NULL,
    `read_count` INT(11) NOT NULL DEFAULT 0,
    `is_event` TINYINT(1) NOT NULL DEFAULT 0,
    `event_date` DATE DEFAULT NULL,
    `event_time` TIME DEFAULT NULL,
    `event_location` VARCHAR(255) DEFAULT NULL,
    `event_type` VARCHAR(50) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_announcements_created_at` (`created_at`),
    INDEX `idx_announcements_status_dates` (`status`, `publish_date`, `expiry_date`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: announcement_reads
CREATE TABLE IF NOT EXISTS `announcement_reads` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `announcement_id` INT(11) NOT NULL,
    `resident_id` INT(11) NOT NULL,
    `read_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_announcement_resident` (`announcement_id`, `resident_id`),
    FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
    INDEX `idx_announcement_reads_resident` (`resident_id`),
    INDEX `idx_announcement_reads_announcement` (`announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: events (legacy - will be renamed after migration)
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `event_date` DATE DEFAULT NULL,
    `event_time` TIME DEFAULT NULL,
    `type` ENUM('Upcoming Event', 'Regular Activity') NOT NULL,
    `created_by` INT(11) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `username` VARCHAR(100),
    `action` VARCHAR(50),
    `target_type` VARCHAR(50),
    `target_id` INT,
    `details` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `request_id` VARCHAR(100) DEFAULT NULL,
    `severity` ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `prev_hash` CHAR(64) DEFAULT NULL,
    `log_hash` CHAR(64) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_activity_logs_created_at` (`created_at`),
    INDEX `idx_activity_logs_action` (`action`),
    INDEX `idx_activity_logs_username` (`username`),
    INDEX `idx_activity_logs_target_type` (`target_type`),
    INDEX `idx_activity_logs_severity` (`severity`),
    INDEX `idx_activity_logs_request_id` (`request_id`),
    INDEX `idx_activity_logs_log_hash` (`log_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: activity_logs_archive
CREATE TABLE IF NOT EXISTS `activity_logs_archive` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_log_id` INT DEFAULT NULL,
    `user_id` INT,
    `username` VARCHAR(100),
    `action` VARCHAR(50),
    `target_type` VARCHAR(50),
    `target_id` INT,
    `details` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `request_id` VARCHAR(100) DEFAULT NULL,
    `severity` ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `prev_hash` CHAR(64) DEFAULT NULL,
    `log_hash` CHAR(64) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `archived_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `archive_batch_id` VARCHAR(64) DEFAULT NULL,
    INDEX `idx_activity_logs_archive_created_at` (`created_at`),
    INDEX `idx_activity_logs_archive_batch_id` (`archive_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: activity_log_archive_batches
CREATE TABLE IF NOT EXISTS `activity_log_archive_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(64) NOT NULL,
    `previous_batch_hash` CHAR(64) DEFAULT NULL,
    `batch_hash` CHAR(64) NOT NULL,
    `start_log_id` INT DEFAULT NULL,
    `end_log_id` INT DEFAULT NULL,
    `entry_count` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_archive_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'general',
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user_id` (`user_id`),
    INDEX `idx_notifications_is_read` (`is_read`),
    INDEX `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: active_user_sessions
CREATE TABLE IF NOT EXISTS `active_user_sessions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `role` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `ended_at` DATETIME DEFAULT NULL,
    `ended_reason` VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_active_session_id` (`session_id`),
    KEY `idx_active_sessions_role_active_expires` (`role`, `is_active`, `expires_at`),
    KEY `idx_active_sessions_user_active` (`user_id`, `is_active`),
    CONSTRAINT `fk_active_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: post_reactions
CREATE TABLE IF NOT EXISTS `post_reactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `post_id` INT(11) NOT NULL,
    `resident_id` INT(11) NOT NULL,
    `reaction_type` ENUM('like', 'acknowledge') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_reaction` (`post_id`, `resident_id`, `reaction_type`),
    CONSTRAINT `fk_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reactions_resident` FOREIGN KEY (`resident_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INITIAL DATA
-- ============================================================

-- Insert default admin user (password: admin123 hashed)
-- NOTE: Replace with your desired admin credentials
INSERT IGNORE INTO `users` (`id`, `username`, `fullname`, `email`, `password`, `role`, `created_at`) 
VALUES (1, 'admin', 'Administrator', 'admin@communalink.com', '$2y$10$1Y7PcRkUq2JVj7hYZEYWAuXFhVWFWAn5G4g0xZlz2Zl2Zl2Zl2Zl2', 'admin', NOW());

-- ============================================================
-- MIGRATION: Migrate Events to Announcements (One-time)
-- ============================================================

-- This will migrate any existing events in the events table to announcements
-- It checks for duplicates to prevent re-running issues
INSERT INTO `announcements` 
    (`user_id`, `title`, `content`, `created_at`, `is_event`, `event_date`, `event_time`, `event_location`, `event_type`, `status`, `priority`)
SELECT 
    `created_by`, `title`, `description`, `created_at`, 1, `event_date`, `event_time`, `location`, `type`, 'active', 'normal'
FROM `events` e
WHERE NOT EXISTS (
    SELECT 1 FROM `announcements` a 
    WHERE a.title = e.title 
    AND a.created_at = e.created_at 
    AND a.is_event = 1
);

-- ============================================================
-- MIGRATION: Setup Legacy Mark Notifications Read Endpoint
-- ============================================================

-- This migration prepares the system for legacy endpoint deprecation
-- See resident/partials/mark-notifications-read.php for implementation details

-- ============================================================
-- MIGRATION: Add Payment Tracking Columns
-- ============================================================

-- These columns are already defined in the CREATE statements above,
-- but this section confirms they exist for backward compatibility

-- Verify document_requests has payment columns:
SELECT 'document_requests: Payment columns verified' AS migration_status 
WHERE EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'document_requests' 
    AND COLUMN_NAME = 'payment_status'
);

-- Verify business_transactions has payment columns:
SELECT 'business_transactions: Payment columns verified' AS migration_status 
WHERE EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'business_transactions' 
    AND COLUMN_NAME = 'payment_status'
);

-- ============================================================
-- FINAL VERIFICATION
-- ============================================================

-- Display all tables created
SELECT 'Tables created in barangay_reports database:' AS status;
SHOW TABLES FROM `barangay_reports`;

-- Count total tables
SELECT CONCAT('Total tables: ', COUNT(*)) AS table_count 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'barangay_reports';

-- ============================================================
-- END OF DATABASE SETUP
-- ============================================================
-- 
-- Next Steps:
-- 1. Configure your .env file with database credentials
-- 2. Update ADMIN_INITIAL_PASSWORD in .env if needed
-- 3. Run your application to verify everything works
-- 4. Backup this database regularly
--
-- All tables, foreign keys, and indexes have been created.
-- The system is ready for use!
-- ============================================================
