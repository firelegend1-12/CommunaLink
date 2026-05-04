<?php
/**
 * AJAX Handler: Edit Incident Note
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

$note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

if (!$note_id || $note === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Note ID and note text are required.']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT user_id FROM incident_notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Note not found.']);
        exit;
    }

    if ((int)$existing['user_id'] !== (int)$current_user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only edit your own notes.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE incident_notes SET note = ? WHERE id = ?");
    $stmt->execute([$note, $note_id]);

    echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
} catch (PDOException $e) {
    error_log('edit-incident-note failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while updating note.']);
}
