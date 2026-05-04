<?php
/**
 * AJAX Handler: Add Incident Note
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_incidents', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

if (!$incident_id || $note === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Incident ID and note text are required.']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("INSERT INTO incident_notes (incident_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$incident_id, $current_user_id, $note]);
    $note_id = (int)$pdo->lastInsertId();

    log_activity_db(
        $pdo,
        'add_note',
        'incident',
        $incident_id,
        "Added admin note to incident #{$incident_id}",
        null,
        null
    );

    echo json_encode(['success' => true, 'note_id' => $note_id, 'created_at' => date('Y-m-d H:i:s')]);
} catch (PDOException $e) {
    error_log('add-incident-note failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while adding note.']);
}
