<?php
/**
 * API Endpoint: Check Expiring & Expired Business Permits
 * 
 * This endpoint:
 * - Checks for permits expiring in 30, 7, and 1 day
 * - Marks permits as expired when expiration date passes
 * - Sends notifications to all admins
 * - Returns status and summary
 */

header('Content-Type: application/json');
require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

try {
    $results = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => [],
        'notifications_sent' => 0
    ];

    // Get all admin user IDs for notifications
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admin_ids = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);

    // ============================================================
    // 1. Check for permits expiring in 30 days
    // ============================================================
    $stmt = $pdo->query("
        SELECT COUNT(*) as count, 
               MIN(permit_expiration_date) as earliest_expiry
        FROM businesses 
        WHERE permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND status IN ('Active', 'Pending')
    ");
    $expiring_30_days = $stmt->fetch();
    
    if ($expiring_30_days['count'] > 0) {
        $results['checks']['expiring_30_days'] = [
            'count' => (int)$expiring_30_days['count'],
            'earliest' => $expiring_30_days['earliest_expiry'],
            'action_taken' => 'notification_sent'
        ];

        // Send notification to all admins
        foreach ($admin_ids as $admin_id) {
            $notify_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $notify_stmt->execute([
                $admin_id,
                '⚠️ Permits Expiring in 30 Days',
                "{$expiring_30_days['count']} permit(s) will expire on " . date('M d, Y', strtotime($expiring_30_days['earliest_expiry'])) . ". Please review.",
                'permit_warning',
                '/admin/?page=monitoring-of-request&tab=business&status=pending'
            ]);
        }
        $results['notifications_sent'] += count($admin_ids);
    } else {
        $results['checks']['expiring_30_days'] = ['count' => 0, 'action_taken' => 'none'];
    }

    // ============================================================
    // 2. Check for permits expiring in 7 days (URGENT)
    // ============================================================
    $stmt = $pdo->query("
        SELECT COUNT(*) as count,
               GROUP_CONCAT(CONCAT(business_name, ' (', permit_expiration_date, ')') SEPARATOR ', ') as businesses
        FROM businesses 
        WHERE permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND status IN ('Active', 'Pending')
    ");
    $expiring_7_days = $stmt->fetch();
    
    if ($expiring_7_days['count'] > 0) {
        $results['checks']['expiring_7_days'] = [
            'count' => (int)$expiring_7_days['count'],
            'action_taken' => 'urgent_notification_sent'
        ];

        // Send URGENT notification to all admins
        foreach ($admin_ids as $admin_id) {
            $notify_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $notify_stmt->execute([
                $admin_id,
                '🔴 URGENT: Permits Expiring in 7 Days',
                "URGENT: {$expiring_7_days['count']} permit(s) expire in 7 days. Immediate action required!",
                'permit_urgent',
                '/admin/?page=monitoring-of-request&tab=business&status=active'
            ]);
        }
        $results['notifications_sent'] += count($admin_ids);
    } else {
        $results['checks']['expiring_7_days'] = ['count' => 0, 'action_taken' => 'none'];
    }

    // ============================================================
    // 3. Check for permits expiring in 1 day (FINAL WARNING)
    // ============================================================
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM businesses 
        WHERE permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND status IN ('Active', 'Pending')
    ");
    $expiring_1_day = $stmt->fetch();
    
    if ($expiring_1_day['count'] > 0) {
        $results['checks']['expiring_1_day'] = [
            'count' => (int)$expiring_1_day['count'],
            'action_taken' => 'final_warning_sent'
        ];

        // Send FINAL WARNING to all admins
        foreach ($admin_ids as $admin_id) {
            $notify_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $notify_stmt->execute([
                $admin_id,
                '🚨 FINAL WARNING: Permits Expire Tomorrow!',
                "🚨 FINAL WARNING: {$expiring_1_day['count']} permit(s) EXPIRE TOMORROW! Immediate action required!",
                'permit_critical',
                '/admin/?page=monitoring-of-request&tab=business&status=active'
            ]);
        }
        $results['notifications_sent'] += count($admin_ids);
    } else {
        $results['checks']['expiring_1_day'] = ['count' => 0, 'action_taken' => 'none'];
    }

    // ============================================================
    // 4. Mark expired permits
    // ============================================================
    $update_stmt = $pdo->query("
        UPDATE businesses 
        SET status = 'Expired' 
        WHERE DATE(permit_expiration_date) < CURDATE() 
        AND status IN ('Active', 'Pending')
    ");
    $expired_count = $update_stmt->rowCount();
    
    if ($expired_count > 0) {
        $results['checks']['marked_expired'] = [
            'count' => $expired_count,
            'action_taken' => 'status_updated'
        ];

        // Notify admins about newly expired permits
        foreach ($admin_ids as $admin_id) {
            $notify_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $notify_stmt->execute([
                $admin_id,
                '❌ Permits Now Expired',
                "$expired_count permit(s) have been marked as EXPIRED. Please renew or process renewal.",
                'permit_expired',
                '/admin/?page=monitoring-of-request&tab=business&status=pending'
            ]);
        }
        $results['notifications_sent'] += count($admin_ids);
    } else {
        $results['checks']['marked_expired'] = ['count' => 0, 'action_taken' => 'none'];
    }

    // ============================================================
    // 5. Get Summary of Current Permit Status
    // ============================================================
    $summary_stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM businesses 
        WHERE status IN ('Active', 'Pending', 'Expired')
        GROUP BY status
    ");
    $summary = $summary_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $results['summary'] = [
        'active' => (int)($summary['Active'] ?? 0),
        'pending' => (int)($summary['Pending'] ?? 0),
        'expired' => (int)($summary['Expired'] ?? 0)
    ];

    // ============================================================
    // 6. Get Next Expiring Permits for Admin Dashboard
    // ============================================================
    $upcoming_stmt = $pdo->query("
        SELECT 
            id, business_name, permit_expiration_date,
            DATEDIFF(permit_expiration_date, CURDATE()) as days_remaining,
            status
        FROM businesses 
        WHERE permit_expiration_date >= CURDATE()
        AND status IN ('Active', 'Pending')
        ORDER BY permit_expiration_date ASC
        LIMIT 5
    ");
    $results['upcoming_expiries'] = $upcoming_stmt->fetchAll();

    http_response_code(200);
    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error during permit expiry check: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>
