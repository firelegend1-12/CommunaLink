<?php
/**
 * Account Security Handler for Resident Email and Password
 * Handles email and password changes with OTP verification.
 * All responses are JSON.
 */

session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/csrf.php';
require_once '../../includes/otp_email_service.php';

// Must be logged in as resident
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!csrf_validate()) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Helper: fetch current user record
function load_account_security_user(PDO $pdo, int $user_id): ?array
{
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.password, u.fullname, r.first_name, r.last_name FROM users u LEFT JOIN residents r ON u.id = r.user_id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

switch ($action) {

    // ─── STEP 1: Send OTP ───
    case 'send_otp':
        $type = trim($_POST['type'] ?? ''); // 'email' or 'password'
        if (!in_array($type, ['email', 'password'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid verification request type.']);
            exit;
        }

        $user = load_account_security_user($pdo, $user_id);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $newPasswordPlain = trim((string)($_POST['new_password'] ?? ''));

        // For password change, validate current password first
        if ($type === 'password') {
            $current_password = $_POST['current_password'] ?? '';
            if (!password_verify($current_password, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                exit;
            }

            $has_upper = preg_match('/[A-Z]/', $newPasswordPlain) === 1;
            $has_lower = preg_match('/[a-z]/', $newPasswordPlain) === 1;
            $has_number = preg_match('/[0-9]/', $newPasswordPlain) === 1;
            $has_special = preg_match('/[^A-Za-z0-9]/', $newPasswordPlain) === 1;
            if ($newPasswordPlain === '' || strlen($newPasswordPlain) < 8 || !$has_upper || !$has_lower || !$has_number || !$has_special) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters and include uppercase, lowercase, number, and special character.']);
                exit;
            }
        }

        // For email change, validate new email
        $new_email = '';
        if ($type === 'email') {
            $new_email = trim($_POST['new_email'] ?? '');
            if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
                exit;
            }
            // Check if email already taken
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$new_email, $user_id]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'This email is already in use by another account.']);
                exit;
            }
        }

        // Generate OTP and decide target email
        $otpCode = OTPEmailService::generateOTP();
        $toName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['fullname'] ?? 'Resident');

        // Email change: verify access to the NEW inbox. Password change: use current email.
        $otpTarget = ($type === 'email') ? $new_email : $user['email'];

        $registrationData = ['change_type' => $type, 'user_id' => $user_id];
        if ($type === 'email') $registrationData['new_email'] = $new_email;

        $stored = OTPEmailService::storeOTP($pdo, $otpTarget, $otpCode, $registrationData);
        if (!$stored) {
            echo json_encode(['success' => false, 'message' => 'Failed to generate verification code. Please try again.']);
            exit;
        }

        $sent = OTPEmailService::sendOTP($otpTarget, $toName, $otpCode);
        if (!$sent) {
            echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
            exit;
        }

        // Persist context for verification step
        $_SESSION['account_change_type']      = $type;
        $_SESSION['account_change_email']     = $user['email'];   // current registered email
        $_SESSION['account_change_otp_email'] = $otpTarget;       // where OTP record is stored
        if ($type === 'email') $_SESSION['account_change_new_email'] = $new_email;
        if ($type === 'password') $_SESSION['account_change_new_password'] = password_hash($newPasswordPlain, PASSWORD_DEFAULT);

        $masked = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $otpTarget);
        echo json_encode(['success' => true, 'message' => "Verification code sent to {$masked}."]);
        exit;

    // ─── STEP 2: Verify OTP & apply email change ───
    case 'verify_change_email':
        $otp_code = trim($_POST['otp_code'] ?? '');
        $email = $_SESSION['account_change_email'] ?? '';
        $otp_email = $_SESSION['account_change_otp_email'] ?? '';
        $new_email = $_SESSION['account_change_new_email'] ?? '';

        if (empty($email) || empty($otp_email) || empty($new_email)) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
            exit;
        }

        if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit code.']);
            exit;
        }

        $result = OTPEmailService::verifyOTP($pdo, $otp_email, $otp_code);
        if ($result === 'max_attempts') {
            $pdo->prepare("DELETE FROM email_verification_otps WHERE email = ?")->execute([$otp_email]);
            unset($_SESSION['account_change_email'], $_SESSION['account_change_otp_email'], $_SESSION['account_change_type'], $_SESSION['account_change_new_email']);
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please start over.']);
            exit;
        }
        if ($result === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
            exit;
        }

        // Apply email change
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET email = ?, username = ? WHERE id = ?")->execute([$new_email, $new_email, $user_id]);
            $pdo->prepare("UPDATE residents SET email = ? WHERE user_id = ?")->execute([$new_email, $user_id]);
            $pdo->commit();
        }
        catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Email change failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update email. Please try again.']);
            exit;
        }

        // Destroy session and redirect to login
        unset($_SESSION['account_change_email'], $_SESSION['account_change_otp_email'], $_SESSION['account_change_type'], $_SESSION['account_change_new_email']);
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Email updated successfully. Please sign in with your new email.', 'redirect' => app_url('/index.php')]);
        exit;

    // ─── STEP 2: Verify OTP & apply password change ───
    case 'verify_change_password':
        $otp_code = trim($_POST['otp_code'] ?? '');
        $email = $_SESSION['account_change_email'] ?? '';
        $otp_email = $_SESSION['account_change_otp_email'] ?? $email;
        $new_password_hash = $_SESSION['account_change_new_password'] ?? '';

        if (empty($otp_email) || empty($new_password_hash)) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
            exit;
        }

        if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit code.']);
            exit;
        }

        $result = OTPEmailService::verifyOTP($pdo, $otp_email, $otp_code);
        if ($result === 'max_attempts') {
            $pdo->prepare("DELETE FROM email_verification_otps WHERE email = ?")->execute([$otp_email]);
            unset($_SESSION['account_change_email'], $_SESSION['account_change_otp_email'], $_SESSION['account_change_type'], $_SESSION['account_change_new_password']);
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please start over.']);
            exit;
        }
        if ($result === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
            exit;
        }

        // Apply password change
        try {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_password_hash, $user_id]);
        }
        catch (PDOException $e) {
            error_log("Password change failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
            exit;
        }

        // Destroy session and redirect to login
        unset($_SESSION['account_change_email'], $_SESSION['account_change_otp_email'], $_SESSION['account_change_type'], $_SESSION['account_change_new_password']);
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Password updated successfully. Please sign in with your new password.', 'redirect' => app_url('/index.php')]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
}
