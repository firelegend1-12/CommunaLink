<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

$stmt = $pdo->query('SELECT i.*, u.fullname AS resident_name FROM incidents i LEFT JOIN users u ON i.resident_user_id = u.id ORDER BY i.reported_at DESC');
$incidents = $stmt->fetchAll();
echo json_encode(['incidents' => $incidents]); 