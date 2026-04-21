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

if (!csrf_validate()) {
    $_SESSION['error_message'] = "Invalid security token. Please refresh the page and try again.";
    redirect_to('../pages/account.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$fullname = sanitize_input((string)($_POST['fullname'] ?? ''));
$email_raw = trim((string)($_POST['email'] ?? ''));
$email = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ? sanitize_input($email_raw) : null;
$current_password = (string)($_POST['current_password'] ?? '');
$new_password = (string)($_POST['new_password'] ?? '');
$confirm_password = (string)($_POST['confirm_password'] ?? '');

if ($user_id <= 0) {
    $_SESSION['error_message'] = "Your session is invalid. Please log in again.";
    redirect_to('../pages/account.php');
}

// Basic validation
if (empty($fullname) || empty($email)) {
    $_SESSION['error_message'] = "Full name and email are required.";
    redirect_to('../pages/account.php');
}

try {
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error_message'] = "User account not found.";
        redirect_to('../pages/account.php');
    }

    $existing_email = (string)($user['email'] ?? '');
    $existing_password_hash = (string)($user['password'] ?? '');
    $is_email_changed = strcasecmp($existing_email, (string)$email) !== 0;
    $is_password_change_requested = ($new_password !== '' || $confirm_password !== '');

    if ($is_email_changed || $is_password_change_requested) {
        if ($current_password === '' || $existing_password_hash === '' || !password_verify($current_password, $existing_password_hash)) {
            $_SESSION['error_message'] = "Current password is required to change email or password.";
            redirect_to('../pages/account.php');
        }
    }

    if ($is_email_changed) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $_SESSION['error_message'] = "This email address is already in use by another account.";
            redirect_to('../pages/account.php');
        }
    }

    if ($is_password_change_requested) {
        if ($new_password === '' || $confirm_password === '') {
            $_SESSION['error_message'] = "To change your password, fill in both new password fields.";
            redirect_to('../pages/account.php');
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = "New passwords do not match.";
            redirect_to('../pages/account.php');
        }

        $password_validation = PasswordSecurity::validatePassword($new_password);
        if (!$password_validation['valid']) {
            $_SESSION['error_message'] = "Password does not meet security requirements: " . implode(' ', $password_validation['errors']);
            redirect_to('../pages/account.php');
        }

        if ($existing_password_hash !== '' && password_verify($new_password, $existing_password_hash)) {
            $_SESSION['error_message'] = "New password must be different from your current password.";
            redirect_to('../pages/account.php');
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$fullname, $email, $hashed_password, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
        $stmt->execute([$fullname, $email, $user_id]);
    }

    $_SESSION['success_message'] = "Account updated successfully.";
    $_SESSION['fullname'] = $fullname;
    $_SESSION['email'] = $email;

} catch (PDOException $e) {
    error_log("Account update failed: " . $e->getMessage());
    $_SESSION['error_message'] = "An unexpected database error occurred.";
}

redirect_to('../pages/account.php'); 