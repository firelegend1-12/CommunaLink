<?php
/**
 * Add User Form Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';

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
$password = $_POST['password'];
$confirm_password = (string)($_POST['confirm_password'] ?? '');
$role = sanitize_input($_POST['role']);
$resident_id = isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0;
$admin_confirmation_password = (string)($_POST['admin_confirmation_password'] ?? '');

// Store inputs in session to re-populate form on error
$_SESSION['form_data'] = [
    'fullname' => $_POST['fullname'] ?? '',
    'role' => $_POST['role'] ?? '',
    'official_position' => $_POST['official_position'] ?? '',
    'resident_id' => $resident_id,
];

// Enhanced input validation using InputValidator
$validation_rules = [
    'fullname' => ['type' => 'name', 'options' => ['required' => true, 'min_length' => 2, 'max_length' => 100]],
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
$password = $validation_result['data']['password'];
$role = $validation_result['data']['role'];

// Enhanced password validation
$passwordValidation = PasswordSecurity::validatePassword($password);
if (!$passwordValidation['valid']) {
    $_SESSION['error_message'] = "Password does not meet security requirements: " . implode(' ', $passwordValidation['errors']);
    redirect_to('../pages/add-user.php');
}

if ($confirm_password === '' || $password !== $confirm_password) {
    $_SESSION['error_message'] = "Password confirmation does not match.";
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

if ($resident_id <= 0) {
    $_SESSION['error_message'] = "Please verify and select a valid resident record before submitting.";
    redirect_to('../pages/add-user.php');
}

$resident_lookup_stmt = $pdo->prepare(
    "SELECT id, email, CONCAT_WS(' ', first_name, IFNULL(middle_initial, ''), last_name) AS full_name
     FROM residents
     WHERE id = ?
     LIMIT 1"
);
$resident_lookup_stmt->execute([$resident_id]);

$resident_row = $resident_lookup_stmt->fetch(PDO::FETCH_ASSOC);
if (!$resident_row) {
    $_SESSION['error_message'] = "Selected resident record was not found.";
    redirect_to('../pages/add-user.php');
}

$resident_id = (int)($resident_row['id'] ?? 0);
$resident_email = (string)($resident_row['email'] ?? '');
$resident_full_name = trim((string)($resident_row['full_name'] ?? ''));

if ($resident_full_name !== '') {
    $fullname = $resident_full_name;
}

if ($resident_email === '' || !filter_var($resident_email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "The matched resident does not have a valid email address on file.";
    redirect_to('../pages/add-user.php');
}

$role_slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', $final_role));
if ($role_slug === '') {
    $role_slug = 'official';
}

$linked_identity_base = 'linked.r' . $resident_id . '.' . $role_slug;
$username = $linked_identity_base;
$email = $linked_identity_base . '@linked.local';

// Ensure the provided password is not the same as the resident's existing account password
$resident_hash = '';
$pwd_stmt = $pdo->prepare('SELECT u.password FROM users u JOIN residents r ON r.user_id = u.id WHERE r.id = ? LIMIT 1');
$pwd_stmt->execute([$resident_id]);
$resident_hash = (string)($pwd_stmt->fetchColumn() ?: '');
if ($resident_hash === '' && $resident_email !== '') {
    $pwd_stmt2 = $pdo->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
    $pwd_stmt2->execute([$resident_email]);
    $resident_hash = (string)($pwd_stmt2->fetchColumn() ?: '');
}

if ($resident_hash !== '' && password_verify($password, $resident_hash)) {
    $_SESSION['error_message'] = "Password used is unavailable";
    redirect_to('../pages/add-user.php');
}

$admin_cap = get_admin_user_cap();
$admin_selection_limit = min(2, $admin_cap);

function has_user_column(PDO $pdo, string $column_name): bool {
    static $column_cache = [];

    if (array_key_exists($column_name, $column_cache)) {
        return $column_cache[$column_name];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = ?"
    );
    $stmt->execute([$column_name]);

    $exists = ((int)$stmt->fetchColumn()) > 0;
    $column_cache[$column_name] = $exists;

    return $exists;
}

function get_users_status_active_value(PDO $pdo): ?string {
    static $cached_active_value = null;
    static $is_cached = false;

    if ($is_cached) {
        return $cached_active_value;
    }

    $is_cached = true;

    $stmt = $pdo->prepare(
        "SELECT DATA_TYPE, COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'status'
         LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $cached_active_value = null;
        return $cached_active_value;
    }

    $data_type = strtolower((string)($row['DATA_TYPE'] ?? ''));
    $column_type = (string)($row['COLUMN_TYPE'] ?? '');

    if ($data_type !== 'enum') {
        $cached_active_value = 'active';
        return $cached_active_value;
    }

    if (strpos($column_type, "'Active'") !== false) {
        $cached_active_value = 'Active';
        return $cached_active_value;
    }

    if (strpos($column_type, "'active'") !== false) {
        $cached_active_value = 'active';
        return $cached_active_value;
    }

    $cached_active_value = null;
    return $cached_active_value;
}

try {
    $pdo->beginTransaction();

    $existing_privileged_stmt = null;
    if (has_user_column($pdo, 'resident_id')) {
        $existing_privileged_stmt = $pdo->prepare(
            "SELECT id, role
             FROM users
             WHERE resident_id = ?
               AND role IN ('admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod')
             LIMIT 1"
        );
        $existing_privileged_stmt->execute([$resident_id]);
    } else {
        $resident_linked_username_like = 'linked.r' . $resident_id . '.%';
        $resident_linked_email_like = 'linked.r' . $resident_id . '.%@linked.local';
        $existing_privileged_stmt = $pdo->prepare(
            "SELECT id, role
             FROM users
             WHERE role IN ('admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod')
               AND (username LIKE ? OR email LIKE ?)
             LIMIT 1"
        );
        $existing_privileged_stmt->execute([$resident_linked_username_like, $resident_linked_email_like]);
    }

    if ($existing_privileged_stmt && $existing_privileged_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "This resident already has a privileged account. Edit the existing account instead.";
        redirect_to('../pages/add-user.php');
    }

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
        if ($admin_count >= $admin_selection_limit) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Admin account limit reached ({$admin_selection_limit}).";
            redirect_to('../pages/add-user.php');
        }
    }

    // Use the entered password for immediate account access.
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $sql_user = "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$username, $fullname, $email, $hashed_password, $final_role]);

    $new_user_id = (int) $pdo->lastInsertId();

    $set_clauses = [];
    $set_params = [];
    if (has_user_column($pdo, 'status')) {
        $active_status_value = get_users_status_active_value($pdo);
        if ($active_status_value !== null) {
            $set_clauses[] = "status = ?";
            $set_params[] = $active_status_value;
        }
    }
    if (has_user_column($pdo, 'email_verified')) {
        $set_clauses[] = "email_verified = ?";
        $set_params[] = 1;
    }
    if (has_user_column($pdo, 'password_change_required')) {
        $set_clauses[] = "password_change_required = ?";
        $set_params[] = 0;
    }
    if (has_user_column($pdo, 'invitation_sent_at')) {
        $set_clauses[] = "invitation_sent_at = NULL";
    }
    if (has_user_column($pdo, 'invitation_expires_at')) {
        $set_clauses[] = "invitation_expires_at = NULL";
    }
    if (has_user_column($pdo, 'reset_token')) {
        $set_clauses[] = "reset_token = NULL";
    }
    if (has_user_column($pdo, 'reset_token_expires')) {
        $set_clauses[] = "reset_token_expires = NULL";
    }

    if (!empty($set_clauses)) {
        $set_params[] = $new_user_id;
        $sync_stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?");
        $sync_stmt->execute($set_params);
    }

    // Link user to resident if the users table has a resident_id column
    if (has_user_column($pdo, 'resident_id') && $resident_id > 0) {
        $link_stmt = $pdo->prepare("UPDATE users SET resident_id = ? WHERE id = ?");
        $link_stmt->execute([$resident_id, $new_user_id]);
    }

    $pdo->commit();
    
    // Prepare user details for logging
    $user_details = "LinkedLoginEmail: {$resident_email}, Username: {$username}, Full Name: {$fullname}, AccountEmail: {$email}, Role: {$final_role}, Status: active, ActivationFlow: linked-account";
    
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

    $_SESSION['success_message'] = "User created successfully. The account is active and can sign in now.";
    redirect_to('../pages/user-management.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
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

    $driver_error_code = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    if ($driver_error_code === 1062) {
        $_SESSION['error_message'] = "A user with this email or username already exists.";
    } else {
        $_SESSION['error_message'] = "Failed to add user due to a database error.";
    }
    redirect_to('../pages/add-user.php');
} 