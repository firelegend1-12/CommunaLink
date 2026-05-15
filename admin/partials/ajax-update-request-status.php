<?php
/**
 * AJAX endpoint for updating request status without page reload
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/notification_system.php';

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
$status = isset($_POST['status']) ? normalize_request_status_display($_POST['status']) : '';

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

$valid_statuses = canonical_request_statuses();
if (!in_array($status, $valid_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $notification_warning = null;
    $old_status = null;
    $request_label = '';
    $notification_link = $type === 'document'
        ? get_document_request_detail_url($id)
        : get_business_transaction_detail_url($id);

    if ($type === 'document') {
        $pre_stmt = $pdo->prepare("SELECT status, document_type FROM document_requests WHERE id = ? LIMIT 1");
        $pre_stmt->execute([$id]);
        $old_row = $pre_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old_row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            exit;
        }

        $old_status = normalize_request_status_display($old_row['status'] ?? null);
        $request_label = (string)($old_row['document_type'] ?? 'Document Request');
        $stored_status = normalize_request_status_for_storage($pdo, 'document_requests', $status);
        $stmt = $pdo->prepare("UPDATE document_requests SET status = ? WHERE id = ?");
        $stmt->execute([$stored_status, $id]);
    } elseif ($type === 'business') {
        $pre_stmt = $pdo->prepare("SELECT status, transaction_type, remarks, business_name FROM business_transactions WHERE id = ? LIMIT 1");
        $pre_stmt->execute([$id]);
        $old_row = $pre_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old_row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            exit;
        }

        $old_status = normalize_request_status_display($old_row['status'] ?? null);
        $request_label = get_business_transaction_display_name(
            $old_row['transaction_type'] ?? ($old_row['business_name'] ?? 'Business Request'),
            $old_row['remarks'] ?? null
        );
        $stored_status = normalize_request_status_for_storage($pdo, 'business_transactions', $status);
        $stmt = $pdo->prepare("UPDATE business_transactions SET status = ? WHERE id = ?");
        $stmt->execute([$stored_status, $id]);
    }

    // Fetch updated record to return details for row update
    if ($type === 'document') {
        $fetch_stmt = $pdo->prepare("SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.status, dr.payment_status, dr.or_number, dr.reference_number FROM document_requests dr LEFT JOIN residents r ON dr.resident_id = r.id WHERE dr.id = ?");
    } else {
        $fetch_stmt = $pdo->prepare("SELECT bt.id, r.first_name, r.last_name, CASE WHEN bt.remarks = 'Barangay Business Clearance' THEN 'Business Clearance' WHEN bt.transaction_type = 'New Permit' THEN 'Business Permit' ELSE bt.transaction_type END as document_type, bt.application_date as date_requested, bt.status, bt.payment_status, bt.or_number, bt.reference_number FROM business_transactions bt LEFT JOIN residents r ON bt.resident_id = r.id WHERE bt.id = ?");
    }
    $fetch_stmt->execute([$id]);
    $updated_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
    if ($updated_row) {
        $updated_row['status'] = get_request_display_status(
            $updated_row['status'] ?? null,
            $updated_row['payment_status'] ?? null,
            $type === 'document'
                ? document_request_requires_payment($updated_row['document_type'] ?? '')
                : true
        );
        $updated_row['reference_number'] = get_request_reference_number_from_row($updated_row, $type);
    }

    $display_status = normalize_request_status_display($stored_status ?? $status);

    if ($old_status !== $display_status) {
        $recipient_user_id = $type === 'document'
            ? get_document_request_recipient_user_id($pdo, $id)
            : get_business_transaction_recipient_user_id($pdo, $id);

        if ($recipient_user_id !== null) {
            $notification_sent = $type === 'document'
                ? NotificationSystem::notify_document_status(
                    $pdo,
                    $recipient_user_id,
                    $request_label,
                    $display_status,
                    $notification_link
                )
                : NotificationSystem::notify_business_status(
                    $pdo,
                    $recipient_user_id,
                    $request_label,
                    $display_status,
                    $notification_link
                );
            if (!$notification_sent) {
                $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
                $notification_warning = 'Status updated, but web-app notification failed' . ($detail ? ': ' . $detail : '.');
                error_log('Notification delivery failed in ajax-update-request-status for request_id=' . $id . ' type=' . $type);
            }
        } else {
            $notification_warning = 'Status updated, but no resident account was found for notification.';
            error_log('No recipient user found in ajax-update-request-status for request_id=' . $id . ' type=' . $type);
        }
    }

    $response = [
        'success' => true,
        'message' => 'Status updated successfully',
        'status' => $updated_row['status'] ?? $display_status,
        'updated_row' => $updated_row
    ];
    if ($notification_warning !== null) {
        $response['warning'] = $notification_warning;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('ajax-update-request-status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
}
