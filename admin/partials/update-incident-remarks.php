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
$remarks = isset($_POST['remarks']) ? trim((string)$_POST['remarks']) : '';
$has_official_remarks = array_key_exists('official_remarks', $_POST);
$official_remarks = $has_official_remarks ? trim((string)$_POST['official_remarks']) : '';
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Incident ID']);
    exit;
}

if ($current_user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    $check_stmt = $pdo->prepare("SELECT id FROM incidents WHERE id = ? LIMIT 1");
    $check_stmt->execute([$id]);
    if (!$check_stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Incident report not found']);
        exit;
    }

    $notes_table_stmt = $pdo->query("SHOW TABLES LIKE 'incident_admin_notes'");
    $has_notes_table = $notes_table_stmt && $notes_table_stmt->rowCount() > 0;

    $pdo->beginTransaction();

    if ($has_notes_table) {
        $stmt = $pdo->prepare("INSERT INTO incident_admin_notes (incident_id, author_user_id, note_text)
                               VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                                   note_text = VALUES(note_text),
                                   updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$id, $current_user_id, $remarks]);
    } else {
        // Legacy fallback before notes-table migration is applied
        $legacy_stmt = $pdo->prepare("UPDATE incidents SET admin_remarks = ? WHERE id = ?");
        $legacy_stmt->execute([$remarks, $id]);
    }

    if ($has_official_remarks) {
        $official_stmt = $pdo->prepare("UPDATE incidents SET admin_remarks = ? WHERE id = ?");
        $official_stmt->execute([$official_remarks, $id]);
    }

    $pdo->commit();

    log_activity_db(
        $pdo,
        'update_remarks',
        'incident',
        $id,
        "Updated personal admin note for incident #{$id}",
        null,
        $remarks
    );

    if ($has_official_remarks) {
        log_activity_db(
            $pdo,
            'update_remarks',
            'incident',
            $id,
            "Updated official remarks for incident #{$id}",
            null,
            $official_remarks
        );
    }

    echo json_encode(['success' => true, 'message' => $has_official_remarks ? 'Notes updated successfully' : 'Your note was updated successfully']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('update-incident-remarks failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while updating remarks.']);
}
