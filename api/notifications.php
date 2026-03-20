<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer', 'kagawad', 'barangay-tanod'])) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$response = ['error' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {

    if ($_GET['action'] === 'get_business_counts') {
        try {
            // Pending businesses (Business Records)
            $stmtBiz = $pdo->query("SELECT COUNT(*) FROM businesses WHERE status = 'Pending'");
            $pendingBusinesses = (int)$stmtBiz->fetchColumn();

            // Pending business transactions (Transactions page)
            $stmtTx = $pdo->query("SELECT COUNT(*) FROM business_transactions WHERE status = 'PENDING'");
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

    } elseif ($_GET['action'] === 'get_incident_counts') {
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
    } elseif ($_GET['action'] === 'get_events_counts') {
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
