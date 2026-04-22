-- Phase 5 legacy cleanup: backup and remove deprecated chat_messages table
-- This migration is idempotent and safe to run even when chat_messages is already absent.

SET @chat_messages_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_messages'
);

SET @backup_table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_messages_legacy_backup_20260421'
);

SET @create_backup_sql := IF(
    @chat_messages_exists = 1 AND @backup_table_exists = 0,
    'CREATE TABLE chat_messages_legacy_backup_20260421 LIKE chat_messages',
    'SELECT 1'
);
PREPARE stmt_create_backup FROM @create_backup_sql;
EXECUTE stmt_create_backup;
DEALLOCATE PREPARE stmt_create_backup;

SET @copy_backup_sql := IF(
    @chat_messages_exists = 1 AND @backup_table_exists = 0,
    'INSERT INTO chat_messages_legacy_backup_20260421 SELECT * FROM chat_messages',
    'SELECT 1'
);
PREPARE stmt_copy_backup FROM @copy_backup_sql;
EXECUTE stmt_copy_backup;
DEALLOCATE PREPARE stmt_copy_backup;

DROP TABLE IF EXISTS chat_messages;
