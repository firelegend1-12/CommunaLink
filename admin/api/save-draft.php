<?php
/**
 * Admin-Authorized Draft Save Endpoint
 * Inserts an announcement row with status='draft'.
 */

require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/permission_checker.php';
require_once __DIR__ . '/../../includes/storage_manager.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_announcements', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

function nullable_input($value) {
    $normalized = sanitize_input((string) $value);
    return $normalized === '' ? null : $normalized;
}

function parse_hidden_boolean($value) {
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
}

$title = trim((string)($_POST['title'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));
$target_audience = sanitize_input($_POST['target_audience'] ?? 'all');
$is_urgent = !empty($_POST['is_urgent']) ? 1 : 0;
$priority = $is_urgent ? 'urgent' : 'normal';

$is_scheduled = !empty($_POST['is_scheduled']);
$publish_date = null;
if ($is_scheduled && !empty($_POST['publish_date'])) {
    $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : '00:00';
    $publish_date = $_POST['publish_date'] . ' ' . $publish_time . ':00';
} else {
    $publish_date = date('Y-m-d H:i:s');
}

$expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] . ' 23:59:59' : null;

$is_event = parse_hidden_boolean($_POST['is_event'] ?? '0') ? 1 : 0;
$event_date = $is_event ? nullable_input($_POST['event_date'] ?? '') : null;
$event_time = $is_event ? nullable_input($_POST['event_time'] ?? '') : null;
$event_location = $is_event ? nullable_input($_POST['event_location'] ?? '') : null;
$event_type = $is_event ? nullable_input($_POST['event_type'] ?? '') : null;

// Image upload optional for drafts
$image_path = null;
if (!empty($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        if (isset($allowed_mimes[$mime]) && ($file['size'] ?? 0) <= 5 * 1024 * 1024) {
            $storage_result = StorageManager::saveUploadedFile([
                'tmp_name' => $file['tmp_name'],
                'extension' => $allowed_mimes[$mime],
            ], 'admin/images/announcements', 'post_draft_');
            if (!empty($storage_result['success'])) {
                $stored_path = (string) ($storage_result['path'] ?? '');
                if (strpos($stored_path, 'admin/') === 0) {
                    $image_path = substr($stored_path, 6);
                } else {
                    $image_path = $stored_path;
                }
            }
        }
    }
}

try {
    $sql = "INSERT INTO announcements (user_id, title, content, image_path, status, priority, target_audience, publish_date, expiry_date, is_event, event_date, event_time, event_location, event_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $user_id,
        $title,
        $content,
        $image_path,
        'draft',
        $priority,
        $target_audience,
        $publish_date,
        $expiry_date,
        $is_event,
        $event_date,
        $event_time,
        $event_location,
        $event_type
    ]);

    $post_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'post_id' => (int)$post_id,
        'message' => 'Draft saved successfully.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save draft: ' . $e->getMessage()]);
}
