<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

require_login();
require_permission_or_redirect('manage_events', '../pages/events.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/events.php');
}

if (!csrf_validate()) {
    $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
    redirect_to('../pages/events.php');
}

$user_id = $_SESSION['user_id'];

// --- Handle Add Event ---
if (isset($_POST['add_event'])) {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $location = sanitize_input($_POST['location']);
    $event_date = sanitize_input($_POST['event_date']);
    $event_time = sanitize_input($_POST['event_time']);
    $type = sanitize_input($_POST['type']);

    if (empty($title) || empty($type)) {
        $_SESSION['error_message'] = "Title and event type are required.";
        redirect_to('../pages/events.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO events (title, description, location, event_date, event_time, type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $location, $event_date, $event_time, $type, $user_id]);
        $_SESSION['event_success_message'] = "Event posted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/events.php');
}


// --- Handle Delete Event ---
if (isset($_POST['delete_event'])) {
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if (!$event_id) {
        $_SESSION['error_message'] = "Invalid event ID.";
        redirect_to('../pages/events.php');
    }

    try {
        $delete_stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $delete_stmt->execute([$event_id]);
        $_SESSION['event_success_message'] = "Event deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    redirect_to('../pages/events.php');
}

// --- Handle Update Event ---
if (isset($_POST['update_event'])) {
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $location = sanitize_input($_POST['location']);
    $event_date = sanitize_input($_POST['event_date']);
    $event_time = sanitize_input($_POST['event_time']);
    $type = sanitize_input($_POST['type']);

    if (!$event_id || empty($title) || empty($type)) {
        $_SESSION['error_message'] = "Event ID, title, and event type are required.";
        redirect_to('../pages/events.php');
    }

    try {
        $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, location = ?, event_date = ?, event_time = ?, type = ? WHERE id = ?");
        $stmt->execute([$title, $description, $location, $event_date, $event_time, $type, $event_id]);
        $_SESSION['event_success_message'] = "Event updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    redirect_to('../pages/events.php');
}

// Fallback redirect
redirect_to('../pages/events.php'); 