-- Migration: add database-backed PHP sessions table

CREATE TABLE IF NOT EXISTS php_sessions (
    session_id VARCHAR(128) NOT NULL,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (session_id),
    KEY idx_php_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
