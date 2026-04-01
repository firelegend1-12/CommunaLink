<?php
/**
 * API Endpoint: Check Expiring & Expired Business Permits
 *
 * - POST only
 * - Allowed callers:
 *   1) Admin UI session + CSRF token
 *   2) Scheduler token header
 * - Uses batched idempotent notification inserts to avoid duplicate alerts
 */

header('Content-Type: application/json');
require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

$request_start = microtime(true);

function emit_perf_headers(float $start, string $endpoint): void
{
    $elapsed_ms = (microtime(true) - $start) * 1000;
    header('X-Endpoint: ' . $endpoint);
    header('X-Response-Time-Ms: ' . number_format($elapsed_ms, 2, '.', ''));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function request_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function is_scheduler_authorized(): bool
{
    $expectedToken = trim((string)env('PERMIT_CHECK_SCHEDULER_TOKEN', ''));
    $providedToken = request_header('X-Cloud-Scheduler-Token');

    if ($expectedToken === '' || $providedToken === '') {
        return false;
    }

    return hash_equals($expectedToken, $providedToken);
}

function insert_admin_notification_batch(PDO $pdo, string $title, string $message, string $type, string $link, int $dedupeWindowMinutes = 15): int
{
    $window = max(1, min(60, $dedupeWindowMinutes));
    $sql = "INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
            SELECT u.id, :title, :message, :type, :link, 0, NOW()
            FROM users u
            WHERE u.role = 'admin'
              AND NOT EXISTS (
                    SELECT 1
                    FROM notifications n
                    WHERE n.user_id = u.id
                      AND n.type = :type_check
                      AND n.title = :title_check
                      AND n.created_at >= DATE_SUB(NOW(), INTERVAL {$window} MINUTE)
              )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':message' => $message,
        ':type' => $type,
        ':link' => $link,
        ':type_check' => $type,
        ':title_check' => $title,
    ]);

    return (int)$stmt->rowCount();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    emit_perf_headers($request_start, 'api_check_expiring_permits');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$schedulerAuthorized = is_scheduler_authorized();
$adminAuthorized = is_logged_in() && (($_SESSION['role'] ?? '') === 'admin') && csrf_validate();

if (!$schedulerAuthorized && !$adminAuthorized) {
    http_response_code(403);
    emit_perf_headers($request_start, 'api_check_expiring_permits');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized request.']);
    exit;
}

$lockName = '';
$lockAcquired = false;

if ($schedulerAuthorized) {
    $lockName = 'permit_expiry_scheduler_' . date('YmdHi');
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 1)');
    $lockStmt->execute([$lockName]);
    $lockAcquired = ((int) $lockStmt->fetchColumn() === 1);

    if (!$lockAcquired) {
        http_response_code(202);
        emit_perf_headers($request_start, 'api_check_expiring_permits');
        echo json_encode([
            'status' => 'success',
            'message' => 'Permit check already running; skipping duplicate scheduler execution.',
            'timestamp' => date('Y-m-d H:i:s'),
            'trigger' => 'scheduler',
        ]);
        exit;
    }
}

