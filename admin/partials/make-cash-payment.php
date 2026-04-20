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
    'Barangay Clearance' => 50.00,
    'Certificate of Residency' => 50.00,
    'Certificate of Indigency' => 0.00,
    'Business Clearance' => 500.00,
];

$business_fee = 500.00;

try {
    $pdo->beginTransaction();
    $notification_warning = null;

    if ($type === 'document') {
        $stmt = $pdo->prepare("SELECT dr.id, dr.document_type, dr.price, dr.payment_status, dr.or_number, r.user_id, CONCAT(r.first_name, ' ', r.last_name) AS resident_name FROM document_requests dr LEFT JOIN residents r ON dr.resident_id = r.id WHERE dr.id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Document request not found.');
        }

        $amount_due = isset($row['price']) && $row['price'] !== null
            ? (float) $row['price']
            : (float) ($document_fees[$row['document_type']] ?? 50.00);

        $table = 'document_requests';
        $item_name = (string) ($row['document_type'] ?? 'Document Request');
    } else {
        $stmt = $pdo->prepare("SELECT bt.id, bt.transaction_type, bt.payment_status, bt.or_number, r.user_id, CONCAT(r.first_name, ' ', r.last_name) AS resident_name FROM business_transactions bt LEFT JOIN residents r ON bt.resident_id = r.id WHERE bt.id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Business transaction not found.');
        }

        $amount_due = $business_fee;
        $table = 'business_transactions';
        $item_name = 'Business Clearance';
    }

    if ($cash_received < $amount_due) {
        throw new Exception('Cash amount is insufficient for this request.');
    }

    $change_amount = round($cash_received - $amount_due, 2);

    $existing_or = trim((string) ($row['or_number'] ?? ''));
    $generated_or = 'OR-' . date('YmdHis') . '-' . $type . '-' . $id;
    $or_number = $existing_or !== '' ? $existing_or : $generated_or;

    $update = $pdo->prepare("UPDATE {$table} SET payment_status = 'Paid', payment_date = NOW(), or_number = ?, cash_received = ?, change_amount = ? WHERE id = ?");
    $update->execute([$or_number, $cash_received, $change_amount, $id]);

    if (!empty($row['user_id'])) {
        $notification_sent = NotificationSystem::notify_payment_update(
            $pdo,
            (int) $row['user_id'],
            $item_name,
            'Paid',
            $or_number,
            'my-requests.php'
        );

        if (!$notification_sent) {
            $notification_warning = 'Payment recorded, but notification delivery failed.';
            error_log('Notification delivery failed in make-cash-payment for id=' . $id . ' type=' . $type);
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
