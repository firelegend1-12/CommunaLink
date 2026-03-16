<?php
session_start();
require_once '../../config/database.php';
$resident_id = $_SESSION['resident_id'] ?? null;
if ($resident_id) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE resident_id = ? AND is_read = 0');
    $stmt->execute([$resident_id]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
} 