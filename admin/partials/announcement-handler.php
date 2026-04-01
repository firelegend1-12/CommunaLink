<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/storage_manager.php';

// Check for admin role
if (!is_admin_or_official()) {
    $_SESSION['error_message'] = "You are not authorized to perform this action.";
    redirect_to('../pages/announcements.php');
}

$user_id = $_SESSION['user_id'];

function validate_announcement_status($status) {
    $allowed = ['active', 'draft'];
    return in_array($status, $allowed, true) ? $status : 'active';
}

function upload_announcement_image($file) {
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }

    $max_size = 5 * 1024 * 1024;
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
    ], 'admin/images/announcements', 'announcement_');

    if (!$storage_result['success']) {
        throw new RuntimeException((string) ($storage_result['error'] ?? 'Failed to upload the image.'));
    }

    $stored_path = (string) ($storage_result['path'] ?? '');
    if (strpos($stored_path, 'admin/') === 0) {
        return substr($stored_path, 6);
    }

    return $stored_path;
}

// --- Handle Add Announcement ---
if (isset($_POST['add_announcement'])) {
    csrf_require();

    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $status = validate_announcement_status(sanitize_input($_POST['status'] ?? 'active'));
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $priority = $is_urgent ? 'urgent' : 'normal';
    $image_path = null;

    if (empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Title and content are required.";
        redirect_to('../pages/announcements.php');
    }

    try {
        $image_path = upload_announcement_image($_FILES['image'] ?? null);

        $stmt = $pdo->prepare("INSERT INTO announcements (user_id, title, content, image_path, status, priority) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $content, $image_path, $status, $priority]);
        // Log add (readable format)
        $new_str = '';
        foreach (['title' => $title, 'content' => $content, 'image_path' => $image_path, 'status' => $status, 'priority' => $priority] as $k => $v) {
            if ($v) $new_str .= "$k: $v\n";
        }
        log_activity_db(
            $pdo,
            'add',
            'announcement',
            $pdo->lastInsertId(),
            'Added announcement',
            null,
            trim($new_str)
        );
        $_SESSION['announcement_success_message'] = "Announcement posted successfully.";
    } catch (RuntimeException $e) {
        $_SESSION['error_message'] = $e->getMessage();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/announcements.php');
}


// --- Handle Update Announcement ---
if (isset($_POST['update_announcement'])) {
    csrf_require();

    $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $status = validate_announcement_status(sanitize_input($_POST['status'] ?? 'active'));
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $priority = $is_urgent ? 'urgent' : 'normal';
    $image_path = null;

    if (!$announcement_id || empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Announcement ID, title and content are required.";
        redirect_to('../pages/announcements.php');
    }

    try {
        $image_path = upload_announcement_image($_FILES['image'] ?? null);

        // Fetch old values
        $stmt_old = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt_old->execute([$announcement_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

        if (!$old_data) {
            $_SESSION['error_message'] = "Announcement not found.";
            redirect_to('../pages/announcements.php');
        }

        $new_data = [
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'priority' => $priority,
            'image_path' => $image_path ?? $old_data['image_path']
        ];
        // Only log changed fields
        $changed_old = [];
        $changed_new = [];
        foreach ($new_data as $key => $new_val) {
            $old_val = $old_data[$key] ?? null;
            if ($old_val != $new_val) {
                $changed_old[$key] = $old_val;
                $changed_new[$key] = $new_val;
            }
        }
        $old_str = '';
        $new_str = '';
        foreach ($changed_old as $k => $v) $old_str .= "$k: $v\n";
        foreach ($changed_new as $k => $v) $new_str .= "$k: $v\n";
        if ($image_path) {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, status = ?, priority = ?, image_path = ? WHERE id = ?");
            $stmt->execute([$title, $content, $status, $priority, $image_path, $announcement_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, status = ?, priority = ? WHERE id = ?");
            $stmt->execute([$title, $content, $status, $priority, $announcement_id]);
        }
        // Log update (only if something changed)
        if (!empty($changed_old)) {
            log_activity_db(
                $pdo,
                'edit',
                'announcement',
                $announcement_id,
                'Updated announcement',
                trim($old_str),
                trim($new_str)
            );
        }
        $_SESSION['announcement_success_message'] = "Announcement updated successfully.";
    } catch (RuntimeException $e) {
        $_SESSION['error_message'] = $e->getMessage();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/announcements.php');
}


// --- Handle Delete Announcement ---
if (isset($_POST['delete_announcement'])) {
    csrf_require();

    $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);

    if (!$announcement_id) {
        $_SESSION['error_message'] = "Invalid announcement ID.";
        redirect_to('../pages/announcements.php');
    }

    try {
        // First, get the image path to delete the file
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['image_path']) {
            StorageManager::deleteStoredPath((string) $result['image_path']);
        }

        // Then, delete the record from the database
        $delete_stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $delete_stmt->execute([$announcement_id]);
        // Log delete
        log_activity_db(
            $pdo,
            'delete',
            'announcement',
            $announcement_id,
            'Deleted announcement',
            json_encode($result),
            null
        );
        $_SESSION['announcement_success_message'] = "Announcement deleted successfully.";

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/announcements.php');
}

// Fallback redirect
redirect_to('../pages/announcements.php'); 