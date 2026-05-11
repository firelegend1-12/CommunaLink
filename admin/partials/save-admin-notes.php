<?php
/**
 * Save Admin Notes Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_documents', 403, 'Forbidden');

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

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';

if (empty($id) || !in_array($type, ['document', 'business'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $table = $type === 'document' ? 'document_requests' : 'business_transactions';
    $target_type = $type === 'document' ? 'document_request' : 'business_request';

    $check = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    if (!$check->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE {$table} SET admin_notes = ? WHERE id = ?");
    $stmt->execute([$admin_notes, $id]);

    log_activity_db(
        $pdo,
        'save_admin_notes',
        $target_type,
        $id,
        "Saved admin notes for {$target_type} #{$id}",
        null,
        null
    );

    echo json_encode(['success' => true, 'admin_notes' => $admin_notes]);
} catch (PDOException $e) {
    error_log('save-admin-notes failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while saving admin notes.']);
}
exit;
