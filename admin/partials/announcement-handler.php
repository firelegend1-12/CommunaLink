<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/functions.php';

// Check for admin role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin'])) {
    $_SESSION['error_message'] = "You are not authorized to perform this action.";
    redirect_to('../pages/announcements.php');
}

$user_id = $_SESSION['user_id'];

// --- Handle Add Announcement ---
if (isset($_POST['add_announcement'])) {
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $status = sanitize_input($_POST['status'] ?? 'active');
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $priority = $is_urgent ? 'urgent' : 'normal';
    $image_path = null;

    if (empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Title and content are required.";
        redirect_to('../pages/announcements.php');
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/announcements/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $_SESSION['error_message'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            redirect_to('../pages/announcements.php');
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['image']['size'] > $max_size) {
            $_SESSION['error_message'] = "File size exceeds the 5MB limit.";
            redirect_to('../pages/announcements.php');
        }
        
        $filename = uniqid() . '-' . basename($_FILES['image']['name']);
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            // Store relative path from the root `admin` folder for easier access later
            $image_path = 'images/announcements/' . $filename;
        } else {
            $_SESSION['error_message'] = "Failed to upload the image.";
            redirect_to('../pages/announcements.php');
        }
    }

    try {
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
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/announcements.php');
}


// --- Handle Update Announcement ---
if (isset($_POST['update_announcement'])) {
    $announcement_id = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $status = sanitize_input($_POST['status'] ?? 'active');
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $priority = $is_urgent ? 'urgent' : 'normal';
    $image_path = null;

    if (!$announcement_id || empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Announcement ID, title and content are required.";
        redirect_to('../pages/announcements.php');
    }

    // Handle image upload if new image is provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/announcements/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $_SESSION['error_message'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            redirect_to('../pages/announcements.php');
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['image']['size'] > $max_size) {
            $_SESSION['error_message'] = "File size exceeds the 5MB limit.";
            redirect_to('../pages/announcements.php');
        }
        
        $filename = uniqid() . '-' . basename($_FILES['image']['name']);
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            // Store relative path from the root `admin` folder for easier access later
            $image_path = 'images/announcements/' . $filename;
        } else {
            $_SESSION['error_message'] = "Failed to upload the image.";
            redirect_to('../pages/announcements.php');
        }
    }

    try {
        // Fetch old values
        $stmt_old = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt_old->execute([$announcement_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
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
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/announcements.php');
}


// --- Handle Delete Announcement ---
if (isset($_POST['delete_announcement'])) {
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
            $file_to_delete = '../' . $result['image_path'];
            if (file_exists($file_to_delete)) {
                unlink($file_to_delete);
            }
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