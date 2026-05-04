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

// Deprecated: admin_remarks is no longer used. Notes are now managed via incident_notes table.
try {
    http_response_code(410);
    echo json_encode(['success' => false, 'error' => 'Admin remarks field is deprecated. Use the incident notes thread instead.']);
} catch (PDOException $e) {
    error_log('update-incident-remarks failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while updating remarks.']);
}
