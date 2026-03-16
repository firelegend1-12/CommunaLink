<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'overall';

$sql = "SELECT * FROM incidents WHERE 1";
$params = [];

switch ($filter) {
    case 'today':
        $sql .= " AND DATE(date_reported) = CURDATE()";
        break;
    case 'week':
        $sql .= " AND YEARWEEK(date_reported, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $sql .= " AND YEAR(date_reported) = YEAR(CURDATE()) AND MONTH(date_reported) = MONTH(CURDATE())";
        break;
    case 'year':
        $sql .= " AND YEAR(date_reported) = YEAR(CURDATE())";
        break;
    // 'overall' or any other value: no additional filter
}

$sql .= " ORDER BY date_reported DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($incidents);
exit(); 