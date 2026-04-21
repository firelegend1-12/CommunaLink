<?php
/**
 * Unified Post Handler - Handles both Announcements and Events
 */
session_start();
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/storage_manager.php';
require_once '../../includes/notification_system.php';

require_login();
require_any_permission_or_redirect(['manage_announcements', 'manage_events'], '../pages/announcements.php');

$user_id = $_SESSION['user_id'];

/**
 * Validates post status
 */
function validate_post_status($status) {
    $allowed = ['active', 'draft'];
    return in_array($status, $allowed, true) ? $status : 'active';
}

function parse_hidden_boolean($value) {
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
}

function nullable_input($value) {
    $normalized = sanitize_input((string) $value);
    return $normalized === '' ? null : $normalized;
}

/**
 * Handles image uploads for posts
 */
function upload_post_image($file) {
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }

    $max_size = 5 * 1024 * 1024; // 5MB
    if (($file['size'] ?? 0) > $max_size) {
        throw new RuntimeException('File size exceeds the 5MB limit.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];

    if (!isset($allowed_mimes[$mime])) {
        throw new RuntimeException('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }

    $storage_result = StorageManager::saveUploadedFile([
        'tmp_name' => $file['tmp_name'],
        'extension' => $allowed_mimes[$mime],
    ], 'admin/images/announcements', 'post_');

    if (!$storage_result['success']) {
        throw new RuntimeException((string) ($storage_result['error'] ?? 'Failed to upload the image.'));
    }

    $stored_path = (string) ($storage_result['path'] ?? '');
    if (strpos($stored_path, 'admin/') === 0) {
        return substr($stored_path, 6);
    }

    return $stored_path;
}

// --- Handle Add Post (Announcement or Event) ---
if (isset($_POST['add_post'])) {
    csrf_require();

    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $status = validate_post_status(sanitize_input($_POST['status'] ?? 'active'));
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $priority = $is_urgent ? 'urgent' : 'normal';
    $target_audience = sanitize_input($_POST['target_audience'] ?? 'all');
    
    // Scheduling and Expiry
    $is_scheduled = isset($_POST['is_scheduled']) ? 1 : 0;
    $publish_date = null;
    if ($is_scheduled && !empty($_POST['publish_date'])) {
        $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : '00:00';
        $publish_date = $_POST['publish_date'] . ' ' . $publish_time . ':00';
    } else {
        $publish_date = date('Y-m-d H:i:s');
    }

    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] . ' 23:59:59' : null;
    
    // Event specific fields
    $is_event = parse_hidden_boolean($_POST['is_event'] ?? '0') ? 1 : 0;
    $event_date = $is_event ? nullable_input($_POST['event_date'] ?? '') : null;
    $event_time = $is_event ? nullable_input($_POST['event_time'] ?? '') : null;
    $event_location = $is_event ? nullable_input($_POST['event_location'] ?? '') : null;
    $event_type = $is_event ? nullable_input($_POST['event_type'] ?? '') : null;

    if ($is_event && empty($event_date)) {
        $_SESSION['error_message'] = "Event date is required when posting an event.";
        header('Location: ../pages/announcements.php');
        exit;
    }

    if (empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Title and content are required.";
        header('Location: ../pages/announcements.php');
        exit;
    }

    try {
        $image_path = upload_post_image($_FILES['image'] ?? null);

        $sql = "INSERT INTO announcements (user_id, title, content, image_path, status, priority, target_audience, publish_date, expiry_date, is_event, event_date, event_time, event_location, event_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $title, $content, $image_path, $status, $priority, $target_audience, $publish_date, $expiry_date, $is_event, $event_date, $event_time, $event_location, $event_type]);
        
        $post_id = $pdo->lastInsertId();
        $type_label = $is_event ? 'event' : 'announcement';
        
        log_activity_db($pdo, 'add', $type_label, $post_id, "Added new $type_label", null, "Title: $title");

        $broadcast_result = null;
        $is_live_now = ($status === 'active') && ($publish_date === null || strtotime((string)$publish_date) <= time());
        if ($is_live_now) {
            $broadcast_result = NotificationSystem::notify_public_post($pdo, [
                'title' => $title,
                'content' => $content,
                'target_audience' => $target_audience,
                'is_event' => $is_event,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'event_location' => $event_location,
            ]);

            if (!$broadcast_result['success']) {
                error_log('Post broadcast notification failed: ' . (string)($broadcast_result['error'] ?? 'unknown error'));
            }
        }

        $_SESSION['announcement_success_message'] = ucfirst($type_label) . " posted successfully.";
        if (is_array($broadcast_result) && !empty($broadcast_result['success'])) {
            $_SESSION['announcement_success_message'] .= ' Sent in-app alerts to ' . (int)$broadcast_result['notification_created']
                . ' resident(s) and email notifications to ' . (int)$broadcast_result['email_sent'] . '.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ../pages/announcements.php');
    exit;
}

// --- Handle Update Post ---
if (isset($_POST['update_post'])) {
    csrf_require();

    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $status = validate_post_status(sanitize_input($_POST['status'] ?? 'active'));
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $priority = $is_urgent ? 'urgent' : 'normal';
    $target_audience = sanitize_input($_POST['target_audience'] ?? 'all');

    // Scheduling and Expiry
    $is_scheduled = isset($_POST['is_scheduled']) ? 1 : 0;
    $publish_date = null;
    if ($is_scheduled && !empty($_POST['publish_date'])) {
        $publish_time = !empty($_POST['publish_time']) ? $_POST['publish_time'] : '00:00';
        $publish_date = $_POST['publish_date'] . ' ' . $publish_time . ':00';
    } else {
        // If not scheduled, but it was previously scheduled, we might want to keep the old publish_date or reset to now
        // For simplicity, if they uncheck "scheduled", we'll set it to NOW if they are making it active
        $publish_date = ($status === 'active') ? date('Y-m-d H:i:s') : null;
    }

    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] . ' 23:59:59' : null;
    
    // Event specific fields
    $is_event = parse_hidden_boolean($_POST['is_event'] ?? '0') ? 1 : 0;
    $event_date = $is_event ? nullable_input($_POST['event_date'] ?? '') : null;
    $event_time = $is_event ? nullable_input($_POST['event_time'] ?? '') : null;
    $event_location = $is_event ? nullable_input($_POST['event_location'] ?? '') : null;
    $event_type = $is_event ? nullable_input($_POST['event_type'] ?? '') : null;

    if ($is_event && empty($event_date)) {
        $_SESSION['error_message'] = "Event date is required when updating an event.";
        header('Location: ../pages/announcements.php');
        exit;
    }

    if (!$post_id || empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Post ID, title and content are required.";
        header('Location: ../pages/announcements.php');
        exit;
    }

    try {
        // Fetch old data for logging and image cleanup
        $stmt_old = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt_old->execute([$post_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

        if (!$old_data) {
            $_SESSION['error_message'] = "Post not found.";
            header('Location: ../pages/announcements.php');
            exit;
        }

        $image_path = upload_post_image($_FILES['image'] ?? null) ?: $old_data['image_path'];

        $sql = "UPDATE announcements SET 
                title = ?, content = ?, status = ?, priority = ?, image_path = ?, 
                target_audience = ?, publish_date = ?, expiry_date = ?,
                is_event = ?, event_date = ?, event_time = ?, event_location = ?, event_type = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $status, $priority, $image_path, $target_audience, $publish_date, $expiry_date, $is_event, $event_date, $event_time, $event_location, $event_type, $post_id]);
        
        log_activity_db($pdo, 'edit', $is_event ? 'event' : 'announcement', $post_id, "Updated post", null, "Title: $title");

        $_SESSION['announcement_success_message'] = "Post updated successfully.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ../pages/announcements.php');
    exit;
}

// --- Handle Delete Post ---
if (isset($_POST['delete_post'])) {
    csrf_require();

    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

    if (!$post_id) {
        $_SESSION['error_message'] = "Invalid post ID.";
        header('Location: ../pages/announcements.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT image_path, is_event FROM announcements WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post && $post['image_path']) {
            StorageManager::deleteStoredPath((string) $post['image_path']);
        }

        $delete_stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $delete_stmt->execute([$post_id]);
        
        log_activity_db($pdo, 'delete', $post['is_event'] ? 'event' : 'announcement', $post_id, "Deleted post");

        $_SESSION['announcement_success_message'] = "Post deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    header('Location: ../pages/announcements.php');
    exit;
}

// Fallback
header('Location: ../pages/announcements.php');
exit;
