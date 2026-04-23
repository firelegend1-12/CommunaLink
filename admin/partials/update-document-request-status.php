<?php
/**
 * Update Document Request Status Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/notification_system.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_documents', 403, 'Forbidden');

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
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate parameters
if (empty($id) || empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$allowed_statuses = ['Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled'];
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit;
}

try {
    $pdo->beginTransaction();
    $notification_warning = null;

    // Get old status for logging
    $stmt = $pdo->prepare('SELECT status, resident_id, document_type FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    $old_data = $stmt->fetch();
    
    if (!$old_data) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }
    
    $old_status = $old_data['status'];
    $resident_id = $old_data['resident_id'];
    $document_type = $old_data['document_type'];

    if ($old_status === 'Cancelled' && $status !== 'Cancelled') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cancelled requests cannot be reopened']);
        exit;
    }

    if ($status === 'Cancelled' && strcasecmp((string) $old_status, 'Pending') !== 0) {
        $pdo->rollBack();
        http_response_code(400);
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
        $notification_sent = NotificationSystem::notify_document_status($pdo, (int) $res_user_id, $document_type, $status, 'my-requests.php');
        if (!$notification_sent) {
            $notification_warning = 'Status updated, but notification delivery failed.';
            error_log('Notification delivery failed in update-document-request-status for request_id=' . $id);
        }
    }

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
    error_log('update-document-request-status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update request status.']);
}
exit; 