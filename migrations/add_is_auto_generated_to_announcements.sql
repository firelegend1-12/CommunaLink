-- ============================================================
-- Migration: Add missing columns to announcements table
-- Issue:     is_auto_generated, related_business_id, related_permit_number,
--            priority, target_audience, publish_date, expiry_date, and
--            read_count are referenced in business_announcement_functions.php
--            but do not exist in the table.
-- ============================================================

ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `is_auto_generated`     TINYINT(1)   NOT NULL DEFAULT 0      AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `related_business_id`   INT(11)               DEFAULT NULL   AFTER `is_auto_generated`,
  ADD COLUMN IF NOT EXISTS `related_permit_number`  VARCHAR(100)          DEFAULT NULL   AFTER `related_business_id`,
  ADD COLUMN IF NOT EXISTS `priority`              VARCHAR(20)  NOT NULL DEFAULT 'normal' AFTER `related_permit_number`,
  ADD COLUMN IF NOT EXISTS `target_audience`       VARCHAR(50)  NOT NULL DEFAULT 'all'   AFTER `priority`,
  ADD COLUMN IF NOT EXISTS `publish_date`          DATETIME              DEFAULT NULL   AFTER `target_audience`,
  ADD COLUMN IF NOT EXISTS `expiry_date`           DATETIME              DEFAULT NULL   AFTER `publish_date`,
  ADD COLUMN IF NOT EXISTS `read_count`            INT(11)      NOT NULL DEFAULT 0      AFTER `expiry_date`;
