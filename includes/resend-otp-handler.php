<?php
/**
 * Resend OTP Handler
 * Generates a new OTP and sends it to the user's email
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

if (!isset($_SESSION['otp_email'])) {
    $_SESSION['error_message'] = "Session expired. Please register again.";
    header("Location: ../register.php");
    exit;
}

$email = $_SESSION['otp_email'];
$fullname = $_SESSION['otp_fullname'] ?? '';

try {
    // Check if there's existing registration data we can reuse
    $stmt = $pdo->prepare(
        "SELECT registration_data FROM email_verification_otps 
         WHERE email = ? ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        $_SESSION['error_message'] = "Verification session expired. Please register again.";
        unset($_SESSION['otp_email'], $_SESSION['otp_fullname']);
        header("Location: ../register.php");
        exit;
    }

    $registrationData = json_decode($existing['registration_data'], true);

    // Generate new OTP
    $otpCode = OTPEmailService::generateOTP();

    // Store new OTP (replaces old one)
    if (!OTPEmailService::storeOTP($pdo, $email, $otpCode, $registrationData)) {
        $_SESSION['otp_error'] = "Failed to generate new code. Please try again.";
        header("Location: ../verify-otp.php");
        exit;
    }

    // Send new OTP email
    $sent = OTPEmailService::sendOTP($email, $fullname, $otpCode);

    if ($sent) {
        unset($_SESSION['otp_dev_code']);
        $_SESSION['otp_success'] = "A new verification code has been sent to your email.";
    } else {
        $app_env = strtolower((string) env('APP_ENV', 'production'));
        if ($app_env !== 'production') {
            $_SESSION['otp_dev_code'] = $otpCode;
            $_SESSION['otp_success'] = 'Email delivery is unavailable. A temporary development OTP code has been generated.';
        } else {
            $_SESSION['otp_error'] = "Failed to send email. Please try again later.";
        }
    }

} catch (PDOException $e) {
    error_log("Resend OTP failed: " . $e->getMessage());
    $_SESSION['otp_error'] = "Server error. Please try again.";
}

header("Location: ../verify-otp.php");
exit;
