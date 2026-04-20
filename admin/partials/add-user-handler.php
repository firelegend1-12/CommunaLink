<?php
/**
 * Add User Form Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/otp_email_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$admin_confirmation_password = (string)($_POST['admin_confirmation_password'] ?? '');
$username = $email; // Use email as username

// Store inputs in session to re-populate form on error
$_SESSION['form_data'] = [
    'fullname' => $_POST['fullname'] ?? '',
    'email' => $_POST['email'] ?? '',
    'role' => $_POST['role'] ?? '',
    'official_position' => $_POST['official_position'] ?? ''
];

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

if (!in_array($role, ['admin', 'official'], true)) {
    $_SESSION['error_message'] = "Invalid role specified.";
    redirect_to('../pages/add-user.php');
}

// Process role selection
if ($role === 'official') {
    $official_position = strtolower(sanitize_input($_POST['official_position'] ?? ''));
    if (empty($official_position)) {
        $_SESSION['error_message'] = "Please select an official position.";
        redirect_to('../pages/add-user.php');
    }

    $official_role_aliases = [
        'official' => 'barangay-officials',
        'barangay-captain' => 'barangay-officials',
        'barangay-secretary' => 'barangay-officials',
        'barangay-treasurer' => 'barangay-officials',
        'kagawad' => 'barangay-kagawad',
    ];

    // Convert official role to specific official position
    $final_role = $official_role_aliases[$official_position] ?? $official_position;
} else {
    $final_role = $role;
}

// Validate final role
if (!in_array($final_role, ['admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'], true)) {
    $_SESSION['error_message'] = "Invalid role specified.";
    redirect_to('../pages/add-user.php');
}

$admin_cap = get_admin_user_cap();

function has_user_column(PDO $pdo, string $column_name): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `users` LIKE ?");
    $stmt->execute([$column_name]);
    return $stmt->fetch() !== false;
}

try {
    $pdo->beginTransaction();

    if ($final_role === 'admin') {
        if ($admin_confirmation_password === '') {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Please confirm your current admin password to create an admin account.";
            redirect_to('../pages/add-user.php');
        }

        $actor_user_id = (int)($_SESSION['user_id'] ?? 0);
        if ($actor_user_id <= 0) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Your session is invalid. Please log in again.";
            redirect_to('../pages/add-user.php');
        }

        $actor_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
        $actor_stmt->execute([$actor_user_id]);
        $actor_hash = (string)($actor_stmt->fetchColumn() ?: '');
        if ($actor_hash === '' || !password_verify($admin_confirmation_password, $actor_hash)) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Admin re-authentication failed. Invalid current password.";
            redirect_to('../pages/add-user.php');
        }

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

    $raw_invite_token = bin2hex(random_bytes(32));
    $invite_token_hash = hash('sha256', $raw_invite_token);
    $invite_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Invite-based lifecycle hardening: never provision privileged users with an immediately usable password.
    // A random bootstrap password is stored, and account activation happens through the expiring invite link.
    $bootstrap_password = bin2hex(random_bytes(24));
    $hashed_password = password_hash($bootstrap_password, PASSWORD_DEFAULT);

    // Insert into users table
    $sql_user = "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$username, $fullname, $email, $hashed_password, $final_role]);

    $new_user_id = (int) $pdo->lastInsertId();

    $set_clauses = [];
    $set_params = [];
    if (has_user_column($pdo, 'status')) {
        $set_clauses[] = "status = ?";
        $set_params[] = 'pending';
    }
    if (has_user_column($pdo, 'email_verified')) {
        $set_clauses[] = "email_verified = ?";
        $set_params[] = 0;
    }
    if (has_user_column($pdo, 'password_change_required')) {
        $set_clauses[] = "password_change_required = ?";
        $set_params[] = 1;
    }
    if (has_user_column($pdo, 'invitation_sent_at')) {
        $set_clauses[] = "invitation_sent_at = NOW()";
    }
    if (has_user_column($pdo, 'invitation_expires_at')) {
        $set_clauses[] = "invitation_expires_at = ?";
        $set_params[] = $invite_expires;
    }
    if (has_user_column($pdo, 'reset_token')) {
        $set_clauses[] = "reset_token = ?";
        $set_params[] = $invite_token_hash;
    }
    if (has_user_column($pdo, 'reset_token_expires')) {
        $set_clauses[] = "reset_token_expires = ?";
        $set_params[] = $invite_expires;
    }

    if (!empty($set_clauses)) {
        $set_params[] = $new_user_id;
        $sync_stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?");
        $sync_stmt->execute($set_params);
    }

    // Build invitation link using same secure reset flow.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $basePath = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/\\');
    $invite_link = $scheme . $_SERVER['HTTP_HOST'] . $basePath . "/reset-password.php?token=" . $raw_invite_token;

    $invite_sent = false;
    try {
        $invite_sent = OTPEmailService::sendPasswordResetEmail($email, $fullname, $invite_link);
    } catch (Exception $e) {
        error_log('Invite email exception for user ID: ' . $new_user_id . ' - ' . $e->getMessage());
    }
    
    $pdo->commit();
    
    // Prepare user details for logging
    $user_details = "Username: {$username}, Full Name: {$fullname}, Email: {$email}, Role: {$final_role}, InviteSent: " . ($invite_sent ? 'Yes' : 'No') . ", InviteExpires: {$invite_expires}";
    
    // Log the user creation
    log_activity_db(
        $pdo,
        'add',
        'user',
        $new_user_id,
        "New user created: {$fullname} ({$username})",
        null,
        $user_details
    );

    $_SESSION['success_message'] = $invite_sent
        ? "User created. Invitation link sent; account remains pending until password setup."
        : "User created in pending state, but invitation email failed. Use Forgot Password to resend activation.";
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