<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$notifications_rate_limit = RateLimiter::checkRateLimit('notifications_api', RateLimiter::getClientIP());
if (!$notifications_rate_limit['allowed']) {
    $retry_after = (int) ($notifications_rate_limit['lockout_remaining'] ?? 60);
    header('Retry-After: ' . $retry_after);
    http_response_code(429);
    echo json_encode([
        'error' => 'Too Many Requests',
        'message' => $notifications_rate_limit['message'] ?? 'Rate limit exceeded. Please try again later.',
        'retry_after' => $retry_after
    ]);
    exit;
}

RateLimiter::recordAttempt('notifications_api', RateLimiter::getClientIP());

$action = $_GET['action'] ?? '';
$allowed_mark_read_roles = ['resident', 'admin', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer', 'kagawad', 'barangay-tanod'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'mark_read' || $action === 'mark_all_read')) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_mark_read_roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if (!csrf_validate()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }

    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        http_response_code(400);
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
        exit;
    }

    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);

    if ($notification_id <= 0) {
        http_response_code(400);
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
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer', 'kagawad', 'barangay-tanod'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = ['error' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action !== '') {

    if ($action === 'get_business_counts') {
        try {
            // Pending businesses (Business Records)
            $stmtBiz = $pdo->query("SELECT COUNT(*) FROM businesses WHERE status = 'Pending'");
            $pendingBusinesses = (int)$stmtBiz->fetchColumn();

            // Pending business transactions (Transactions page)
            $stmtTx = $pdo->query("SELECT COUNT(*) FROM business_transactions WHERE status = 'Pending'");
            $pendingTransactions = (int)$stmtTx->fetchColumn();

            $response = [
                'success'      => true,
                'businesses'   => $pendingBusinesses,
                'transactions' => $pendingTransactions,
                'total'        => $pendingBusinesses + $pendingTransactions,
            ];
        } catch (PDOException $e) {
            error_log('notifications get_business_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching business counts.'];
        }

    } elseif ($action === 'get_incident_counts') {
        try {
            $stmtInc = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'Pending'");
            $pendingIncidents = (int)$stmtInc->fetchColumn();
            $response = [
                'success'   => true,
                'incidents' => $pendingIncidents,
            ];
        } catch (PDOException $e) {
            error_log('notifications get_incident_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching incident counts.'];
        }
    } elseif ($action === 'get_events_counts') {
        try {
            // Events happening today or tomorrow
            $stmtEvt = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
            $upcomingEvents = (int)$stmtEvt->fetchColumn();
            $response = [
                'success' => true,
                'events'  => $upcomingEvents,
            ];
        } catch (PDOException $e) {
            error_log('notifications get_events_counts database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching event counts.'];
        }
    }
}

echo json_encode($response);
