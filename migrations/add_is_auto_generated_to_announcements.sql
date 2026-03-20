-- ============================================================
-- Migration: Add missing columns to announcements table
-- Run this entire file in phpMyAdmin on the CommunaLink DB.
-- All statements use IF NOT EXISTS so it is safe to re-run.
-- ============================================================

ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `status`                VARCHAR(20)  NOT NULL DEFAULT 'active'  AFTER `content`,
  ADD COLUMN IF NOT EXISTS `priority`              VARCHAR(20)  NOT NULL DEFAULT 'normal'  AFTER `status`,
  ADD COLUMN IF NOT EXISTS `is_auto_generated`     TINYINT(1)   NOT NULL DEFAULT 0         AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `related_business_id`   INT(11)               DEFAULT NULL      AFTER `is_auto_generated`,
  ADD COLUMN IF NOT EXISTS `related_permit_number` VARCHAR(100)          DEFAULT NULL      AFTER `related_business_id`,
  ADD COLUMN IF NOT EXISTS `target_audience`       VARCHAR(50)  NOT NULL DEFAULT 'all'     AFTER `priority`,
  ADD COLUMN IF NOT EXISTS `publish_date`          DATETIME              DEFAULT NULL      AFTER `target_audience`,
  ADD COLUMN IF NOT EXISTS `expiry_date`           DATETIME              DEFAULT NULL      AFTER `publish_date`,
  ADD COLUMN IF NOT EXISTS `read_count`            INT(11)      NOT NULL DEFAULT 0         AFTER `expiry_date`;

