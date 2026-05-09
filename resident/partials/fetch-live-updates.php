<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$resident_id = (int) ($_SESSION['resident_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode([
        'error' => 'Not logged in',
        'notifications' => [],
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

// Fetch notifications
$stmt = $pdo->prepare('SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Fetch document requests
$stmt = $pdo->prepare('SELECT id, document_type, purpose, date_requested, status, remarks, NULL AS admin_notes, details FROM document_requests WHERE requested_by_user_id = ? OR (requested_by_user_id IS NULL AND resident_id = ?) ORDER BY date_requested DESC');
$stmt->execute([$user_id, $resident_id]);
$doc_requests = $stmt->fetchAll();
foreach ($doc_requests as &$doc_row) {
    $doc_row['status'] = normalize_request_status_display($doc_row['status'] ?? null);
}
unset($doc_row);

// Fetch business transactions
$stmt = $pdo->prepare('SELECT id, business_name, business_type, transaction_type, application_date, status, remarks, NULL AS admin_notes FROM business_transactions WHERE resident_id = ? ORDER BY application_date DESC');
$stmt->execute([$resident_id]);
$biz_requests = $stmt->fetchAll();
foreach ($biz_requests as &$biz_row) {
    $biz_row['status'] = normalize_request_status_display($biz_row['status'] ?? null);
}
unset($biz_row);

echo json_encode([
    'error' => $resident_id > 0 ? null : 'Resident profile not found.',
    'notifications' => $notifications,
    'doc_requests' => $doc_requests,
    'biz_requests' => $biz_requests
]); 
