<?php
/**
 * Registration Form Handler
 */

require_once '../config/init.php'; // Use init to include db and functions
require_once 'functions.php';

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
    $pdo->beginTransaction();

    // Check if email already exists in users table
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "An account with this email already exists.";
        redirect_to('../register.php');
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into users table with 'resident' role
    $sql_user = "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, 'resident')";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$email, $fullname, $email, $hashed_password]);
    $user_id = $pdo->lastInsertId();

    // Insert into residents table with all collected information
    $sql_resident = "INSERT INTO residents (
        user_id, first_name, middle_initial, last_name, gender, date_of_birth, 
        place_of_birth, age, religion, citizenship, email, contact_no, 
        address, civil_status, voter_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_resident = $pdo->prepare($sql_resident);
    $stmt_resident->execute([
        $user_id, $first_name, $middle_initial, $last_name, $gender, $date_of_birth,
        $place_of_birth, $age, $religion, $citizenship, $email, $contact_no,
        $address, $civil_status, $voter_status
    ]);

    $pdo->commit();
    
    // Log the successful registration
    log_activity_db($pdo, $user_id, 'user_registration', 'New user registration completed', $_SERVER['REMOTE_ADDR']);
    
    $_SESSION['success_message'] = "Registration successful! You can now sign in with your email and password.";
    redirect_to('../index.php');

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Registration failed: " . $e->getMessage());
    $_SESSION['error_message'] = "Registration failed due to a database error. Please try again.";
    redirect_to('../register.php');
} 