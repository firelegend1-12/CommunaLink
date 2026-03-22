<?php
/**
 * Update Payment Info Handler (O.R. Number & Status)
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check authorization
if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : ''; // 'document' or 'business'
$or_number = isset($_POST['or_number']) ? trim($_POST['or_number']) : '';
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'Unpaid';

// Validate parameters
if (empty($id) || empty($type)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

if (!in_array($type, ['document', 'business'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request type']);
    exit;
}

if (!in_array($payment_status, ['Unpaid', 'Paid'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment status']);
    exit;
}

try {
    $table = ($type === 'document') ? 'document_requests' : 'business_transactions';
    
    // Set payment date if marking as paid
    $payment_date = ($payment_status === 'Paid') ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("UPDATE $table SET or_number = ?, payment_status = ?, payment_date = ? WHERE id = ?");
    $stmt->execute([$or_number, $payment_status, $payment_date, $id]);

    // Log the action
    log_activity_db(
        $pdo,
        'update_payment',
        $type . '_request',
        $id,
        "Updated payment info: O.R. #{$or_number}, Status: {$payment_status}",
        null,
        $payment_status
    );

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
