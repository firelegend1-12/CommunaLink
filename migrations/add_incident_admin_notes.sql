-- Migration: Add per-user admin notes for incidents
CREATE TABLE IF NOT EXISTS `incident_admin_notes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `incident_id` INT(11) NOT NULL,
    `author_user_id` INT(11) NOT NULL,
    `note_text` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_incident_note_author` (`incident_id`, `author_user_id`),
    KEY `idx_incident_admin_notes_incident` (`incident_id`),
    KEY `idx_incident_admin_notes_author` (`author_user_id`),
    CONSTRAINT `fk_incident_admin_notes_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_incident_admin_notes_author` FOREIGN KEY (`author_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
