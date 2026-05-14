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

    if ($type === 'document') {
        $fee_stmt = $pdo->prepare("SELECT document_type FROM document_requests WHERE id = ? LIMIT 1");
        $fee_stmt->execute([$id]);
        $document_type = (string)($fee_stmt->fetchColumn() ?: '');
        if ($document_type === '') {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Document request not found.']);
            $pdo->rollBack();
            exit;
        }

        if ($payment_status === 'Paid' && !document_request_requires_payment($document_type)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'This document does not require payment.']);
            $pdo->rollBack();
            exit;
        }
    }
    
    // Set payment date if marking as paid
    $payment_date = ($payment_status === 'Paid') ? date('Y-m-d H:i:s') : null;

    if ($payment_status === 'Paid') {
        $completed_status = normalize_request_status_for_storage($pdo, $table, 'Completed');
        $stmt = $pdo->prepare("UPDATE $table SET or_number = ?, payment_status = ?, payment_date = ?, status = CASE WHEN UPPER(status) IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN status ELSE ? END WHERE id = ?");
        $stmt->execute([$or_number, $payment_status, $payment_date, $completed_status, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE $table SET or_number = ?, payment_status = ?, payment_date = ? WHERE id = ?");
        $stmt->execute([$or_number, $payment_status, $payment_date, $id]);
    }

    // Fetch resident info for notification
    $stmt_res = $pdo->prepare("SELECT " . ($type === 'document' ? "COALESCE(NULLIF(dr.requested_by_user_id, 0), r.user_id)" : "r.user_id") . " AS recipient_user_id, " . ($type === 'document' ? "dr.document_type as item_name" : "COALESCE(NULLIF(bt.transaction_type, ''), bt.business_name) as item_name") . " 
                               FROM $table " . ($type === 'document' ? 'dr' : 'bt') . "
                               JOIN residents r ON " . ($type === 'document' ? 'dr.resident_id' : 'bt.resident_id') . " = r.id
                               WHERE " . ($type === 'document' ? 'dr.id' : 'bt.id') . " = ?");
    $stmt_res->execute([$id]);
    $res_info = $stmt_res->fetch();

    if ($res_info && $res_info['recipient_user_id']) {
        $item_name = $res_info['item_name'];
        $notification_sent = NotificationSystem::notify_payment_update(
            $pdo,
            (int) $res_info['recipient_user_id'],
            $item_name,
            $payment_status,
            $or_number,
            $type === 'document' ? 'my-document-requests.php' : 'my-requests.php'
        );

        if (!$notification_sent) {
            $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
            $notification_warning = 'Payment updated, but web-app notification failed' . ($detail ? ': ' . $detail : '.');
            error_log('Notification delivery failed in update-payment-info for id=' . $id . ' type=' . $type);
        }

        if ($payment_status === 'Paid') {
            $status_notification_sent = NotificationSystem::notify_document_status(
                $pdo,
                (int) $res_info['recipient_user_id'],
                $item_name,
                'Completed',
                $type === 'document' ? 'my-document-requests.php' : 'my-requests.php'
            );
            if (!$status_notification_sent && $notification_warning === null) {
                $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
                $notification_warning = 'Payment updated, but completion web-app notification failed' . ($detail ? ': ' . $detail : '.');
            }
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
    if ($payment_status === 'Paid') {
        $response['status'] = 'Completed';
    }
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
