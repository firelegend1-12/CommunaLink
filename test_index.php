<?php
require_once 'config/init.php';

echo "1. Testing document_requests index utilization:\n";
$stmt = $pdo->query("EXPLAIN SELECT * FROM document_requests WHERE requested_by_user_id = 1 ORDER BY date_requested DESC LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n2. Testing incidents index utilization:\n";
$stmt = $pdo->query("EXPLAIN SELECT id, type, description, location, reported_at, status FROM incidents WHERE resident_user_id = 1 ORDER BY reported_at DESC LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n3. Testing announcements index utilization:\n";
$stmt = $pdo->query("EXPLAIN SELECT title FROM announcements ORDER BY created_at DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
