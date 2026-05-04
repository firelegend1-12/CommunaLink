<?php
/**
 * AJAX Handler: Get Incident Notes
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_incidents', 403, 'Forbidden');

$incident_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$incident_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid incident ID']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? 0;
$current_role = $_SESSION['role'] ?? '';
$is_admin = in_array($current_role, ['admin', 'super_admin'], true);

try {
    $stmt = $pdo->prepare("SELECT 
        n.id,
        n.note,
        n.created_at,
        n.updated_at,
        n.user_id,
        u.fullname AS user_name,
        u.role AS user_role
    FROM incident_notes n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.incident_id = ?
    ORDER BY n.created_at ASC");
    $stmt->execute([$incident_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notes as &$note) {
        $note['is_owner'] = ((int)$note['user_id'] === (int)$current_user_id);
        $note['is_admin'] = $is_admin;
    }
    unset($note);

    echo json_encode(['success' => true, 'notes' => $notes]);
} catch (PDOException $e) {
    error_log('get-incident-notes failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while fetching notes.']);
}
