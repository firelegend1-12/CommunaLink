<?php
/**
 * OTP Verification Handler
 * Validates the OTP and creates the user account
 */

require_once '../config/init.php';
require_once '../includes/functions.php';
require_once '../includes/otp_email_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../register.php");
    exit;
}

// Must have email in session
if (!isset($_SESSION['otp_email'])) {
    $_SESSION['error_message'] = "Session expired. Please register again.";
    header("Location: ../register.php");
    exit;
}

$email = $_SESSION['otp_email'];
$otpCode = trim($_POST['otp_code'] ?? '');

if (strlen($otpCode) !== 6 || !ctype_digit($otpCode)) {
    $_SESSION['otp_error'] = "Please enter a valid 6-digit code.";
    header("Location: ../verify-otp.php");
    exit;
}

// Verify OTP
$result = OTPEmailService::verifyOTP($pdo, $email, $otpCode);

if ($result === 'max_attempts') {
    // Delete the OTP record and force re-registration
    $pdo->prepare("DELETE FROM email_verification_otps WHERE email = ?")->execute([$email]);
    unset($_SESSION['otp_email'], $_SESSION['otp_fullname'], $_SESSION['otp_dev_code']);
    $_SESSION['error_message'] = "Too many failed attempts. Please register again.";
    header("Location: ../register.php");
    exit;
}

if ($result === false) {
    $_SESSION['otp_error'] = "Invalid or expired verification code. Please try again.";
    header("Location: ../verify-otp.php");
    exit;
}

// OTP verified! $result contains the registration data
$data = $result;

try {
    $pdo->beginTransaction();

    // Double-check email doesn't exist (race condition guard)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        unset($_SESSION['otp_email'], $_SESSION['otp_fullname']);
        $_SESSION['error_message'] = "An account with this email already exists.";
        header("Location: ../register.php");
        exit;
    }

    // Insert into users table (password already hashed in registration data)
    $sql_user = "INSERT INTO users (username, fullname, email, password, role, email_verified) VALUES (?, ?, ?, ?, 'resident', 1)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$data['email'], $data['fullname'], $data['email'], $data['password']]);
    $user_id = $pdo->lastInsertId();

    // Insert into residents table
    $sql_resident = "INSERT INTO residents (
        user_id, first_name, middle_initial, last_name, gender, date_of_birth, 
        place_of_birth, age, religion, citizenship, email, contact_no, 
        address, civil_status, voter_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_resident = $pdo->prepare($sql_resident);
    $stmt_resident->execute([
        $user_id, $data['first_name'], $data['middle_initial'], $data['last_name'],
        $data['gender'], $data['date_of_birth'], $data['place_of_birth'], $data['age'],
        $data['religion'], $data['citizenship'], $data['email'], $data['contact_no'],
        $data['address'], $data['civil_status'], $data['voter_status']
    ]);

    $pdo->commit();

    // Log registration
    log_activity_db($pdo, $user_id, 'user_registration', 'New user registration completed (email verified)', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    // Clear OTP session data
    unset($_SESSION['otp_email'], $_SESSION['otp_fullname']);

    $_SESSION['success_message'] = "Registration successful! Your email has been verified. You can now sign in.";
    header("Location: ../index.php");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Registration after OTP failed: " . $e->getMessage());
    $_SESSION['otp_error'] = "Registration failed due to a server error. Please try again.";
    header("Location: ../verify-otp.php");
    exit;
}
