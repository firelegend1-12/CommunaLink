<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
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

if (!is_admin_or_official()) {
    http_response_code(403);
    emit_perf_headers($request_start, 'admin_fetch_live_incidents');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

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
    SUM(CASE WHEN reported_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
              AND reported_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
              AND status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_this_month,
    SUM(CASE WHEN reported_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
              AND reported_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
             THEN 1 ELSE 0 END) AS total_this_month
    FROM incidents");
$stats_row = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$monthly_total = (int)($stats_row['total_this_month'] ?? 0);
$monthly_resolved = (int)($stats_row['resolved_this_month'] ?? 0);

$stats = [
    'active_cases' => (int)($stats_row['active_cases'] ?? 0),
    'trending_today' => (int)($stats_row['trending_today'] ?? 0),
    'resolution_rate' => $monthly_total > 0 ? (int)round(($monthly_resolved / $monthly_total) * 100) : 0
];

emit_perf_headers($request_start, 'admin_fetch_live_incidents');
echo json_encode([
    'incidents' => $incidents,
    'stats' => $stats
]); 