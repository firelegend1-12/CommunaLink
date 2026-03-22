<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$type = trim($_POST['type'] ?? '');
$reason = sanitize_input(trim($_POST['reason'] ?? 'No reason provided'));

if ($id <= 0 || !in_array($type, ['document', 'business'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    if ($type === 'document') {
        $stmt = $pdo->prepare("SELECT id, status, document_type FROM document_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            exit;
        }

        if (strcasecmp((string) $request['status'], 'Pending') !== 0) {
            echo json_encode(['success' => false, 'error' => 'Only pending requests can be cancelled']);
            exit;
        }

        $remarks = "Cancelled by admin: " . $reason;
        $update = $pdo->prepare("UPDATE document_requests SET status = 'Cancelled', remarks = ? WHERE id = ?");
        $update->execute([$remarks, $id]);

        log_activity_db(
            $pdo,
            'cancel',
            'document_request',
            $id,
            "Admin cancelled document request '{$request['document_type']}'",
            'Pending',
            'Cancelled'
        );

        echo json_encode(['success' => true, 'message' => 'Document request cancelled']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, status, business_name FROM business_transactions WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    if (strcasecmp((string) $transaction['status'], 'PENDING') !== 0) {
        echo json_encode(['success' => false, 'error' => 'Only pending applications can be cancelled']);
        exit;
    }

    $remarks = "Cancelled by admin: " . $reason;
    $update = $pdo->prepare("UPDATE business_transactions SET status = 'REJECTED', remarks = ?, processed_date = NOW() WHERE id = ?");
    $update->execute([$remarks, $id]);

    log_activity_db(
        $pdo,
        'cancel',
        'business_transaction',
        $id,
        "Admin cancelled business transaction '{$transaction['business_name']}'",
        'PENDING',
        'REJECTED'
    );

    echo json_encode(['success' => true, 'message' => 'Business application cancelled']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel request']);
}
