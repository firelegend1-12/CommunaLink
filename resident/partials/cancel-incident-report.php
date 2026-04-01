<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'resident') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!csrf_validate()) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$report_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$reason = sanitize_input(trim($_POST['reason'] ?? 'No reason provided'));
$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($report_id <= 0 || $user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, status, type FROM incidents WHERE id = ? AND resident_user_id = ? LIMIT 1');
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        exit;
    }

    if (strcasecmp((string) $report['status'], 'Pending') !== 0) {
        echo json_encode(['success' => false, 'error' => 'Only pending reports can be cancelled']);
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM incidents WHERE id = ? AND resident_user_id = ? LIMIT 1');
    $delete->execute([$report_id, $user_id]);

    if ($delete->rowCount() !== 1) {
        echo json_encode(['success' => false, 'error' => 'Failed to cancel report']);
        exit;
    }

    log_activity_db(
        $pdo,
        'cancel',
        'incident_report',
        $report_id,
        "Resident cancelled incident report '{$report['type']}' (reason: {$reason})",
        'Pending',
        'Deleted'
    );

    echo json_encode(['success' => true, 'message' => 'Report cancelled successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel report']);
}
