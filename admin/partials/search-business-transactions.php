<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sql = "SELECT * FROM business_transactions WHERE status != 'DELETED'";
$params = [];
if (!empty($search_query)) {
    $sql .= " AND (owner_name LIKE ? OR business_name LIKE ?)";
    $search_param = "%{$search_query}%";
    $params = [$search_param, $search_param];
}
$sql .= " ORDER BY application_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($transactions);
exit(); 