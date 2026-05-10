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
$status = isset($_POST['status']) ? normalize_request_status_display($_POST['status']) : '';

// Validate parameters
if (empty($id) || empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$allowed_statuses = canonical_request_statuses();
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit;
}

try {
    $pdo->beginTransaction();
    $notification_warning = null;

    // Get old status for logging
    $stmt = $pdo->prepare('SELECT status, resident_id, requested_by_user_id, document_type FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    $old_data = $stmt->fetch();
    
    if (!$old_data) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }
    
    $old_status = normalize_request_status_display($old_data['status'] ?? null);
    $old_stored_status = (string)($old_data['status'] ?? '');
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
    
    $stored_status = normalize_request_status_for_storage($pdo, 'document_requests', $status);

    // Update the status
    $stmt = $pdo->prepare('UPDATE document_requests SET status = ? WHERE id = ?');
    $stmt->execute([$stored_status, $id]);

    // Log the status change
    $old_str = "status: $old_stored_status";
    $new_str = "status: $stored_status";
    log_activity_db(
        $pdo,
        'update_status',
        'document_request',
        $id,
        "Document request status changed from '{$old_stored_status}' to '{$stored_status}' for {$document_type} (Resident ID: {$resident_id})",
        $old_str,
        $new_str
    );

    // Create Notification for the resident account that submitted the request.
    $res_user_id = get_document_request_recipient_user_id($pdo, $id);
    if ($res_user_id !== null) {
        $notification_sent = NotificationSystem::notify_document_status($pdo, $res_user_id, $document_type, normalize_request_status_display($stored_status), 'my-document-requests.php');
        if (!$notification_sent) {
            $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
            $notification_warning = 'Status updated, but web-app notification failed' . ($detail ? ': ' . $detail : '.');
            error_log('Notification delivery failed in update-document-request-status for request_id=' . $id);
        }
    } else {
        $notification_warning = 'Status updated, but no resident account was found for notification.';
        error_log('No recipient user found in update-document-request-status for request_id=' . $id);
    }

    $pdo->commit();


    
    $response = [
        'success' => true,
        'status' => normalize_request_status_display($stored_status),
        'stored_status' => $stored_status,
    ];
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
