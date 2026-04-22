-- Add rejection reason storage for incident reports
ALTER TABLE `incidents`
  ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT DEFAULT NULL AFTER `admin_remarks`;
