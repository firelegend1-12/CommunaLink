<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../account.php');
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    redirect_to('../../index.php');
}

$user_id = $_SESSION['user_id'];

// Handle Profile Picture Update
if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_image'];
    $upload_dir = '../../admin/images/resident-profiles/';
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_file = $upload_dir . $new_filename;

    // Validate file type and size
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array(strtolower($file_extension), $allowed_types) && $file['size'] < 5000000) { // 5MB limit
        if (move_uploaded_file($file['tmp_name'], $upload_file)) {
            // Get old image path to delete it
            $stmt_old = $pdo->prepare("SELECT profile_image_path FROM residents WHERE user_id = ?");
            $stmt_old->execute([$user_id]);
            $old_image = $stmt_old->fetchColumn();
            if ($old_image && file_exists($upload_dir . $old_image)) {
                 // The path in DB is relative to admin folder, not this script's location
                $old_image_path_in_db = str_replace('images/resident-profiles/', '', $old_image);
                if ($old_image && file_exists($upload_dir . $old_image_path_in_db)) {
                    unlink($upload_dir . $old_image_path_in_db);
                }
            }

            // Update database with new image path
            $db_path = 'images/resident-profiles/' . $new_filename;
            $stmt = $pdo->prepare("UPDATE residents SET profile_image_path = ? WHERE user_id = ?");
            $stmt->execute([$db_path, $user_id]);
            redirect_to('../account.php?success=pic_updated');
        } else {
            redirect_to('../account.php?error=upload_failed');
        }
    } else {
        redirect_to('../account.php?error=invalid_file');
    }
    exit();
}

// Handle Profile Details Update
if (isset($_POST['update_details'])) {
    // Sanitize and retrieve form data
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $middle_initial = trim(filter_input(INPUT_POST, 'middle_initial', FILTER_SANITIZE_STRING));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $date_of_birth = trim(filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING));
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $gender = trim(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING));
    $place_of_birth = trim(filter_input(INPUT_POST, 'place_of_birth', FILTER_SANITIZE_STRING));
    $civil_status = trim(filter_input(INPUT_POST, 'civil_status', FILTER_SANITIZE_STRING));
    $religion = trim(filter_input(INPUT_POST, 'religion', FILTER_SANITIZE_STRING));
    $citizenship = trim(filter_input(INPUT_POST, 'citizenship', FILTER_SANITIZE_STRING));
    $contact_no = trim(filter_input(INPUT_POST, 'contact_no', FILTER_SANITIZE_STRING));
    $address = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING));
    $voter_status = trim(filter_input(INPUT_POST, 'voter_status', FILTER_SANITIZE_STRING));

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($contact_no) || empty($address) || empty($gender) || empty($date_of_birth)) {
        redirect_to('../account.php?error=emptyfields');
        exit();
    }

    // Update the residents table
    try {
        $sql = "UPDATE residents SET 
                    first_name = ?, middle_initial = ?, last_name = ?, date_of_birth = ?, age = ?, gender = ?, 
                    place_of_birth = ?, civil_status = ?, religion = ?, citizenship = ?, 
                    contact_no = ?, address = ?, voter_status = ? 
                WHERE user_id = ?";
                
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $first_name, $middle_initial, $last_name, $date_of_birth, $age, $gender,
            $place_of_birth, $civil_status, $religion, $citizenship,
            $contact_no, $address, $voter_status,
            $user_id
        ]);

        // Update the session fullname to reflect changes immediately
        $_SESSION['fullname'] = $first_name . ' ' . $last_name;

        // Redirect back with a success message
        redirect_to('../account.php?success=updated');

    } catch (PDOException $e) {
        // Log the error and redirect with a generic error message
        error_log("Account update error: " . $e->getMessage());
        redirect_to('../account.php?error=dberror');
    }
} else {
    // If neither form was submitted, just redirect
    redirect_to('../account.php');
}