try {
    $results = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => [],
        'notifications_sent' => 0,
        'trigger' => $schedulerAuthorized ? 'scheduler' : 'admin'
    ];

    $expiring_stmt = $pdo->query("SELECT
        SUM(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status IN ('Active', 'Pending') THEN 1 ELSE 0 END) AS count_30,
        MIN(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status IN ('Active', 'Pending') THEN permit_expiration_date END) AS earliest_30,
        SUM(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status IN ('Active', 'Pending') THEN 1 ELSE 0 END) AS count_7,
        SUM(CASE WHEN permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status IN ('Active', 'Pending') THEN 1 ELSE 0 END) AS count_1
        FROM businesses");
    $expiring = $expiring_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $count30 = (int)($expiring['count_30'] ?? 0);
    $count7 = (int)($expiring['count_7'] ?? 0);
    $count1 = (int)($expiring['count_1'] ?? 0);
    $earliest30 = $expiring['earliest_30'] ?? null;

    if ($count30 > 0) {
        $results['checks']['expiring_30_days'] = [
            'count' => $count30,
            'earliest' => $earliest30,
            'action_taken' => 'notification_sent'
        ];
        $earliestDate = $earliest30 ? date('M d, Y', strtotime((string)$earliest30)) : date('M d, Y', strtotime('+30 days'));
        $results['notifications_sent'] += insert_admin_notification_batch(
            $pdo,
            'Permits Expiring in 30 Days',
            "{$count30} permit(s) will expire on {$earliestDate}. Please review.",
            'permit_warning',
            '/admin/?page=monitoring-of-request&tab=business&status=pending'
        );
    } else {
        $results['checks']['expiring_30_days'] = ['count' => 0, 'action_taken' => 'none'];
    }

    if ($count7 > 0) {
        $results['checks']['expiring_7_days'] = [
            'count' => $count7,
            'action_taken' => 'urgent_notification_sent'
        ];
        $results['notifications_sent'] += insert_admin_notification_batch(
            $pdo,
            'URGENT: Permits Expiring in 7 Days',
            "URGENT: {$count7} permit(s) expire in 7 days. Immediate action required!",
            'permit_urgent',
            '/admin/?page=monitoring-of-request&tab=business&status=active'
        );
    } else {
        $results['checks']['expiring_7_days'] = ['count' => 0, 'action_taken' => 'none'];
    }

    if ($count1 > 0) {
        $results['checks']['expiring_1_day'] = [
            'count' => $count1,
            'action_taken' => 'final_warning_sent'
        ];
        $results['notifications_sent'] += insert_admin_notification_batch(
            $pdo,
            'FINAL WARNING: Permits Expire Tomorrow',
            "FINAL WARNING: {$count1} permit(s) expire tomorrow. Immediate action required!",
            'permit_critical',
            '/admin/?page=monitoring-of-request&tab=business&status=active'
        );
    } else {
        $results['checks']['expiring_1_day'] = ['count' => 0, 'action_taken' => 'none'];
    }

    $update_stmt = $pdo->query("UPDATE businesses
        SET status = 'Expired'
        WHERE DATE(permit_expiration_date) < CURDATE()
          AND status IN ('Active', 'Pending')");
    $expired_count = (int)$update_stmt->rowCount();

    if ($expired_count > 0) {
        $results['checks']['marked_expired'] = [
            'count' => $expired_count,
            'action_taken' => 'status_updated'
        ];
        $results['notifications_sent'] += insert_admin_notification_batch(
            $pdo,
            'Permits Now Expired',
            "{$expired_count} permit(s) have been marked as expired. Please review renewal actions.",
            'permit_expired',
            '/admin/?page=monitoring-of-request&tab=business&status=pending'
        );
    } else {
        $results['checks']['marked_expired'] = ['count' => 0, 'action_taken' => 'none'];
    }

    $summary_stmt = $pdo->query("SELECT status, COUNT(*) AS count
        FROM businesses
        WHERE status IN ('Active', 'Pending', 'Expired')
        GROUP BY status");
    $summary = $summary_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $results['summary'] = [
        'active' => (int)($summary['Active'] ?? 0),
        'pending' => (int)($summary['Pending'] ?? 0),
        'expired' => (int)($summary['Expired'] ?? 0)
    ];

    $upcoming_stmt = $pdo->query("SELECT
            id, business_name, permit_expiration_date,
            DATEDIFF(permit_expiration_date, CURDATE()) AS days_remaining,
            status
        FROM businesses
        WHERE permit_expiration_date >= CURDATE()
          AND status IN ('Active', 'Pending')
        ORDER BY permit_expiration_date ASC
        LIMIT 5");
    $results['upcoming_expiries'] = $upcoming_stmt->fetchAll();

    http_response_code(202);
    emit_perf_headers($request_start, 'api_check_expiring_permits');
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    emit_perf_headers($request_start, 'api_check_expiring_permits');
    echo json_encode([
        'status' => 'error',
        'message' => 'Error during permit expiry check: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
} finally {
    if ($lockAcquired && $lockName !== '') {
        $unlockStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $unlockStmt->execute([$lockName]);
    }
}
?>
