<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

$request_start = microtime(true);

function emit_perf_headers(float $start, string $endpoint): void
{
    $elapsed_ms = (microtime(true) - $start) * 1000;
    header('X-Endpoint: ' . $endpoint);
    header('X-Response-Time-Ms: ' . number_format($elapsed_ms, 2, '.', ''));
}

require_login();
require_permission_or_json('manage_incidents', 403, 'Forbidden');

$stats_stmt = $pdo->query("SELECT
    (SELECT COUNT(*) FROM document_requests WHERE status = 'Pending') AS pending_doc_requests,
    (SELECT COUNT(*) FROM business_transactions WHERE status = 'Pending') AS pending_biz_requests,
    (SELECT COUNT(*) FROM businesses) AS business_count,
    (SELECT COUNT(*) FROM residents) AS resident_count,
    (SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')) AS active_incidents,
    (SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()) AS upcoming_events");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$pending_doc_requests = (int)($stats['pending_doc_requests'] ?? 0);
$pending_biz_requests = (int)($stats['pending_biz_requests'] ?? 0);
$pending_requests = $pending_doc_requests + $pending_biz_requests;
$business_count = (int)($stats['business_count'] ?? 0);
$resident_count = (int)($stats['resident_count'] ?? 0);
$active_incidents = (int)($stats['active_incidents'] ?? 0);
$upcoming_events = (int)($stats['upcoming_events'] ?? 0);

emit_perf_headers($request_start, 'admin_dashboard_stats');
echo json_encode([
    'pending_requests' => (int)$pending_requests,
    'pending_doc_requests' => (int)$pending_doc_requests,
    'pending_biz_requests' => (int)$pending_biz_requests,
    'business_count' => (int)$business_count,
    'resident_count' => (int)$resident_count,
    'active_incidents' => (int)$active_incidents,
    'upcoming_events' => (int)$upcoming_events,
]); 