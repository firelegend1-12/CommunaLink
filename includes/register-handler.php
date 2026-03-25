<?php
/**
 * Registration Form Handler
 * Now sends OTP for email verification before creating the account
 */

require_once '../config/init.php'; // Use init to include db and functions
require_once 'functions.php';
require_once 'otp_email_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../register.php');
}

// Sanitize and validate form inputs
$fullname = sanitize_input($_POST['fullname']);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ? sanitize_input($_POST['email']) : null;
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// Personal Information
$first_name = sanitize_input($_POST['first_name']);
$middle_initial = sanitize_input($_POST['middle_initial']);
$last_name = sanitize_input($_POST['last_name']);
$gender = sanitize_input($_POST['gender']);
$date_of_birth = sanitize_input($_POST['date_of_birth']);
$place_of_birth = sanitize_input($_POST['place_of_birth']);
$religion = sanitize_input($_POST['religion']);
$citizenship = sanitize_input($_POST['citizenship']);
$civil_status = sanitize_input($_POST['civil_status']);
$voter_status = sanitize_input($_POST['voter_status']);

// Contact Information
$contact_no = sanitize_input($_POST['contact_no']);
$address = sanitize_input($_POST['address']);

// Validate required fields
if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
    $_SESSION['error_message'] = "All required fields must be filled.";
    redirect_to('../register.php');
}

if (empty($first_name) || empty($last_name) || empty($gender) || empty($date_of_birth) || 
    empty($place_of_birth) || empty($citizenship) || empty($civil_status) || empty($voter_status) || empty($address)) {
    $_SESSION['error_message'] = "All required personal information fields must be filled.";
    redirect_to('../register.php');
}

// Validate password
if (strlen($password) < 8) {
    $_SESSION['error_message'] = "Password must be at least 8 characters long.";
    redirect_to('../register.php');
}

if ($password !== $confirm_password) {
    $_SESSION['error_message'] = "Passwords do not match.";
    redirect_to('../register.php');
}

// Validate email format
if (!$email) {
    $_SESSION['error_message'] = "Please enter a valid email address.";
    redirect_to('../register.php');
}

// Calculate age from date of birth
$birth_date = new DateTime($date_of_birth);
$today = new DateTime();
$age = $today->diff($birth_date)->y;

// Validate age (must be at least 18 for registration)
if ($age < 18) {
    $_SESSION['error_message'] = "You must be at least 18 years old to register.";
    redirect_to('../register.php');
}

try {
    // Check if email already exists in users table
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "An account with this email already exists.";
        redirect_to('../register.php');
    }

    // Cleanup expired OTPs
    OTPEmailService::cleanupExpired($pdo);

    // Generate OTP
    $otpCode = OTPEmailService::generateOTP();

    // Store registration data securely (hash password before storing)
    $registrationData = [
        'fullname'       => $fullname,
        'email'          => $email,
        'password'       => password_hash($password, PASSWORD_DEFAULT),
        'first_name'     => $first_name,
        'middle_initial' => $middle_initial,
        'last_name'      => $last_name,
        'gender'         => $gender,
        'date_of_birth'  => $date_of_birth,
        'place_of_birth' => $place_of_birth,
        'age'            => $age,
        'religion'       => $religion,
        'citizenship'    => $citizenship,
        'civil_status'   => $civil_status,
        'voter_status'   => $voter_status,
        'contact_no'     => $contact_no,
        'address'        => $address,
    ];

    // Store OTP in database
    if (!OTPEmailService::storeOTP($pdo, $email, $otpCode, $registrationData)) {
        $_SESSION['error_message'] = "Failed to process verification. Please try again.";
        redirect_to('../register.php');
    }

    // Send OTP email
    $emailSent = OTPEmailService::sendOTP($email, $fullname, $otpCode);

    if (!$emailSent) {
        $_SESSION['error_message'] = "Failed to send verification email. Please check your email address or try again later.";
        redirect_to('../register.php');
    }

    // Store email in session for the verification page
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_fullname'] = $fullname;

    // Redirect to OTP verification page
    redirect_to('../verify-otp.php');

} catch (PDOException $e) {
    error_log("Registration OTP failed: " . $e->getMessage());
    $_SESSION['error_message'] = "Registration failed due to a server error. Please try again.";
    redirect_to('../register.php');
} 