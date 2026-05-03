<?php
/**
 * AJAX Handler: Get Incident Details
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/storage_manager.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_incidents', 403, 'Forbidden');

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
if ($current_user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Incident ID']);
    exit;
}

try {
    // 1. Fetch Incident Info + Reporter Info
    $stmt = $pdo->prepare("SELECT i.*, i.media_path AS image_path, u.fullname AS reporter_name, u.email AS reporter_email, r.contact_no AS reporter_contact, r.id AS resident_id
                           FROM incidents i
                           LEFT JOIN users u ON i.resident_user_id = u.id
                           LEFT JOIN residents r ON u.id = r.user_id
                           WHERE i.id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Incident report not found']);
        exit;
    }

    $incident['official_remarks'] = (string)($incident['admin_remarks'] ?? '');

    // 2. Determine if author-scoped notes table exists (for compatibility with non-migrated environments)
    $notes_table_stmt = $pdo->query("SHOW TABLES LIKE 'incident_admin_notes'");
    $has_notes_table = $notes_table_stmt && $notes_table_stmt->rowCount() > 0;

    if ($has_notes_table) {
        // Fetch note owned by the current account
        $note_stmt = $pdo->prepare("SELECT note_text, updated_at FROM incident_admin_notes WHERE incident_id = ? AND author_user_id = ? LIMIT 1");
        $note_stmt->execute([$id, $current_user_id]);
        $my_note = $note_stmt->fetch(PDO::FETCH_ASSOC);

        $incident['admin_note'] = (string)($my_note['note_text'] ?? '');
        if ($incident['admin_note'] === '') {
            $incident['admin_note'] = (string)($incident['admin_remarks'] ?? '');
        }
        $incident['admin_note_updated_at'] = $my_note['updated_at'] ?? null;

        // Fetch other responders' notes only (read-only team feed)
        $team_stmt = $pdo->prepare("SELECT n.author_user_id, n.note_text, n.updated_at, u.fullname AS author_name, u.role AS author_role
                                    FROM incident_admin_notes n
                                    INNER JOIN users u ON n.author_user_id = u.id
                                    WHERE n.incident_id = ?
                                      AND n.author_user_id <> ?
                                      AND n.note_text IS NOT NULL
                                      AND TRIM(n.note_text) <> ''
                                    ORDER BY n.updated_at DESC");
        $team_stmt->execute([$id, $current_user_id]);
        $incident['team_notes'] = $team_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        // Legacy fallback: keep app functional before migration
        $incident['admin_note'] = (string)($incident['admin_remarks'] ?? '');
        $incident['admin_note_updated_at'] = null;
        $incident['team_notes'] = [];
    }

    $imagePath = (string)($incident['image_path'] ?? '');
    $incident['image_url'] = $imagePath !== '' ? StorageManager::resolvePublicUrl($imagePath) : '';

    echo json_encode([
        'success' => true,
        'data' => $incident
    ]);

} catch (PDOException $e) {
    error_log('get-incident-details failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while fetching incident details.']);
}
