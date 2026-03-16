<?php
/**
 * Account Update Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';


require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/account.php');
}

$user_id = $_SESSION['user_id'];
$fullname = sanitize_input($_POST['fullname']);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ? sanitize_input($_POST['email']) : null;
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// Basic validation
if (empty($fullname) || empty($email)) {
    $_SESSION['error_message'] = "Full name and email are required.";
    redirect_to('../pages/account.php');
}

try {
    // Check for email uniqueness if it has been changed
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "This email address is already in use by another account.";
        redirect_to('../pages/account.php');
    }

    // Handle password change
    if (!empty($new_password)) {
        if (empty($current_password) || empty($confirm_password)) {
            $_SESSION['error_message'] = "To change your password, you must fill all password fields.";
            redirect_to('../pages/account.php');
        }
        
        if ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = "New passwords do not match.";
            redirect_to('../pages/account.php');
        }

        // Fetch current password from DB
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password'])) {
            $_SESSION['error_message'] = "Incorrect current password.";
            redirect_to('../pages/account.php');
        }

        // Hash new password and update everything
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$fullname, $email, $hashed_password, $user_id]);

    } else {
        // Update only profile info
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
        $stmt->execute([$fullname, $email, $user_id]);
    }

    $_SESSION['success_message'] = "Account updated successfully.";
    // Update session fullname if it was changed
    $_SESSION['fullname'] = $fullname; 

} catch (PDOException $e) {
    // error_log($e->getMessage());
    $_SESSION['error_message'] = "An unexpected database error occurred.";
}

redirect_to('../pages/account.php'); 