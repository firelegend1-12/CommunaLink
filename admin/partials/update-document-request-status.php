<?php
/**
 * Update Document Request Status Handler
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

// Get parameters from POST request
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate parameters
if (empty($id) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$allowed_statuses = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled'];
if (!in_array($status, $allowed_statuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get old status for logging
    $stmt = $pdo->prepare('SELECT status, resident_id, document_type FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    $old_data = $stmt->fetch();
    
    if (!$old_data) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }
    
    $old_status = $old_data['status'];
    $resident_id = $old_data['resident_id'];
    $document_type = $old_data['document_type'];

    if ($old_status === 'Cancelled' && $status !== 'Cancelled') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Cancelled requests cannot be reopened']);
        exit;
    }

    if ($status === 'Cancelled' && strcasecmp((string) $old_status, 'Pending') !== 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Only pending requests can be cancelled']);
        exit;
    }
    
    // Update the status
    $stmt = $pdo->prepare('UPDATE document_requests SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    // Log the status change
    $old_str = "status: $old_status";
    $new_str = "status: $status";
    log_activity_db(
        $pdo,
        'update_status',
        'document_request',
        $id,
        "Document request status changed from '{$old_status}' to '{$status}' for {$document_type} (Resident ID: {$resident_id})",
        $old_str,
        $new_str
    );

    // Create Notification for the resident
    $stmt_user = $pdo->prepare("SELECT user_id FROM residents WHERE id = ?");
    $stmt_user->execute([$resident_id]);
    $res_user_id = $stmt_user->fetchColumn();

    if ($res_user_id) {
        $title = "Request Update: " . $document_type;
        $message = "Your request for " . $document_type . " has been updated to: **" . $status . "**. ";
        
        if ($status === 'Ready for Pickup') {
            $message .= "Please visit the Barangay Hall to claim your document.";
        } elseif ($status === 'Rejected') {
            $message .= "Please contact the office for more details.";
        }

        create_notification($pdo, $res_user_id, $title, $message, 'request_status');
    }

    $pdo->commit();


    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit; 