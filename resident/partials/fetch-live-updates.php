<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$resident_id = (int) ($_SESSION['resident_id'] ?? 0);

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'resident' || $user_id <= 0) {
    echo json_encode([
        'error' => 'Not logged in',
        'doc_requests' => [],
        'biz_requests' => []
    ]);
    exit;
}

if ($resident_id <= 0) {
    $resolved_resident_id = (int) (get_resident_id($pdo, $user_id) ?? 0);
    if ($resolved_resident_id > 0) {
        $resident_id = $resolved_resident_id;
        $_SESSION['resident_id'] = $resident_id;
    }
}

// Notifications intentionally removed from resident live updates.

// Fetch document requests
$stmt = $pdo->prepare('SELECT id, document_type, purpose, date_requested, status, payment_status, or_number, reference_number, remarks, NULL AS admin_notes, details FROM document_requests WHERE requested_by_user_id = ? OR (requested_by_user_id IS NULL AND resident_id = ?) ORDER BY date_requested DESC');
$stmt->execute([$user_id, $resident_id]);
$doc_requests = $stmt->fetchAll();
foreach ($doc_requests as &$doc_row) {
    $doc_row['status'] = get_request_display_status(
        $doc_row['status'] ?? null,
        $doc_row['payment_status'] ?? null,
        document_request_requires_payment($doc_row['document_type'] ?? '')
    );
    $doc_row['reference_number'] = get_request_reference_number_from_row($doc_row, 'document');
}
unset($doc_row);

// Fetch business transactions
$stmt = $pdo->prepare('SELECT id, business_name, business_type, transaction_type, application_date, status, payment_status, or_number, reference_number, remarks, NULL AS admin_notes FROM business_transactions WHERE resident_id = ? ORDER BY application_date DESC');
$stmt->execute([$resident_id]);
$biz_requests = $stmt->fetchAll();
foreach ($biz_requests as &$biz_row) {
    $biz_row['status'] = get_request_display_status($biz_row['status'] ?? null, $biz_row['payment_status'] ?? null, true);
    $biz_row['reference_number'] = get_request_reference_number_from_row($biz_row, 'business');
}
unset($biz_row);

echo json_encode([
    'error' => $resident_id > 0 ? null : 'Resident profile not found.',
    'doc_requests' => $doc_requests,
    'biz_requests' => $biz_requests
]);
