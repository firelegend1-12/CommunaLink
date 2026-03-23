<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
require_login();

if (!is_admin_or_official()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$permit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($permit_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid permit ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM business_permits WHERE id = ?");
    $stmt->execute([$permit_id]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($permit) {
        echo json_encode(['success' => true, 'data' => $permit]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Permit details not found']);
    }
} catch (PDOException $e) {
    error_log('get-permit-details failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error while fetching permit details.']);
}
exit();
