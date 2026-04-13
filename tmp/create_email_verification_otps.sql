CREATE TABLE IF NOT EXISTS `email_verification_otps` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
