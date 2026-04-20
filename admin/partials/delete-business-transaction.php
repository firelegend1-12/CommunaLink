<?php
/**
 * Delete Business Transaction Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_businesses', 403, 'Forbidden');

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

// Get parameters from POST request
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate parameters
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get transaction details before state change for logging
    $stmt = $pdo->prepare('SELECT resident_id, business_name, transaction_type, status, remarks FROM business_transactions WHERE id = ?');
    $stmt->execute([$id]);
    $transaction_data = $stmt->fetch();
    
    if (!$transaction_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    
    $resident_id = $transaction_data['resident_id'];
    $business_name = $transaction_data['business_name'];
    $transaction_type = $transaction_data['transaction_type'];
    $status = $transaction_data['status'];
    $status = (string) $status;

    if (strcasecmp($status, 'Cancelled') === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Transaction is already cancelled.'
        ]);
        exit;
    }
    
    $existing_remarks = trim((string) ($transaction_data['remarks'] ?? ''));
    $soft_delete_note = 'Marked as removed by admin on ' . date('Y-m-d H:i:s');
    $new_remarks = $existing_remarks === ''
        ? $soft_delete_note
        : $existing_remarks . ' | ' . $soft_delete_note;

    // Soft-delete policy: keep history and mark as cancelled.
    $stmt = $pdo->prepare("UPDATE business_transactions
                           SET status = 'Cancelled',
                               remarks = ?,
                               processed_date = COALESCE(processed_date, NOW())
                           WHERE id = ?");
    $stmt->execute([$new_remarks, $id]);
    
    if ($stmt->rowCount() > 0) {
        // Log the soft-delete action
        log_activity_db(
            $pdo,
            'soft_delete',
            'business_transaction',
            $id,
            "Business transaction marked as Cancelled: {$business_name} - {$transaction_type} (Resident ID: {$resident_id}, Previous Status: {$status})",
            "Status: {$status}",
            'Status: Cancelled'
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction marked as cancelled.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete transaction']);
    }
    
} catch (PDOException $e) {
    error_log('delete-business-transaction failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while updating transaction']);
}
exit; 