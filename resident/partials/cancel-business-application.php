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

$transaction_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$reason = sanitize_input(trim($_POST['reason'] ?? 'No reason provided'));
$resident_id = $_SESSION['resident_id'] ?? 0;

if ($transaction_id <= 0 || $resident_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, status, business_name FROM business_transactions WHERE id = ? AND resident_id = ? LIMIT 1");
    $stmt->execute([$transaction_id, $resident_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Application not found']);
        exit;
    }

    if (strcasecmp((string) $transaction['status'], 'PENDING') !== 0) {
        echo json_encode(['success' => false, 'error' => 'Only pending applications can be cancelled']);
        exit;
    }

    $remarks = "Cancelled by resident: " . $reason;
    $update = $pdo->prepare("UPDATE business_transactions SET status = 'REJECTED', remarks = ?, processed_date = NOW() WHERE id = ?");
    $update->execute([$remarks, $transaction_id]);

    log_activity_db(
        $pdo,
        'cancel',
        'business_transaction',
        $transaction_id,
        "Resident cancelled business application '{$transaction['business_name']}'",
        'PENDING',
        'REJECTED'
    );

    echo json_encode(['success' => true, 'message' => 'Application cancelled successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel application']);
}
