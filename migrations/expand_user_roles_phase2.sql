UPDATE `users`
SET `role` = 'barangay-kagawad'
WHERE `role` = 'kagawad';

UPDATE `users`
SET `role` = 'barangay-officials'
WHERE `role` IN ('official', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer');

ALTER TABLE `users`
MODIFY `role` ENUM(
    'admin',
    'resident',
    'barangay-officials',
    'barangay-kagawad',
    'barangay-tanod'
) NOT NULL DEFAULT 'resident';

UPDATE `active_user_sessions`
SET `role` = 'barangay-kagawad'
WHERE `role` = 'kagawad';

UPDATE `active_user_sessions`
SET `role` = 'barangay-officials'
WHERE `role` IN ('official', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer');
