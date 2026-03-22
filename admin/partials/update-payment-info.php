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
    $pdo->beginTransaction();

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
        $title = "Payment Updated: " . $item_name;
        $message = "Payment information for **" . $item_name . "** has been updated. ";
        
        if ($payment_status === 'Paid') {
            $message .= "Status: **Paid**. Official Receipt #: **" . $or_number . "**. Date: " . date('M d, Y') . ".";
        } else {
            $message .= "Status: **Unpaid**. Please settle your balance at the Barangay Hall.";
        }

        create_notification($pdo, $res_info['user_id'], $title, $message, 'payment_update');
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
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
