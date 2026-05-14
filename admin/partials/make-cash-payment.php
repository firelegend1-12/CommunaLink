<?php
/**
 * AJAX endpoint for cash payments in Monitoring of Requests quick view.
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

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$type = isset($_POST['type']) ? sanitize_input($_POST['type']) : '';
$cash_received = isset($_POST['cash_received']) ? (float) $_POST['cash_received'] : 0.0;

if ($id <= 0 || !in_array($type, ['document', 'business'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
    exit;
}

if ($cash_received <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cash amount must be greater than zero']);
    exit;
}

$document_fees = [
    'Barangay Clearance' => get_document_request_fee('Barangay Clearance'),
    'Certificate of Residency' => get_document_request_fee('Certificate of Residency'),
    'Certificate of Indigency' => get_document_request_fee('Certificate of Indigency'),
    'Certificate of Indigency (Special)' => get_document_request_fee('Certificate of Indigency (Special)'),
    'Business Clearance' => 500.00,
];

$business_fee = 500.00;

try {
    $pdo->beginTransaction();
    $notification_warning = null;

    if ($type === 'document') {
        $stmt = $pdo->prepare("SELECT dr.id, dr.document_type, dr.price, dr.payment_status, dr.or_number, dr.status, COALESCE(NULLIF(dr.requested_by_user_id, 0), r.user_id) AS recipient_user_id, CONCAT(r.first_name, ' ', r.last_name) AS resident_name FROM document_requests dr LEFT JOIN residents r ON dr.resident_id = r.id WHERE dr.id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Document request not found.');
        }

        $amount_due = get_document_request_fee($row['document_type'] ?? '');
        if ($amount_due <= 0) {
            throw new Exception('This document does not require payment.');
        }

        $table = 'document_requests';
        $item_name = (string) ($row['document_type'] ?? 'Document Request');
    } else {
        $stmt = $pdo->prepare("SELECT bt.id, bt.transaction_type, bt.payment_status, bt.or_number, bt.status, r.user_id AS recipient_user_id, CONCAT(r.first_name, ' ', r.last_name) AS resident_name FROM business_transactions bt LEFT JOIN residents r ON bt.resident_id = r.id WHERE bt.id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Business transaction not found.');
        }

        $amount_due = $business_fee;
        $table = 'business_transactions';
        $item_name = (string) ($row['transaction_type'] ?? 'Business Request');
    }

    if ($cash_received < $amount_due) {
        throw new Exception('Cash amount is insufficient for this request.');
    }

    $change_amount = round($cash_received - $amount_due, 2);

    $existing_or = trim((string) ($row['or_number'] ?? ''));
    $generated_or = 'OR-' . date('YmdHis') . '-' . $type . '-' . $id;
    $or_number = $existing_or !== '' ? $existing_or : $generated_or;

    if ($type === 'document') {
        $completed_status = normalize_request_status_for_storage($pdo, 'document_requests', 'Completed');
        $update = $pdo->prepare("UPDATE document_requests SET payment_status = 'Paid', payment_date = NOW(), or_number = ?, cash_received = ?, change_amount = ?, status = CASE WHEN UPPER(status) IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN status ELSE ? END WHERE id = ?");
        $update->execute([$or_number, $cash_received, $change_amount, $completed_status, $id]);
    } else {
        $completed_status = normalize_request_status_for_storage($pdo, 'business_transactions', 'Completed');
        $update = $pdo->prepare("UPDATE {$table} SET payment_status = 'Paid', payment_date = NOW(), or_number = ?, cash_received = ?, change_amount = ?, status = CASE WHEN UPPER(status) IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN status ELSE ? END WHERE id = ?");
        $update->execute([$or_number, $cash_received, $change_amount, $completed_status, $id]);
    }

    if (!empty($row['recipient_user_id'])) {
        $notification_sent = NotificationSystem::notify_payment_update(
            $pdo,
            (int) $row['recipient_user_id'],
            $item_name,
            'Paid',
            $or_number,
            $type === 'document' ? 'my-document-requests.php' : 'my-requests.php'
        );

        if (!$notification_sent) {
            $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
            $notification_warning = 'Payment recorded, but web-app notification failed' . ($detail ? ': ' . $detail : '.');
            error_log('Notification delivery failed in make-cash-payment for id=' . $id . ' type=' . $type);
        }

        if (!request_has_terminal_status($row['status'] ?? null)) {
            $status_notification_sent = NotificationSystem::notify_document_status(
                $pdo,
                (int) $row['recipient_user_id'],
                $item_name,
                'Completed',
                $type === 'document' ? 'my-document-requests.php' : 'my-requests.php'
            );
            if (!$status_notification_sent && $notification_warning === null) {
                $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
                $notification_warning = 'Payment recorded, but status web-app notification failed' . ($detail ? ': ' . $detail : '.');
            }
        }
    }

    log_activity_db(
        $pdo,
        'cash_payment',
        $type . '_request',
        $id,
        'Cash payment received. Amount: ' . number_format($cash_received, 2) . ', Change: ' . number_format($change_amount, 2) . ', OR: ' . $or_number,
        null,
        'Paid'
    );

    $pdo->commit();

    $response = [
        'success' => true,
        'message' => 'Payment recorded successfully.',
        'or_number' => $or_number,
        'amount_due' => number_format($amount_due, 2, '.', ''),
        'cash_received' => number_format($cash_received, 2, '.', ''),
        'change_amount' => number_format($change_amount, 2, '.', ''),
        'status' => !request_has_terminal_status($row['status'] ?? null) ? 'Completed' : null,
    ];

    if ($notification_warning !== null) {
        $response['warning'] = $notification_warning;
    }

    echo json_encode($response);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;
