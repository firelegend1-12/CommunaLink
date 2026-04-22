<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$type = trim($_POST['type'] ?? '');
$reason = sanitize_input(trim($_POST['reason'] ?? 'No reason provided'));

if ($id <= 0 || !in_array($type, ['document', 'business'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

if ($type === 'document') {
    require_permission_or_json('manage_documents', 403, 'Forbidden');
} else {
    require_permission_or_json('manage_businesses', 403, 'Forbidden');
}

try {
    if ($type === 'document') {
        $stmt = $pdo->prepare("SELECT id, status, document_type FROM document_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            exit;
        }

        if (strcasecmp((string) $request['status'], 'Pending') !== 0) {
            http_response_code(400);
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
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    if (strcasecmp((string) $transaction['status'], 'Pending') !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only pending applications can be cancelled']);
        exit;
    }

    $remarks = "Cancelled by admin: " . $reason;
    $update = $pdo->prepare("UPDATE business_transactions SET status = 'Cancelled', remarks = ?, processed_date = NOW() WHERE id = ?");
    $update->execute([$remarks, $id]);

    log_activity_db(
        $pdo,
        'cancel',
        'business_transaction',
        $id,
        "Admin cancelled business transaction '{$transaction['business_name']}'",
        'Pending',
        'Cancelled'
    );

    echo json_encode(['success' => true, 'message' => 'Business application cancelled']);
} catch (Exception $e) {
    error_log('cancel-request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to cancel request']);
}
