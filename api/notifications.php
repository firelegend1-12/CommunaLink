<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_read') {
    $allowed_mark_read_roles = ['resident', 'admin', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer', 'kagawad', 'barangay-tanod'];
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_mark_read_roles, true)) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if (!csrf_validate()) {
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }

    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    $user_id = (int) ($_SESSION['user_id'] ?? 0);

    if ($notification_id <= 0 || $user_id <= 0) {
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
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer', 'kagawad', 'barangay-tanod'])) {
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
            $response = ['error' => 'Database error: ' . $e->getMessage()];
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
            $response = ['error' => 'Database error: ' . $e->getMessage()];
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
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }
    }
}

echo json_encode($response);
