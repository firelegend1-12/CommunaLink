<?php
/**
 * Database Initialization File
 * Establishes a PDO connection and creates necessary tables if they don't exist.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database.php'; // This file should instantiate $pdo
require_once __DIR__ . '/../includes/csrf.php'; // CSRF protection
require_once __DIR__ . '/../includes/password_security.php'; // Password security
require_once __DIR__ . '/../includes/rate_limiter.php'; // Rate limiting
require_once __DIR__ . '/../includes/input_validator.php'; // Input validation & sanitization
require_once __DIR__ . '/../includes/security_headers.php'; // Security headers
require_once __DIR__ . '/../includes/database_optimizer.php'; // Database optimization
require_once __DIR__ . '/../includes/cache_manager.php'; // Caching system
require_once __DIR__ . '/../includes/query_optimizer.php'; // Query optimization


date_default_timezone_set('Asia/Manila');

try {
    // Table creation queries
    $queries = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `fullname` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `role` ENUM('admin', 'resident', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod') NOT NULL DEFAULT 'resident',
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
            `status` ENUM('PENDING', 'PROCESSING', 'READY FOR PICKUP', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
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
            `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
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
            `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected') NOT NULL DEFAULT 'Pending',
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

        "CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sender_id` INT(11) NOT NULL,
            `receiver_id` INT(11) NOT NULL,
            `message` TEXT NOT NULL,
            `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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
            `old_value` TEXT DEFAULT NULL,
            `new_value` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
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
        'status' => "ADD COLUMN `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER `date_requested`",
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
                    ADD COLUMN `role` ENUM('admin', 'resident') NOT NULL DEFAULT 'admin' AFTER `email`,
                    ADD COLUMN `last_login` DATETIME DEFAULT NULL AFTER `created_at`;");

        // Migrate data from old `is_admin` column if it exists
        $stmt_col_exists = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'is_admin'");
        if ($stmt_col_exists->rowCount() > 0) {
            $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `is_admin` = TRUE;");
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `is_admin`;");
        }
    } else {
        // If role column exists, just modify it to include 'resident'
        $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('admin', 'resident', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod') NOT NULL DEFAULT 'resident';");
    }
    
    // Check for admin and create if it doesn't exist
    $admin_email = 'admin@communalink.com';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    if ($stmt->rowCount() == 0) {
        $admin_username = 'admin';
        $admin_fullname = 'Administrator';
        $admin_password = 'Admin@2024!'; // Enhanced secure password
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

        $insert_stmt = $pdo->prepare(
            "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, 'admin')"
        );
        $insert_stmt->execute([$admin_username, $admin_fullname, $admin_email, $hashed_password]);
    }
    // Note: If admin account already exists, it keeps its existing password
    // Current working password appears to be: admin123

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

    // --- Schema Migration for activity_logs: new_value ---
    $stmt = $pdo->query("SHOW COLUMNS FROM `activity_logs` LIKE 'new_value'");
    if ($stmt && $stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `activity_logs` ADD COLUMN `new_value` TEXT DEFAULT NULL AFTER old_value;");
    }

    // --- Performance Optimizations (Indexes) ---
    $performance_indexes = [
        ['table' => 'incidents', 'name' => 'idx_incidents_reported_at', 'columns' => 'reported_at'],
        ['table' => 'incidents', 'name' => 'idx_incidents_status', 'columns' => 'status'],
        ['table' => 'document_requests', 'name' => 'idx_docreq_date_requested', 'columns' => 'date_requested'],
        ['table' => 'document_requests', 'name' => 'idx_docreq_status', 'columns' => 'status'],
        ['table' => 'announcements', 'name' => 'idx_announcements_created_at', 'columns' => 'created_at'],
        ['table' => 'residents', 'name' => 'idx_residents_name', 'columns' => 'last_name, first_name'],
        ['table' => 'business_transactions', 'name' => 'idx_biztrans_status', 'columns' => 'status']
    ];

    foreach ($performance_indexes as $idx) {
        // Safely check if index exists
        $stmt = $pdo->prepare("SHOW INDEX FROM `{$idx['table']}` WHERE Key_name = ?");
        $stmt->execute([$idx['name']]);
        if ($stmt->rowCount() == 0) {
            $pdo->exec("CREATE INDEX `{$idx['name']}` ON `{$idx['table']}` ({$idx['columns']})");
        }
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database connection failed: " . $e->getMessage();
    // If we are in a page inside 'pages' folder, the path to index should be relative
    // This is a simple check, might need to be more robust
    if (strpos($_SERVER['REQUEST_URI'], '/pages/') !== false) {
        header("Location: ../index.php");
    } else {
        header("Location: index.php");
    }
    exit;
} 