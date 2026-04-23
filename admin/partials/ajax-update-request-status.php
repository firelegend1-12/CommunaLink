<?php
/**
 * AJAX endpoint for updating request status without page reload
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

require_login();

header('Content-Type: application/json');

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

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$type = isset($_POST['type']) ? sanitize_input($_POST['type']) : '';
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';

if (!$id || !$type || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if ($type === 'document') {
    require_permission_or_json('manage_documents', 403, 'Forbidden');
} elseif ($type === 'business') {
    require_permission_or_json('manage_businesses', 403, 'Forbidden');
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit;
}

$valid_statuses = ['Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled'];
if (!in_array($status, $valid_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    if ($type === 'document') {
        $stmt = $pdo->prepare("UPDATE document_requests SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    } elseif ($type === 'business') {
        $stmt = $pdo->prepare("UPDATE business_transactions SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    // Fetch updated record to return details for row update
    if ($type === 'document') {
        $fetch_stmt = $pdo->prepare("SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.status, dr.payment_status, dr.or_number FROM document_requests dr LEFT JOIN residents r ON dr.resident_id = r.id WHERE dr.id = ?");
    } else {
        $fetch_stmt = $pdo->prepare("SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.status, bt.payment_status, bt.or_number FROM business_transactions bt LEFT JOIN residents r ON bt.resident_id = r.id WHERE bt.id = ?");
    }
    $fetch_stmt->execute([$id]);
    $updated_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'status' => $status,
        'updated_row' => $updated_row
    ]);

} catch (Exception $e) {
    error_log('ajax-update-request-status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
}
