<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');

require_login();

if (!is_admin_or_official()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Fetch Incidents (alias media_path as image_path for frontend compatibility)
$stmt = $pdo->query('SELECT i.*, i.media_path AS image_path, u.fullname AS resident_name FROM incidents i LEFT JOIN users u ON i.resident_user_id = u.id ORDER BY i.reported_at DESC');
$incidents = $stmt->fetchAll();

// Fetch Latest Stats for UI Sync
$stats = [
    'active_cases' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn(),
    'trending_today' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE reported_at >= NOW() - INTERVAL 1 DAY")->fetchColumn(),
    'resolution_rate' => 0
];

// Monthly Resolution Rate
$stmt_m = $pdo->query("SELECT COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved, COUNT(*) as total FROM incidents WHERE MONTH(reported_at) = MONTH(CURRENT_DATE()) AND YEAR(reported_at) = YEAR(CURRENT_DATE())");
$m_data = $stmt_m->fetch();
$stats['resolution_rate'] = ($m_data['total'] > 0) ? round(($m_data['resolved'] / $m_data['total']) * 100) : 0;

echo json_encode([
    'incidents' => $incidents,
    'stats' => $stats
]); 