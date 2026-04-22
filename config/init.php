<?php
/**
 * Database Initialization File
 * Establishes a PDO connection and creates necessary tables if they don't exist.
 */

require_once __DIR__ . '/../includes/functions.php';

function configure_session_cookie_security() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Cloud environments might require a specific session save path (e.g., /tmp for App Engine)
    $session_path = '';
    if (function_exists('env')) {
        $session_path = env('SESSION_SAVE_PATH', '');
    } else {
        $session_path = getenv('SESSION_SAVE_PATH') ?: ($_ENV['SESSION_SAVE_PATH'] ?? $_SERVER['SESSION_SAVE_PATH'] ?? '');
    }
    
    if (!empty($session_path)) {
        if (!is_dir($session_path)) {
            @mkdir($session_path, 0777, true);
        }
        session_save_path($session_path);
    }

    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strpos(strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']), 'https') !== false)
    );

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $is_https ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    configure_session_cookie_security();
    session_start();
}

require_once __DIR__ . '/database.php'; // This file should instantiate $pdo
require_once __DIR__ . '/../includes/csrf.php'; // CSRF protection
require_once __DIR__ . '/../includes/password_security.php'; // Password security
require_once __DIR__ . '/../includes/rate_limiter.php'; // Rate limiting
require_once __DIR__ . '/../includes/input_validator.php'; // Input validation & sanitization
require_once __DIR__ . '/../includes/security_headers.php'; // Security headers
require_once __DIR__ . '/../includes/database_optimizer.php'; // Database optimization
require_once __DIR__ . '/../includes/cache_manager.php'; // Caching system
require_once __DIR__ . '/../includes/query_optimizer.php'; // Query optimization


function fail_fast_startup_contract_error(array $errors) {
    $message = 'Startup configuration contract validation failed: ' . implode(' | ', $errors);
    error_log($message);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    exit('Application configuration error. Check server logs.');
}

function validate_startup_config_contract() {
    $app_env = strtolower(trim((string) env('APP_ENV', 'production')));
    $is_production_like = in_array($app_env, ['production', 'prod', 'staging'], true);
    if (!$is_production_like) {
        return;
    }

    $errors = [];

    if (trim((string) env('DB_NAME', '')) === '') {
        $errors[] = 'DB_NAME is required in production-like environments';
    }

    if (trim((string) env('DB_USER', '')) === '') {
        $errors[] = 'DB_USER is required in production-like environments';
    }

    $dbSocket = trim((string) env('DB_SOCKET', ''));
    $dbHost = trim((string) env('DB_HOST', ''));
    $dbPort = trim((string) env('DB_PORT', ''));

    if ($dbSocket === '') {
        if ($dbHost === '') {
            $errors[] = 'DB_HOST is required when DB_SOCKET is not configured';
        }
        if ($dbPort === '' || !ctype_digit($dbPort)) {
            $errors[] = 'DB_PORT must be a numeric value when DB_SOCKET is not configured';
        }
    }

    $useCloudStorage = strtolower(trim((string) env('USE_CLOUD_STORAGE', 'false')));
    if (in_array($useCloudStorage, ['1', 'true', 'yes', 'on'], true) && trim((string) env('STORAGE_BUCKET', '')) === '') {
        $errors[] = 'STORAGE_BUCKET is required when USE_CLOUD_STORAGE=true';
    }

    $permitToken = trim((string) env('PERMIT_CHECK_SCHEDULER_TOKEN', ''));
    $sessionCleanupToken = trim((string) env('SESSION_CLEANUP_SCHEDULER_TOKEN', ''));
    if ($permitToken === '') {
        $errors[] = 'PERMIT_CHECK_SCHEDULER_TOKEN is required in production-like environments';
    }
    if ($sessionCleanupToken === '') {
        $errors[] = 'SESSION_CLEANUP_SCHEDULER_TOKEN is required in production-like environments';
    }

    if (!empty($errors)) {
        fail_fast_startup_contract_error($errors);
    }
}


if (function_exists('apply_app_timezone')) {
    apply_app_timezone();
} else {
    date_default_timezone_set('Asia/Manila');
}

validate_startup_config_contract();

