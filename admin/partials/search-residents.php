<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sql = "SELECT *, CASE WHEN id_number IS NOT NULL AND id_number != '' THEN id_number ELSE CONCAT('BR-', YEAR(created_at), '-', LPAD(id, 4, '0')) END AS display_id_number FROM residents";
$params = [];
if (!empty($search_query)) {
    $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?";
    $search_param = "%{$search_query}%";
    $params = [$search_param, $search_param, $search_param];
}
$sql .= " ORDER BY last_name, first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($residents);
exit(); 