<?php
require_once '../../config/init.php';
header('Content-Type: application/json');

$incident_id = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
if (!$incident_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid incident ID']);
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE target_type = 'incident' AND target_id = ? ORDER BY created_at DESC");
$stmt->execute([$incident_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success' => true, 'logs' => $logs]); 