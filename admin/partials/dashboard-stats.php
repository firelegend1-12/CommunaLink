<?php
require_once '../../config/database.php';

$pending_doc_requests = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending'")->fetchColumn();
$pending_biz_requests = $pdo->query("SELECT COUNT(*) FROM business_transactions WHERE status = 'PENDING'")->fetchColumn();
$pending_requests = $pending_doc_requests + $pending_biz_requests;
$business_count = $pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
$resident_count = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
$active_incidents = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn();
$upcoming_events = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()") ? $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn() : 0;

echo json_encode([
    'pending_requests' => (int)$pending_requests,
    'pending_doc_requests' => (int)$pending_doc_requests,
    'pending_biz_requests' => (int)$pending_biz_requests,
    'business_count' => (int)$business_count,
    'resident_count' => (int)$resident_count,
    'active_incidents' => (int)$active_incidents,
    'upcoming_events' => (int)$upcoming_events,
]); 