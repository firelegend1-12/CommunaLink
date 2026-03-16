<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$business_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($business_id > 0) {
    $sql = "SELECT b.*, r.first_name, r.last_name, r.middle_initial, r.id_number FROM businesses b JOIN residents r ON b.resident_id = r.id WHERE b.id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($business ? [$business] : []);
    exit();
}

$sql = "SELECT b.*, r.first_name, r.last_name, r.middle_initial, r.id_number FROM businesses b JOIN residents r ON b.resident_id = r.id";
$params = [];
if (!empty($search_query)) {
    $sql .= " WHERE b.business_name LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ?";
    $search_param = "%{$search_query}%";
    $params = [$search_param, $search_param, $search_param];
}
$sql .= " ORDER BY b.business_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($businesses);
exit(); 