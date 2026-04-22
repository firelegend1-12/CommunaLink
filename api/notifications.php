<?php
header('Content-Type: application/json');

require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/cache_manager.php';
require_once '../includes/permission_checker.php';

$request_start = microtime(true);

function emit_perf_headers(float $start, string $endpoint): void
{
    $elapsed_ms = (microtime(true) - $start) * 1000;
    header('X-Endpoint: ' . $endpoint);
    header('X-Response-Time-Ms: ' . number_format($elapsed_ms, 2, '.', ''));
}

function notifications_json_error(int $statusCode, string $error, float $requestStart, ?string $requiredPermission = null): void
{
    http_response_code($statusCode);
    emit_perf_headers($requestStart, 'api_notifications');

    $payload = [
        'success' => false,
        'error' => $error,
    ];

    if ($requiredPermission !== null && $requiredPermission !== '') {
        $payload['required_permission'] = $requiredPermission;
    }

    echo json_encode($payload);
    exit;
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
    notifications_json_error(401, 'Authentication required.', $request_start);
}

$notifications_rate_limit = RateLimiter::checkRateLimit('notifications_api', RateLimiter::getClientIP());
if (!$notifications_rate_limit['allowed']) {
    $retry_after = (int)($notifications_rate_limit['lockout_remaining'] ?? 60);
    header('Retry-After: ' . $retry_after);
    http_response_code(429);
    emit_perf_headers($request_start, 'api_notifications');
    echo json_encode([
        'success' => false,
        'error' => 'Too Many Requests',
        'message' => $notifications_rate_limit['message'] ?? 'Rate limit exceeded. Please try again later.',
        'retry_after' => $retry_after,
    ]);
    exit;
}

RateLimiter::recordAttempt('notifications_api', RateLimiter::getClientIP());

$action = (string)($_GET['action'] ?? '');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    if ($action !== 'mark_read' && $action !== 'mark_all_read') {
        notifications_json_error(400, 'Invalid request.', $request_start);
    }

    if (!csrf_validate()) {
        notifications_json_error(403, 'Invalid security token.', $request_start, 'csrf_token');
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        notifications_json_error(400, 'Invalid request.', $request_start);
    }

    if ($action === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);

            emit_perf_headers($request_start, 'api_notifications');
            echo json_encode([
                'success' => true,
                'updated_count' => (int)$stmt->rowCount(),
            ]);
            exit;
        } catch (PDOException $e) {
            error_log('notifications mark_all_read database error: ' . $e->getMessage());
            notifications_json_error(500, 'Database error while updating notifications.', $request_start);
        }
    }

    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if ($notification_id <= 0) {
        notifications_json_error(400, 'Invalid request.', $request_start);
    }

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);

        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode([
            'success' => true,
            'updated' => ((int)$stmt->rowCount()) > 0,
        ]);
        exit;
    } catch (PDOException $e) {
        error_log('notifications mark_read database error: ' . $e->getMessage());
        notifications_json_error(500, 'Database error while updating notification.', $request_start);
    }
}

if ($requestMethod !== 'GET') {
    notifications_json_error(405, 'Method not allowed.', $request_start);
}

$response = ['success' => false, 'error' => 'Invalid request.'];

if ($action === 'get_admin_sidebar_counts') {
    if (!require_any_permission(['manage_incidents', 'manage_events'])) {
        notifications_json_error(403, 'Forbidden', $request_start, 'manage_incidents|manage_events');
    }

    $canManageIncidents = require_permission('manage_incidents');
    $canManageEvents = require_permission('manage_events');
    $cacheKey = 'notifications:get_admin_sidebar_counts:' . (int)$canManageIncidents . ':' . (int)$canManageEvents;
    $cachedResponse = notifications_cache_get($cacheKey);
    if ($cachedResponse !== null) {
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode($cachedResponse);
        exit;
    }

    try {
        $pendingIncidents = 0;
        $upcomingEvents = 0;

        if ($canManageIncidents) {
            $pendingIncidents = (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'Pending'")->fetchColumn();
        }

        if ($canManageEvents) {
            $upcomingEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
        }

        $response = [
            'success' => true,
            'incidents' => $pendingIncidents,
            'events' => $upcomingEvents,
        ];

        notifications_cache_set($cacheKey, $response);
    } catch (PDOException $e) {
        error_log('notifications get_admin_sidebar_counts database error: ' . $e->getMessage());
        notifications_json_error(500, 'Database error while fetching sidebar counts.', $request_start);
    }
} elseif ($action === 'get_business_counts') {
    require_permission_or_json('manage_businesses', 403, 'Forbidden');

    $cacheKey = 'notifications:get_business_counts';
    $cachedResponse = notifications_cache_get($cacheKey);
    if ($cachedResponse !== null) {
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode($cachedResponse);
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
            'success' => true,
            'businesses' => $pendingBusinesses,
            'transactions' => $pendingTransactions,
            'total' => $pendingBusinesses + $pendingTransactions,
        ];

        notifications_cache_set($cacheKey, $response);
    } catch (PDOException $e) {
        error_log('notifications get_business_counts database error: ' . $e->getMessage());
        notifications_json_error(500, 'Database error while fetching business counts.', $request_start);
    }
} elseif ($action === 'get_incident_counts') {
    require_permission_or_json('manage_incidents', 403, 'Forbidden');

    $cacheKey = 'notifications:get_incident_counts';
    $cachedResponse = notifications_cache_get($cacheKey);
    if ($cachedResponse !== null) {
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode($cachedResponse);
        exit;
    }

    try {
        $stmtInc = $pdo->query("SELECT
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_incidents
            FROM incidents");
        $counts = $stmtInc->fetch(PDO::FETCH_ASSOC) ?: [];
        $pendingIncidents = (int)($counts['pending_incidents'] ?? 0);

        $response = [
            'success' => true,
            'incidents' => $pendingIncidents,
        ];

        notifications_cache_set($cacheKey, $response);
    } catch (PDOException $e) {
        error_log('notifications get_incident_counts database error: ' . $e->getMessage());
        notifications_json_error(500, 'Database error while fetching incident counts.', $request_start);
    }
} elseif ($action === 'get_events_counts') {
    require_permission_or_json('manage_events', 403, 'Forbidden');

    $cacheKey = 'notifications:get_events_counts';
    $cachedResponse = notifications_cache_get($cacheKey);
    if ($cachedResponse !== null) {
        emit_perf_headers($request_start, 'api_notifications');
        echo json_encode($cachedResponse);
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
            'events' => $upcomingEvents,
        ];

        notifications_cache_set($cacheKey, $response);
    } catch (PDOException $e) {
        error_log('notifications get_events_counts database error: ' . $e->getMessage());
        notifications_json_error(500, 'Database error while fetching event counts.', $request_start);
    }
} else {
    notifications_json_error(400, 'Invalid request.', $request_start);
}

emit_perf_headers($request_start, 'api_notifications');
echo json_encode($response);
