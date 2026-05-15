<?php
/**
 * AJAX handler: fetch receipt details for paid requests/transactions.
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_any_permission_or_json(['financial_management', 'view_monitoring_requests', 'view_residents'], 403, 'Forbidden');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';

if (!$id || !in_array($type, ['document', 'business'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid receipt request.']);
    exit;
}

try {
    if ($type === 'document') {
        $stmt = $pdo->prepare("
            SELECT
                dr.id,
                'document' AS request_type,
                dr.document_type AS item_name,
                dr.document_type,
                dr.status,
                dr.payment_status,
                dr.or_number,
                dr.date_requested AS request_date,
                dr.payment_date,
                dr.purpose,
                dr.details,
                dr.cash_received,
                dr.change_amount,
                CONCAT(COALESCE(r.first_name, ''), ' ', COALESCE(r.last_name, '')) AS resident_name
            FROM document_requests dr
            LEFT JOIN residents r ON dr.resident_id = r.id
            WHERE dr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Receipt source record not found.']);
            exit;
        }

        $amount_due = (float) get_document_request_fee($receipt['document_type'] ?? '');
    } else {
        $stmt = $pdo->prepare("
            SELECT
                bt.id,
                'business' AS request_type,
                bt.transaction_type,
                bt.remarks,
                bt.status,
                bt.payment_status,
                bt.or_number,
                bt.application_date AS request_date,
                bt.payment_date,
                JSON_OBJECT(
                    'business_name', bt.business_name,
                    'business_type', bt.business_type,
                    'owner_name', bt.owner_name,
                    'address', bt.address
                ) AS details,
                bt.cash_received,
                bt.change_amount,
                CONCAT(COALESCE(r.first_name, ''), ' ', COALESCE(r.last_name, '')) AS resident_name
            FROM business_transactions bt
            LEFT JOIN residents r ON bt.resident_id = r.id
            WHERE bt.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Receipt source record not found.']);
            exit;
        }

        $receipt['item_name'] = get_business_transaction_display_name($receipt['transaction_type'] ?? '', $receipt['remarks'] ?? '');
        $receipt['document_type'] = $receipt['item_name'];
        $amount_due = (float) get_business_transaction_fee($receipt['transaction_type'] ?? '', $receipt['remarks'] ?? '');
    }

    $or_number = trim((string) ($receipt['or_number'] ?? ''));
    $payment_status = trim((string) ($receipt['payment_status'] ?? ''));

    if ($payment_status !== 'Paid' || $or_number === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Receipt is not available until payment is recorded.']);
        exit;
    }

    $cash_received = isset($receipt['cash_received']) ? (float) $receipt['cash_received'] : null;
    $change_amount = isset($receipt['change_amount']) ? (float) $receipt['change_amount'] : null;
    $amount_paid = $amount_due > 0 ? $amount_due : max(0, ($cash_received ?? 0) - ($change_amount ?? 0));

    $details = [];
    if (!empty($receipt['details'])) {
        $decoded = json_decode((string) $receipt['details'], true);
        if (is_array($decoded)) {
            $details = $decoded;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $receipt['id'],
            'requestType' => (string) $receipt['request_type'],
            'referenceNumber' => get_request_reference_number(
                $receipt['request_type'] ?? $type,
                $receipt['id'] ?? $id,
                $receipt['request_date'] ?? null
            ),
            'residentName' => trim((string) ($receipt['resident_name'] ?? '')),
            'itemName' => (string) ($receipt['item_name'] ?? $receipt['document_type'] ?? 'Request'),
            'documentType' => (string) ($receipt['document_type'] ?? ''),
            'status' => get_request_display_status(
                $receipt['status'] ?? null,
                $payment_status,
                $type === 'document'
                    ? document_request_requires_payment($receipt['document_type'] ?? '')
                    : true
            ),
            'paymentStatus' => $payment_status,
            'orNumber' => $or_number,
            'requestDate' => $receipt['request_date'] ?? null,
            'paymentDate' => $receipt['payment_date'] ?? null,
            'purpose' => (string) ($receipt['purpose'] ?? ''),
            'amountDue' => number_format($amount_due, 2, '.', ''),
            'amountPaid' => number_format($amount_paid, 2, '.', ''),
            'cashReceived' => $cash_received !== null ? number_format($cash_received, 2, '.', '') : null,
            'changeAmount' => $change_amount !== null ? number_format($change_amount, 2, '.', '') : null,
            'details' => $details,
            'barangayName' => 'Barangay Pakiad',
        ],
    ]);
} catch (Throwable $e) {
    error_log('get-receipt-details failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while fetching receipt details.']);
}

exit;
