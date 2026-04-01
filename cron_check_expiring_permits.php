<?php
/**
 * Cron Job Script for Business Permit Expiry Checks
 *
 * This script should be run daily via cron job to automatically:
 * - Check for expiring permits
 * - Generate reminder announcements
 * - Update permit statuses
 *
 * Example cron job (run daily at 9:00 AM):
 * 0 9 * * * /usr/bin/php /path/to/barangay/cron_check_expiring_permits.php
 */

date_default_timezone_set('Asia/Manila');

require_once 'config/init.php';
require_once 'includes/business_announcement_functions.php';

echo "=== Business Permit Expiry Check - " . date('Y-m-d H:i:s') . " ===\n\n";

try {
    $expiring_stmt = $pdo->query("SELECT
        SUM(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'Active' THEN 1 ELSE 0 END) AS count_30,
        MIN(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'Active' THEN permit_expiration_date END) AS earliest_30,
        SUM(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'Active' THEN 1 ELSE 0 END) AS count_7,
        SUM(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status = 'Active' THEN 1 ELSE 0 END) AS count_1
        FROM businesses");
    $expiring = $expiring_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $expiring_30_days = [
        'count' => (int)($expiring['count_30'] ?? 0),
        'earliest_expiry' => $expiring['earliest_30'] ?? null
    ];
    $expiring_7_days = [
        'count' => (int)($expiring['count_7'] ?? 0)
    ];
    $expiring_1_day = [
        'count' => (int)($expiring['count_1'] ?? 0)
    ];

    echo "1. Checking permits expiring in 30 days...\n";
    if ($expiring_30_days['count'] > 0) {
        echo "   Found {$expiring_30_days['count']} permit(s) expiring in 30 days\n";
        createBusinessAnnouncement('Business Permit Renewal Reminder', [
            'business_count' => $expiring_30_days['count'],
            'expiry_date' => date('M d, Y', strtotime((string)$expiring_30_days['earliest_expiry']))
        ]);
        echo "   Created renewal reminder announcement\n";
    } else {
        echo "   No permits expiring in 30 days\n";
    }

    echo "\n2. Checking permits expiring in 7 days...\n";
    if ($expiring_7_days['count'] > 0) {
        echo "   Found {$expiring_7_days['count']} permit(s) expiring in 7 days\n";
        createBusinessAnnouncement('Urgent Permit Expiry', [
            'business_count' => $expiring_7_days['count'],
            'days_remaining' => '7'
        ]);
        echo "   Created urgent expiry announcement\n";
    } else {
        echo "   No permits expiring in 7 days\n";
    }

    echo "\n3. Checking permits expiring in 1 day...\n";
    if ($expiring_1_day['count'] > 0) {
        echo "   Found {$expiring_1_day['count']} permit(s) expiring tomorrow\n";
        createBusinessAnnouncement('Urgent Permit Expiry', [
            'business_count' => $expiring_1_day['count'],
            'days_remaining' => '1'
        ]);
        echo "   Created final warning announcement\n";
    } else {
        echo "   No permits expiring tomorrow\n";
    }

    echo "\n4. Updating expired permit statuses...\n";
    $stmt = $pdo->query("UPDATE businesses
        SET status = 'Expired'
        WHERE permit_expiration_date < CURDATE()
          AND status = 'Active'");
    $expired_count = (int)$stmt->rowCount();

    if ($expired_count > 0) {
        echo "   Updated {$expired_count} expired permit(s)\n";
    } else {
        echo "   No permits to mark as expired\n";
    }

    if (date('j') === '1') {
        echo "\n5. Generating monthly compliance report...\n";
        generateMonthlyComplianceReport();
        echo "   Generated monthly compliance report\n";
    }

    echo "\nBusiness permit expiry check completed successfully.\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
} catch (Exception $e) {
    echo "Error during permit expiry check: " . $e->getMessage() . "\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    exit(1);
}
