<?php
/**
 * Add User Form Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';

session_start();

// Check if the user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "You are not authorized to perform this action.";
    redirect_to('../pages/user-management.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/add-user.php');
}

// Validate CSRF token
if (!csrf_validate()) {
    $_SESSION['error_message'] = "Invalid security token. Please refresh the page and try again.";
    redirect_to('../pages/add-user.php');
}

$fullname = sanitize_input($_POST['fullname']);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'];
$role = sanitize_input($_POST['role']);
$username = $email; // Use email as username

// Store inputs in session to re-populate form on error
$_SESSION['form_data'] = $_POST;

// Enhanced input validation using InputValidator
$validation_rules = [
    'fullname' => ['type' => 'name', 'options' => ['required' => true, 'min_length' => 2, 'max_length' => 100]],
    'email' => ['type' => 'email', 'options' => ['required' => true]],
    'password' => ['type' => 'password', 'options' => ['required' => true]],
    'role' => ['type' => 'string', 'options' => ['required' => true]]
];

$validation_result = validate_form($_POST, $validation_rules);

if (!$validation_result['valid']) {
    $error_messages = [];
    foreach ($validation_result['errors'] as $field => $errors) {
        $error_messages[] = ucfirst($field) . ": " . implode(', ', $errors);
    }
    $_SESSION['error_message'] = "Validation errors: " . implode('; ', $error_messages);
    redirect_to('../pages/add-user.php');
}

// Use sanitized data
$fullname = $validation_result['data']['fullname'];
$email = $validation_result['data']['email'];
$password = $validation_result['data']['password'];
$role = $validation_result['data']['role'];

// Enhanced password validation
$passwordValidation = PasswordSecurity::validatePassword($password);
if (!$passwordValidation['valid']) {
    $_SESSION['error_message'] = "Password does not meet security requirements: " . implode(' ', $passwordValidation['errors']);
    redirect_to('../pages/add-user.php');
}

if (!in_array($role, ['admin', 'resident', 'official'])) {
    $_SESSION['error_message'] = "Invalid role specified.";
    redirect_to('../pages/add-user.php');
}

// Process role selection
if ($role === 'official') {
    $official_position = sanitize_input($_POST['official_position'] ?? '');
    if (empty($official_position)) {
        $_SESSION['error_message'] = "Please select an official position.";
        redirect_to('../pages/add-user.php');
    }
    // Convert official role to specific official position
    $final_role = $official_position;
} else {
    $final_role = $role;
}

// Validate final role
if (!in_array($final_role, ['admin', 'resident', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'])) {
    $_SESSION['error_message'] = "Invalid role specified.";
    redirect_to('../pages/add-user.php');
}

$admin_cap = get_admin_user_cap();

try {
    $pdo->beginTransaction();

    if ($final_role === 'admin') {
        $admin_count = count_admin_users($pdo, true);
        if ($admin_count >= $admin_cap) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Admin account limit reached ({$admin_cap}).";
            redirect_to('../pages/add-user.php');
        }
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "A user with this email already exists.";
        redirect_to('../pages/add-user.php');
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $sql_user = "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$username, $fullname, $email, $hashed_password, $final_role]);
    
    // If the role is resident, also create a record in the residents table
    if ($final_role === 'resident') {
        $user_id = $pdo->lastInsertId();
        $name_parts = explode(' ', $fullname);
        $last_name = array_pop($name_parts);
        $first_name = implode(' ', $name_parts);

        $sql_resident = "INSERT INTO residents (user_id, first_name, last_name, email) VALUES (?, ?, ?, ?)";
        $stmt_resident = $pdo->prepare($sql_resident);
        $stmt_resident->execute([$user_id, $first_name, $last_name, $email]);
    }

    $pdo->commit();
    
    // Prepare user details for logging
    $user_details = "Username: {$username}, Full Name: {$fullname}, Email: {$email}, Role: {$final_role}";
    
    // Log the user creation
    log_activity_db(
        $pdo,
        'add',
        'user',
        $pdo->lastInsertId(),
        "New user created: {$fullname} ({$username})",
        null,
        $user_details
    );
    
    $_SESSION['success_message'] = "User added successfully.";
    redirect_to('../pages/user-management.php');

} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Log the error
    log_activity_db(
        $pdo,
        'error',
        'user',
        null,
        "Failed to create user. Error: " . $e->getMessage(),
        null,
        null
    );
    
    error_log("Add user failed: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to add user due to a database error.";
    redirect_to('../pages/add-user.php');
} 