try {
    $app_env = strtolower(trim((string) env('APP_ENV', 'production')));
    $is_production_like = in_array($app_env, ['production', 'prod', 'staging'], true);

    if ($is_production_like) {
        // Never expose debug output in production-like environments.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    $auto_db_schema_sync = strtolower((string) env('AUTO_DB_SCHEMA_SYNC', 'true')) === 'true';
    if ($is_production_like && $auto_db_schema_sync) {
        error_log('AUTO_DB_SCHEMA_SYNC was enabled in a production-like environment; forcing disabled mode.');
        $auto_db_schema_sync = false;
    }

    if (!$auto_db_schema_sync) {
        return;
    }

    // Table creation queries
    $queries = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `fullname` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `role` ENUM('admin', 'resident', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod') NOT NULL DEFAULT 'resident',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `last_login` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `residents` (
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
            `signature_path` VARCHAR(255) DEFAULT NULL,
            `profile_image_path` VARCHAR(255) DEFAULT NULL,
            `id_number` VARCHAR(50) UNIQUE DEFAULT NULL,
            `voter_status` ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
            `user_id` INT(11) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `businesses` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `resident_id` INT(11) NOT NULL,
            `business_name` VARCHAR(255) NOT NULL,
            `business_type` VARCHAR(100) NOT NULL,
            `address` TEXT NOT NULL,
            `status` ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Pending',
            `date_registered` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `requested_by_user_id` INT(11) NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `business_transactions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `resident_id` INT(11) NOT NULL,
            `business_name` VARCHAR(255) NOT NULL,
            `business_type` VARCHAR(100) NOT NULL,
            `owner_name` VARCHAR(255) NOT NULL,
            `address` TEXT NOT NULL,
            `transaction_type` ENUM('New Permit', 'Renewal') NOT NULL,
            `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
            `application_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `processed_date` DATETIME DEFAULT NULL,
            `remarks` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `business_permits` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `document_requests` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `resident_id` INT(11) NOT NULL,
            `document_type` VARCHAR(255) NOT NULL,
            `purpose` TEXT NOT NULL,
            `details` JSON,
            `date_requested` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
            `price` DECIMAL(10, 2) DEFAULT NULL,
            `remarks` TEXT DEFAULT NULL,
            `requested_by_user_id` INT(11) NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `incidents` (
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
            PRIMARY KEY (`id`),
            FOREIGN KEY (`resident_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `announcements` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` TEXT NOT NULL,
            `image_path` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `announcement_reads` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `announcement_id` INT(11) NOT NULL,
            `resident_id` INT(11) NOT NULL,
            `read_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_announcement_resident` (`announcement_id`, `resident_id`),
            FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `events` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Email verification OTPs table
        "CREATE TABLE IF NOT EXISTS `email_verification_otps` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(100) NOT NULL,
            `otp_code` VARCHAR(255) NOT NULL,
            `registration_data` TEXT NOT NULL,
            `attempts` INT(3) NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_otp_email` (`email`),
            INDEX `idx_otp_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Add activity_logs table
        "CREATE TABLE IF NOT EXISTS `activity_logs` (
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
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `activity_logs_archive` (
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
            `archive_batch_id` VARCHAR(64) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `activity_log_archive_batches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `batch_id` VARCHAR(64) NOT NULL,
            `previous_batch_hash` CHAR(64) DEFAULT NULL,
            `batch_hash` CHAR(64) NOT NULL,
            `start_log_id` INT DEFAULT NULL,
            `end_log_id` INT DEFAULT NULL,
            `entry_count` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_archive_batch_id` (`batch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
 
        "CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `type` VARCHAR(50) DEFAULT 'general',
            `link` VARCHAR(255) DEFAULT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `active_user_sessions` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    // Execute all table creation queries
    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    // --- Schema Migration for residents table ---
    $resident_columns = [
        'occupation' => "ADD COLUMN `occupation` VARCHAR(100) DEFAULT NULL AFTER `civil_status`",
        'user_id' => "ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `voter_status`"
    ];

    foreach ($resident_columns as $column => $alter_statement) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `residents` LIKE '{$column}'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `residents` " . $alter_statement);
        }
    }

    // Add foreign key for user_id in residents if it doesn't exist
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'residents' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `residents` ADD CONSTRAINT `fk_resident_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;");
    }

    // --- Schema Migration for document_requests ---
    $doc_columns = [
        'document_type' => "ADD COLUMN `document_type` VARCHAR(255) NOT NULL AFTER `resident_id`",
        'purpose' => "ADD COLUMN `purpose` TEXT NOT NULL AFTER `document_type`",
        'details' => "ADD COLUMN `details` JSON AFTER `purpose`",
        'status' => "ADD COLUMN `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending' AFTER `date_requested`",
        'price' => "ADD COLUMN `price` DECIMAL(10, 2) DEFAULT NULL AFTER `status`",
        'remarks' => "ADD COLUMN `remarks` TEXT DEFAULT NULL AFTER `price`",
        'requested_by_user_id' => "ADD COLUMN `requested_by_user_id` INT(11) NULL AFTER `remarks`"
    ];

    foreach ($doc_columns as $column => $alter_statement) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `document_requests` LIKE '{$column}'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `document_requests` " . $alter_statement);
        }
    }
    
    // Add foreign key constraint to document_requests after adding the column
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'document_requests' AND COLUMN_NAME = 'requested_by_user_id' AND REFERENCED_TABLE_NAME = 'users'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `document_requests` ADD CONSTRAINT `fk_requested_by_user` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;");
    }

    // Ensure Cancelled status exists in document_requests status enum
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `document_requests` LIKE 'status'");
        if ($stmt && $stmt->rowCount() > 0) {
            $column_info = $stmt->fetch();
            $type = $column_info['Type'] ?? '';
            if (strpos($type, 'Cancelled') === false) {
                $pdo->exec("ALTER TABLE `document_requests` MODIFY `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending';");
            }
        }
    } catch (Exception $e) {
        // Ignore errors if table does not exist yet
    }

    // --- Schema Migration for business_transactions ---
    // Add PROCESSING and READY FOR PICKUP status to business_transactions if they don't exist
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `business_transactions` LIKE 'status'");
        if ($stmt && $stmt->rowCount() > 0) {
            $column_info = $stmt->fetch();
            $type = $column_info['Type'] ?? '';
            $needs_update = false;
            if (strpos($type, 'PROCESSING') === false) {
                $needs_update = true;
            }
            if (strpos($type, 'READY FOR PICKUP') === false) {
                $needs_update = true;
            }
            if ($needs_update) {
                $pdo->exec("ALTER TABLE `business_transactions` MODIFY `status` ENUM('PENDING', 'PROCESSING', 'READY FOR PICKUP', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING';");
            }
        }
    } catch (Exception $e) {
        // Ignore errors if table doesn't exist yet
    }

    // --- Schema Migration for announcements ---
    try {
        $announcement_columns = [
            'status' => "ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `content`",
            'priority' => "ADD COLUMN `priority` VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER `status`",
            'is_auto_generated' => "ADD COLUMN `is_auto_generated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_id`",
            'related_business_id' => "ADD COLUMN `related_business_id` INT(11) DEFAULT NULL AFTER `is_auto_generated`",
            'related_permit_number' => "ADD COLUMN `related_permit_number` VARCHAR(100) DEFAULT NULL AFTER `related_business_id`",
            'target_audience' => "ADD COLUMN `target_audience` VARCHAR(50) NOT NULL DEFAULT 'all' AFTER `priority`",
            'publish_date' => "ADD COLUMN `publish_date` DATETIME DEFAULT NULL AFTER `target_audience`",
            'expiry_date' => "ADD COLUMN `expiry_date` DATETIME DEFAULT NULL AFTER `publish_date`",
            'read_count' => "ADD COLUMN `read_count` INT(11) NOT NULL DEFAULT 0 AFTER `expiry_date`",
            'is_event' => "ADD COLUMN `is_event` TINYINT(1) NOT NULL DEFAULT 0 AFTER `read_count`",
            'event_date' => "ADD COLUMN `event_date` DATE DEFAULT NULL AFTER `is_event`",
            'event_time' => "ADD COLUMN `event_time` TIME DEFAULT NULL AFTER `event_date`",
            'event_location' => "ADD COLUMN `event_location` VARCHAR(255) DEFAULT NULL AFTER `event_time`",
            'event_type' => "ADD COLUMN `event_type` VARCHAR(50) DEFAULT NULL AFTER `event_location`"
        ];

        foreach ($announcement_columns as $column => $alter_statement) {
            $stmt = $pdo->query("SHOW COLUMNS FROM `announcements` LIKE '{$column}'");
            if ($stmt && $stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `announcements` " . $alter_statement);
            }
        }

        // --- Data Migration: Events to Announcements (One-time) ---
        $stmt = $pdo->query("SHOW TABLES LIKE 'events'");
        if ($stmt && $stmt->rowCount() > 0) {
            // Check if there's any data in events table
            $count = $pdo->query("SELECT COUNT(*) FROM `events`")->fetchColumn();
            if ($count > 0) {
                // Migrate events to announcements
                // Check for existing records by title and date to prevent duplicates if someone re-runs this
                $pdo->exec("INSERT INTO `announcements` (user_id, title, content, created_at, is_event, event_date, event_time, event_location, event_type, status, priority)
                            SELECT created_by, title, description, created_at, 1, event_date, event_time, location, type, 'active', 'normal'
                            FROM `events` e
                            WHERE NOT EXISTS (
                                SELECT 1 FROM `announcements` a 
                                WHERE a.title = e.title 
                                AND a.created_at = e.created_at 
                                AND a.is_event = 1
                            )");
                
                // After successful migration, rename the table to prevent re-runs
                $pdo->exec("RENAME TABLE `events` TO `events_migrated` ");
            }
        }
    } catch (Exception $e) {
        // Log error if needed: error_log("Migration error: " . $e->getMessage());
    }

    // --- Schema Migration for businesses (permit fields) ---
    try {
        $biz_columns = [
            'permit_number' => "ADD COLUMN `permit_number` VARCHAR(50) DEFAULT NULL AFTER `status`",
            'permit_expiration_date' => "ADD COLUMN `permit_expiration_date` DATE DEFAULT NULL AFTER `permit_number`",
            'approval_date' => "ADD COLUMN `approval_date` DATETIME DEFAULT NULL AFTER `permit_expiration_date`",
            'approved_by' => "ADD COLUMN `approved_by` INT(11) DEFAULT NULL AFTER `approval_date`"
        ];

        foreach ($biz_columns as $column => $alter_statement) {
            $stmt = $pdo->query("SHOW COLUMNS FROM `businesses` LIKE '{$column}'");
            if ($stmt && $stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `businesses` " . $alter_statement);
            }
        }

        // Add foreign key for approved_by if it doesn't exist
        $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'businesses' AND COLUMN_NAME = 'approved_by' AND REFERENCED_TABLE_NAME = 'users'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `businesses` ADD CONSTRAINT `fk_businesses_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;");
        }
    } catch (Exception $e) {
        // Ignore if table not present; creation above will add on fresh installs
    }

    // Handle legacy `admin_users` table if it exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("RENAME TABLE `admin_users` TO `users`;");
    }

    // --- Schema Migration and Admin Creation ---

    // Check if 'role' column exists. If not, this is a legacy `users` table that needs migration.
    $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        // Add role and last_login columns
        $pdo->exec("ALTER TABLE `users` 
                ADD COLUMN `role` ENUM('admin', 'resident', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod') NOT NULL DEFAULT 'admin' AFTER `email`,
                    ADD COLUMN `last_login` DATETIME DEFAULT NULL AFTER `created_at`;");

        // Migrate data from old `is_admin` column if it exists
        $stmt_col_exists = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'is_admin'");
        if ($stmt_col_exists->rowCount() > 0) {
            $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `is_admin` = TRUE;");
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `is_admin`;");
        }
    } else {
        // Normalize legacy role values before constraining to the consolidated enum.
        $pdo->exec("UPDATE `users` SET `role` = 'barangay-kagawad' WHERE `role` = 'kagawad';");
        $pdo->exec("UPDATE `users` SET `role` = 'barangay-officials' WHERE `role` IN ('official', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer');");

        // If role column exists, modify it to the canonical consolidated role list.
        $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('admin', 'resident', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod') NOT NULL DEFAULT 'resident';");

        // Keep active session role labels aligned for live-session metrics and policy checks.
        $pdo->exec("UPDATE `active_user_sessions` SET `role` = 'barangay-kagawad' WHERE `role` = 'kagawad';");
        $pdo->exec("UPDATE `active_user_sessions` SET `role` = 'barangay-officials' WHERE `role` IN ('official', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer');");
    }
    
    // Check for admin and create if it doesn't exist
    $admin_email = 'admin@communalink.com';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    $admin_initial_password = trim((string) env('ADMIN_INITIAL_PASSWORD', ''));
    if ($stmt->rowCount() == 0) {
        if ($admin_initial_password === '') {
            $app_env = strtolower((string) env('APP_ENV', 'production'));
            $already_logged = isset($_SESSION['admin_seed_warning_logged']) && $_SESSION['admin_seed_warning_logged'] === true;
            if ($app_env !== 'production' && !$already_logged) {
                error_log('Admin seed skipped: required environment variable ADMIN_INITIAL_PASSWORD is not configured.');
                $_SESSION['admin_seed_warning_logged'] = true;
            }
        } else {
            $admin_username = 'admin';
            $admin_fullname = 'Administrator';
            $hashed_password = password_hash($admin_initial_password, PASSWORD_DEFAULT);

            $insert_stmt = $pdo->prepare(
                "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, 'admin')"
            );
            $insert_stmt->execute([$admin_username, $admin_fullname, $admin_email, $hashed_password]);
        }
    }
    // Note: If admin account already exists, it keeps its existing password

    // --- Schema Migration for post_reactions ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `post_reactions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `post_id` INT(11) NOT NULL,
            `resident_id` INT(11) NOT NULL,
            `reaction_type` ENUM('like', 'acknowledge') NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_reaction` (`post_id`, `resident_id`, `reaction_type`),
            CONSTRAINT `fk_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_reactions_resident` FOREIGN KEY (`resident_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        // Ignore if error
    }

    // --- End of Migration and Seeding ---

    // --- Schema Migration for activity_logs ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'ip_address'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER details;");
    }

    // --- Schema Migration for activity_logs: old_value ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'old_value'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `old_value` TEXT DEFAULT NULL AFTER ip_address;");
    }

    // --- Schema Migration for activity_logs: user_agent ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'user_agent'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `user_agent` VARCHAR(255) NULL AFTER ip_address;");
    }

    // --- Schema Migration for activity_logs: session_id ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'session_id'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `session_id` VARCHAR(128) NULL AFTER user_agent;");
    }

    // --- Schema Migration for activity_logs: request_id ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'request_id'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `request_id` VARCHAR(100) NULL AFTER session_id;");
    }

    // --- Schema Migration for activity_logs: severity ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'severity'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `severity` ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info' AFTER request_id;");
    }

    // --- Schema Migration for activity_logs: new_value ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'new_value'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `new_value` TEXT DEFAULT NULL AFTER old_value;");
    }

    // Ensure active_user_sessions table exists for concurrent session controls
    $pdo->exec("CREATE TABLE IF NOT EXISTS `active_user_sessions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // --- Schema Migration for activity_logs: prev_hash ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'prev_hash'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `prev_hash` CHAR(64) NULL AFTER new_value;");
    }

    // --- Schema Migration for activity_logs: log_hash ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'log_hash'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `log_hash` CHAR(64) NULL AFTER prev_hash;");
    }

    // Ensure archive tables exist on upgraded installs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs_archive` (
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
        `archive_batch_id` VARCHAR(64) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_log_archive_batches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `batch_id` VARCHAR(64) NOT NULL,
        `previous_batch_hash` CHAR(64) DEFAULT NULL,
        `batch_hash` CHAR(64) NOT NULL,
        `start_log_id` INT DEFAULT NULL,
        `end_log_id` INT DEFAULT NULL,
        `entry_count` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_archive_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Enhanced Monitoring of Request: Add Payment Columns (must run BEFORE indexes that reference these columns)
    $pdo->exec("ALTER TABLE `document_requests` 
                ADD COLUMN IF NOT EXISTS `or_number` VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `payment_status` ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
                ADD COLUMN IF NOT EXISTS `payment_date` DATETIME DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `cash_received` DECIMAL(10,2) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `change_amount` DECIMAL(10,2) DEFAULT NULL");

    $pdo->exec("ALTER TABLE `business_transactions` 
                ADD COLUMN IF NOT EXISTS `or_number` VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `payment_status` ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
                ADD COLUMN IF NOT EXISTS `payment_date` DATETIME DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `cash_received` DECIMAL(10,2) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `change_amount` DECIMAL(10,2) DEFAULT NULL");

    // --- Performance Optimizations (Indexes) ---
    $performance_indexes = [
        ['table' => 'incidents', 'name' => 'idx_incidents_reported_at', 'columns' => 'reported_at'],
        ['table' => 'incidents', 'name' => 'idx_incidents_status', 'columns' => 'status'],
        ['table' => 'document_requests', 'name' => 'idx_docreq_date_requested', 'columns' => 'date_requested'],
        ['table' => 'document_requests', 'name' => 'idx_docreq_status', 'columns' => 'status'],
        ['table' => 'announcements', 'name' => 'idx_announcements_created_at', 'columns' => 'created_at'],
        ['table' => 'announcements', 'name' => 'idx_announcements_status_dates', 'columns' => 'status, publish_date, expiry_date, created_at'],
        ['table' => 'residents', 'name' => 'idx_residents_name', 'columns' => 'last_name, first_name'],
        ['table' => 'business_transactions', 'name' => 'idx_biztrans_status', 'columns' => 'status'],
        ['table' => 'announcement_reads', 'name' => 'idx_announcement_reads_resident', 'columns' => 'resident_id'],
        ['table' => 'announcement_reads', 'name' => 'idx_announcement_reads_announcement', 'columns' => 'announcement_id'],
        // Monitoring of Request Page Optimization Indexes
        ['table' => 'document_requests', 'name' => 'idx_docreq_payment_status', 'columns' => 'payment_status'],
        ['table' => 'document_requests', 'name' => 'idx_docreq_resident_id', 'columns' => 'resident_id'],
        ['table' => 'document_requests', 'name' => 'idx_docreq_status_payment_date', 'columns' => 'status, payment_status, date_requested'],
        ['table' => 'business_transactions', 'name' => 'idx_biztrans_payment_status', 'columns' => 'payment_status'],
        ['table' => 'business_transactions', 'name' => 'idx_biztrans_resident_id', 'columns' => 'resident_id'],
        ['table' => 'business_transactions', 'name' => 'idx_biztrans_status_payment_date', 'columns' => 'status, payment_status, application_date'],
        // Log scalability indexes
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_created_at', 'columns' => 'created_at'],
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_action', 'columns' => 'action'],
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_username', 'columns' => 'username'],
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_target_type', 'columns' => 'target_type'],
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_severity', 'columns' => 'severity'],
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_request_id', 'columns' => 'request_id'],
        ['table' => 'activity_logs', 'name' => 'idx_activity_logs_log_hash', 'columns' => 'log_hash'],
        ['table' => 'activity_logs_archive', 'name' => 'idx_activity_logs_archive_created_at', 'columns' => 'created_at'],
        ['table' => 'activity_logs_archive', 'name' => 'idx_activity_logs_archive_batch_id', 'columns' => 'archive_batch_id']
    ];

    foreach ($performance_indexes as $idx) {
        // Safely check if index exists
        $stmt = $pdo->prepare("SHOW INDEX FROM `{$idx['table']}` WHERE Key_name = ?");
        $stmt->execute([$idx['name']]);
        if ($stmt->rowCount() == 0) {
            $pdo->exec("CREATE INDEX `{$idx['name']}` ON `{$idx['table']}` ({$idx['columns']})");
        }
    }

    // Notifications Table Migration
    $pdo->exec("ALTER TABLE `notifications` 
                ADD COLUMN IF NOT EXISTS `user_id` INT(11) DEFAULT NULL AFTER `id`,
                ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) DEFAULT NULL AFTER `user_id`,
                ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'general' AFTER `message`,
                ADD COLUMN IF NOT EXISTS `link` VARCHAR(255) DEFAULT NULL AFTER `type` ");

    // Incident report schema migration
    $pdo->exec("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT DEFAULT NULL AFTER `admin_remarks`");

    // Add email_verified column to users table
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`");

    // Add status column to users table (for active/inactive/suspended accounts)
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `email_verified`");

    // Add password reset columns to users table
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(255) DEFAULT NULL AFTER `status`");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_token_expires` DATETIME DEFAULT NULL AFTER `reset_token`");

    // Account lifecycle hardening columns
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `password_change_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reset_token_expires`");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `invitation_sent_at` DATETIME DEFAULT NULL AFTER `password_change_required`");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `invitation_expires_at` DATETIME DEFAULT NULL AFTER `invitation_sent_at`");

    // Sync resident_id to user_id for old records if user_id is null
    // Only run if the legacy resident_id column exists
    $colCheck = $pdo->query("SHOW COLUMNS FROM `notifications` LIKE 'resident_id'");
    if ($colCheck && $colCheck->rowCount() > 0) {
        $pdo->exec("UPDATE `notifications` n 
                    JOIN `residents` r ON n.resident_id = r.id 
                    SET n.user_id = r.user_id 
                    WHERE n.user_id IS NULL AND n.resident_id IS NOT NULL");
    }

} catch (PDOException $e) {
    // Log the schema error but do NOT redirect — redirecting causes infinite loops
    // since every page includes init.php
    error_log("CommunaLink init.php schema error: " . $e->getMessage());
    // Store error for display but continue execution
    $_SESSION['db_init_error'] = $e->getMessage();
} 