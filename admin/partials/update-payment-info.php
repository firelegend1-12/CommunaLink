<?php
/**
 * Update Payment Info Handler (O.R. Number & Status)
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/notification_system.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('financial_management', 403, 'Forbidden');

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

// Get parameters
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : ''; // 'document' or 'business'
$or_number = isset($_POST['or_number']) ? trim($_POST['or_number']) : '';
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'Unpaid';

// Validate parameters
if (empty($id) || empty($type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

if (!in_array($type, ['document', 'business'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request type']);
    exit;
}

if (!in_array($payment_status, ['Unpaid', 'Paid'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment status']);
    exit;
}

try {
    $pdo->beginTransaction();
    $notification_warning = null;

    $table = ($type === 'document') ? 'document_requests' : 'business_transactions';
    
    // Set payment date if marking as paid
    $payment_date = ($payment_status === 'Paid') ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("UPDATE $table SET or_number = ?, payment_status = ?, payment_date = ? WHERE id = ?");
    $stmt->execute([$or_number, $payment_status, $payment_date, $id]);

    // Fetch resident info for notification
    $stmt_res = $pdo->prepare("SELECT r.user_id, " . ($type === 'document' ? "dr.document_type as item_name" : "bt.business_name as item_name") . " 
                               FROM $table " . ($type === 'document' ? 'dr' : 'bt') . "
                               JOIN residents r ON " . ($type === 'document' ? 'dr.resident_id' : 'bt.resident_id') . " = r.id
                               WHERE " . ($type === 'document' ? 'dr.id' : 'bt.id') . " = ?");
    $stmt_res->execute([$id]);
    $res_info = $stmt_res->fetch();

    if ($res_info && $res_info['user_id']) {
        $item_name = $res_info['item_name'];
        $notification_sent = NotificationSystem::notify_payment_update(
            $pdo,
            (int) $res_info['user_id'],
            $item_name,
            $payment_status,
            $or_number,
            'my-requests.php'
        );

        if (!$notification_sent) {
            $notification_warning = 'Payment updated, but notification delivery failed.';
            error_log('Notification delivery failed in update-payment-info for id=' . $id . ' type=' . $type);
        }
    }

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

    $pdo->commit();
    $response = ['success' => true];
    if ($notification_warning !== null) {
        $response['warning'] = $notification_warning;
    }

    echo json_encode($response);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('update-payment-info failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update payment info.']);
}
exit;
