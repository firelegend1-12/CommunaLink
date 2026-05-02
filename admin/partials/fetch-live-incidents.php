<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/storage_manager.php';
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

// Fetch Incidents (alias media_path as image_path for frontend compatibility)
$stmt = $pdo->query('SELECT i.*, i.media_path AS image_path, u.fullname AS resident_name FROM incidents i LEFT JOIN users u ON i.resident_user_id = u.id ORDER BY i.reported_at DESC');
$incidents = $stmt->fetchAll();

foreach ($incidents as &$incident) {
    $imagePath = (string)($incident['image_path'] ?? '');
    $incident['image_url'] = $imagePath !== '' ? StorageManager::resolvePublicUrl($imagePath) : '';
}
unset($incident);

// Fetch Latest Stats for UI Sync in one DB roundtrip
$stats_stmt = $pdo->query("SELECT
    SUM(CASE WHEN status IN ('Pending', 'In Progress') THEN 1 ELSE 0 END) AS active_cases,
    SUM(CASE WHEN reported_at >= NOW() - INTERVAL 1 DAY THEN 1 ELSE 0 END) AS trending_today,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_all_time,
    COUNT(*) AS total_all_time
    FROM incidents");
$stats_row = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$all_time_total = (int)($stats_row['total_all_time'] ?? 0);
$all_time_resolved = (int)($stats_row['resolved_all_time'] ?? 0);

$stats = [
    'active_cases' => (int)($stats_row['active_cases'] ?? 0),
    'trending_today' => (int)($stats_row['trending_today'] ?? 0),
    'resolution_rate' => $all_time_total > 0 ? (int)round(($all_time_resolved / $all_time_total) * 100) : 0
];

emit_perf_headers($request_start, 'admin_fetch_live_incidents');
echo json_encode([
    'incidents' => $incidents,
    'stats' => $stats
]); 