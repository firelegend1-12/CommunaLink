<?php
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/functions.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_redirect('edit_residents', '../pages/residents.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
if (!$resident_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid resident ID.']);
    exit;
}

$new_token = bin2hex(random_bytes(32));
$new_expiry = date('Y-m-d H:i:s', strtotime('+1 year'));
$stmt = $pdo->prepare("UPDATE residents SET qr_token = ?, qr_token_expires_at = ? WHERE id = ?");
$stmt->execute([$new_token, $new_expiry, $resident_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true, 'token' => $new_token, 'expires_at' => $new_expiry]);
} else {
    echo json_encode(['success' => false, 'error' => 'Resident not found or token unchanged.']);
}
