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

$request_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$reason = sanitize_input(trim($_POST['reason'] ?? 'No reason provided'));
$resident_id = $_SESSION['resident_id'] ?? 0;

if ($request_id <= 0 || $resident_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, status, document_type FROM document_requests WHERE id = ? AND resident_id = ? LIMIT 1");
    $stmt->execute([$request_id, $resident_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    if (strcasecmp((string) $request['status'], 'Pending') !== 0) {
        echo json_encode(['success' => false, 'error' => 'Only pending requests can be cancelled']);
        exit;
    }

    $remarks = "Cancelled by resident: " . $reason;
    $update = $pdo->prepare("UPDATE document_requests SET status = 'Cancelled', remarks = ? WHERE id = ?");
    $update->execute([$remarks, $request_id]);

    log_activity_db(
        $pdo,
        'cancel',
        'document_request',
        $request_id,
        "Resident cancelled document request '{$request['document_type']}'",
        'Pending',
        'Cancelled'
    );

    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel request']);
}
