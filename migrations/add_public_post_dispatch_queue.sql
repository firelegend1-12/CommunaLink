CREATE TABLE IF NOT EXISTS `public_post_dispatch_queue` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `payload_json` LONGTEXT NOT NULL,
    `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `attempts` INT(11) NOT NULL DEFAULT 0,
    `max_attempts` INT(11) NOT NULL DEFAULT 5,
    `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `failed_at` DATETIME DEFAULT NULL,
    `last_error` VARCHAR(1000) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_public_post_queue_status_available` (`status`, `available_at`),
    KEY `idx_public_post_queue_locked` (`locked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
