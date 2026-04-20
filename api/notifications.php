<?php
header('Content-Type: application/json');
require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/cache_manager.php';

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

init_cache_manager([
    'cache_dir' => __DIR__ . '/../cache/',
]);

function notifications_cache_get(string $key): ?array
{
    $cached = cache_get($key);
    return is_array($cached) ? $cached : null;
}

function notifications_cache_set(string $key, array $payload, int $ttl = 15): void
{
    cache_set($key, $payload, $ttl);
}

if (!is_logged_in()) {
    http_response_code(401);
    emit_perf_headers($request_start, 'api_notifications');
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$notifications_rate_limit = RateLimiter::checkRateLimit('notifications_api', RateLimiter::getClientIP());
if (!$notifications_rate_limit['allowed']) {
    $retry_after = (int) ($notifications_rate_limit['lockout_remaining'] ?? 60);
    header('Retry-After: ' . $retry_after);
    http_response_code(429);
    emit_perf_headers($request_start, 'api_notifications');
    echo json_encode([
        'error' => 'Too Many Requests',
        'message' => $notifications_rate_limit['message'] ?? 'Rate limit exceeded. Please try again later.',
        'retry_after' => $retry_after
    ]);
    exit;
}

RateLimiter::recordAttempt('notifications_api', RateLimiter::getClientIP());

$action = $_GET['action'] ?? '';
$allowed_mark_read_roles = ['resident', 'admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'mark_read' || $action === 'mark_all_read')) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_mark_read_roles, true)) {
        http_response_code(403);
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if (!csrf_validate()) {
        http_response_code(403);
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }

    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        http_response_code(400);
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode(['error' => 'Invalid request.']);
        exit;
    }

    if ($action === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);

            echo json_encode([
                'success' => true,
                'updated_count' => (int) $stmt->rowCount(),
            ]);
        } catch (PDOException $e) {
            error_log('notifications mark_all_read database error: ' . $e->getMessage());
            echo json_encode(['error' => 'Database error while updating notifications.']);
        }
        emit_perf_headers($request_start, 'api_notifications');
        exit;
    }

    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);

    if ($notification_id <= 0) {
        http_response_code(400);
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode(['error' => 'Invalid request.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);

        echo json_encode([
            'success' => true,
            'updated' => ((int) $stmt->rowCount()) > 0,
        ]);
    } catch (PDOException $e) {
        error_log('notifications mark_read database error: ' . $e->getMessage());
        echo json_encode(['error' => 'Database error while updating notification.']);
    }
    emit_perf_headers($request_start, 'api_notifications');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'], true)) {
    http_response_code(403);
    emit_perf_headers($request_start, 'api_notifications');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = ['error' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action !== '') {

    if ($action === 'get_admin_sidebar_counts') {
        $cacheKey = 'notifications:get_admin_sidebar_counts';
        $cachedResponse = notifications_cache_get($cacheKey);
        if ($cachedResponse !== null) {
            $response = $cachedResponse;
            emit_perf_headers($request_start, 'api_notifications');
            echo json_encode($response);
            exit;
        }

        try {
            $stmtSidebar = $pdo->query("SELECT
                (SELECT COUNT(*) FROM incidents WHERE status = 'Pending') AS pending_incidents,
                (SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS upcoming_events");
            $counts = $stmtSidebar->fetch(PDO::FETCH_ASSOC) ?: [];
            $pendingIncidents = (int)($counts['pending_incidents'] ?? 0);
            $upcomingEvents = (int)($counts['upcoming_events'] ?? 0);

            $response = [
                'success' => true,
                'incidents' => $pendingIncidents,
                'events' => $upcomingEvents,
            ];
            notifications_cache_set($cacheKey, $response);
        } catch (PDOException $e) {
            error_log('notifications get_admin_sidebar_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching sidebar counts.'];
        }

    } elseif ($action === 'get_business_counts') {
        $cacheKey = 'notifications:get_business_counts';
        $cachedResponse = notifications_cache_get($cacheKey);
        if ($cachedResponse !== null) {
            $response = $cachedResponse;
            emit_perf_headers($request_start, 'api_notifications');
            echo json_encode($response);
            exit;
        }

        try {
            $stmtBiz = $pdo->query("SELECT
                (SELECT COUNT(*) FROM businesses WHERE status = 'Pending') AS pending_businesses,
                (SELECT COUNT(*) FROM business_transactions WHERE status = 'Pending') AS pending_transactions");
            $counts = $stmtBiz->fetch(PDO::FETCH_ASSOC) ?: [];
            $pendingBusinesses = (int)($counts['pending_businesses'] ?? 0);
            $pendingTransactions = (int)($counts['pending_transactions'] ?? 0);

            $response = [
                'success'      => true,
                'businesses'   => $pendingBusinesses,
                'transactions' => $pendingTransactions,
                'total'        => $pendingBusinesses + $pendingTransactions,
            ];
            notifications_cache_set($cacheKey, $response);
        } catch (PDOException $e) {
            error_log('notifications get_business_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching business counts.'];
        }

    } elseif ($action === 'get_incident_counts') {
        $cacheKey = 'notifications:get_incident_counts';
        $cachedResponse = notifications_cache_get($cacheKey);
        if ($cachedResponse !== null) {
            $response = $cachedResponse;
            emit_perf_headers($request_start, 'api_notifications');
            echo json_encode($response);
            exit;
        }

        try {
            $stmtInc = $pdo->query("SELECT
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_incidents
                FROM incidents");
            $counts = $stmtInc->fetch(PDO::FETCH_ASSOC) ?: [];
            $pendingIncidents = (int)($counts['pending_incidents'] ?? 0);
            $response = [
                'success'   => true,
                'incidents' => $pendingIncidents,
            ];
            notifications_cache_set($cacheKey, $response);
        } catch (PDOException $e) {
            error_log('notifications get_incident_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching incident counts.'];
        }
    } elseif ($action === 'get_events_counts') {
        $cacheKey = 'notifications:get_events_counts';
        $cachedResponse = notifications_cache_get($cacheKey);
        if ($cachedResponse !== null) {
            $response = $cachedResponse;
            emit_perf_headers($request_start, 'api_notifications');
            echo json_encode($response);
            exit;
        }

        try {
            $stmtEvt = $pdo->query("SELECT
                SUM(CASE WHEN event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS upcoming_events
                FROM events");
            $counts = $stmtEvt->fetch(PDO::FETCH_ASSOC) ?: [];
            $upcomingEvents = (int)($counts['upcoming_events'] ?? 0);
            $response = [
                'success' => true,
                'events'  => $upcomingEvents,
            ];
            notifications_cache_set($cacheKey, $response);
        } catch (PDOException $e) {
            error_log('notifications get_events_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching event counts.'];
        }
    }
}

emit_perf_headers($request_start, 'api_notifications');
echo json_encode($response);
