<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sql = "SELECT id, username, fullname, email, role, created_at, last_login FROM users";
$params = [];
if (!empty($search_query)) {
    $sql .= " WHERE username LIKE ? OR fullname LIKE ? OR email LIKE ?";
    $search_param = "%{$search_query}%";
    $params = [$search_param, $search_param, $search_param];
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users);
exit(); 