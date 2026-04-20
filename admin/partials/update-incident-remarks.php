<?php
/**
 * AJAX Handler: Update Incident Remarks
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_incidents', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$remarks = isset($_POST['remarks']) ? sanitize_input($_POST['remarks']) : '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Incident ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE incidents SET admin_remarks = ? WHERE id = ?");
    $result = $stmt->execute([$remarks, $id]);

    if ($result) {
        log_activity_db(
            $pdo,
            'update_remarks',
            'incident',
            $id,
            "Updated admin remarks for incident #{$id}",
            null,
            $remarks
        );
        echo json_encode(['success' => true, 'message' => 'Remarks updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update remarks']);
    }

} catch (PDOException $e) {
    error_log('update-incident-remarks failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while updating remarks.']);
}
