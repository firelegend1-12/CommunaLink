<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';
$resident_id = $_SESSION['resident_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
if (!$resident_id || !$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare('SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Fetch document requests
$stmt = $pdo->prepare('SELECT id, document_type, purpose, date_requested, status, remarks, details FROM document_requests WHERE resident_id = ? ORDER BY date_requested DESC');
$stmt->execute([$resident_id]);
$doc_requests = $stmt->fetchAll();

// Fetch business transactions
$stmt = $pdo->prepare('SELECT id, business_name, business_type, transaction_type, application_date, status, remarks FROM business_transactions WHERE resident_id = ? ORDER BY application_date DESC');
$stmt->execute([$resident_id]);
$biz_requests = $stmt->fetchAll();

echo json_encode([
    'notifications' => $notifications,
    'doc_requests' => $doc_requests,
    'biz_requests' => $biz_requests
]); 