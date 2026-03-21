<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sql = "SELECT *, CASE WHEN id_number IS NOT NULL AND id_number != '' THEN id_number ELSE CONCAT('BR-', YEAR(created_at), '-', LPAD(id, 4, '0')) END AS display_id_number FROM residents";
$params = [];
$voter_status = isset($_GET['voter_status']) && $_GET['voter_status'] !== 'All' ? sanitize_input($_GET['voter_status']) : null;

if (!empty($search_query)) {
    $sql .= " WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR id_number LIKE ?)";
    $search_param = "%{$search_query}%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    
    if ($voter_status) {
        $sql .= " AND voter_status = ?";
        $params[] = $voter_status;
    }
} else if ($voter_status) {
    $sql .= " WHERE voter_status = ?";
    $params[] = $voter_status;
}
$sql .= " ORDER BY last_name, first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($residents);
exit(); 