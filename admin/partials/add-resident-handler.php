<?php
/**
 * Add Resident Form Handler
 * Processes the data from the add resident form
 */

// Include authentication and database
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/storage_manager.php';
require_once '../../includes/password_security.php';
require_once '../../includes/input_validator.php';

// Check if user is logged in
require_login();

// Restrict this flow to admin or official roles
if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    redirect_to('../pages/add-resident.php');
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/add-resident.php');
}

$_SESSION['form_data'] = $_POST;

// Function to handle file uploads
function handle_upload($file_input_name, $relative_dir, $prefix) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_validation = validate_input($_FILES[$file_input_name], 'file', [
            'max_size' => 5 * 1024 * 1024,
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif']
        ]);

        if (!$file_validation['valid']) {
            return ['error' => 'Invalid upload. ' . implode(' ', $file_validation['errors'])];
        }

        $validated_file = $file_validation['sanitized'];
        $storage_result = StorageManager::saveUploadedFile($validated_file, $relative_dir, $prefix);
        if (!$storage_result['success']) {
            return ['error' => (string) ($storage_result['error'] ?? 'Failed to store uploaded file.')];
        }

        return ['path' => (string) ($storage_result['path'] ?? '')];
    }
    return ['path' => null];
}

// Define upload directories
$profile_storage_dir = 'admin/images/resident-profiles';
$signature_storage_dir = 'admin/images/signatures';

// Handle file uploads
$profile_image_upload = handle_upload('profile_image', $profile_storage_dir, 'resident_profile_');
$signature_upload = handle_upload('signature', $signature_storage_dir, 'resident_signature_');

if (isset($profile_image_upload['error']) || isset($signature_upload['error'])) {
    // Handle upload errors
    $error_message = $profile_image_upload['error'] ?? $signature_upload['error'];
    $_SESSION['error_message'] = $error_message;
    redirect_to('../pages/add-resident.php');
}

// Construct relative paths for database
$profile_image_path = null;
if (!empty($profile_image_upload['path'])) {
    $profile_image_path = str_replace('admin/', '', $profile_image_upload['path']);
}

$signature_path = null;
if (!empty($signature_upload['path'])) {
    $signature_path = str_replace('admin/', '', $signature_upload['path']);
}

// Sanitize and validate form inputs
$first_name = trim((string)($_POST['first_name'] ?? ''));
$middle_initial = trim((string)($_POST['middle_initial'] ?? ''));
$last_name = trim((string)($_POST['last_name'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
$place_of_birth = trim((string)($_POST['place_of_birth'] ?? ''));
$age_input = trim((string)($_POST['age'] ?? ''));
$religion = trim((string)($_POST['religion'] ?? ''));
$citizenship = trim((string)($_POST['citizenship'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$contact_no = trim((string)($_POST['contact_no'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$civil_status = trim((string)($_POST['civil_status'] ?? ''));
$voter_status = trim((string)($_POST['voter_status'] ?? ''));

$required_fields = [
    'first_name' => $first_name,
    'middle_initial' => $middle_initial,
    'last_name' => $last_name,
    'gender' => $gender,
    'date_of_birth' => $date_of_birth,
    'place_of_birth' => $place_of_birth,
    'age' => $age_input,
    'religion' => $religion,
    'citizenship' => $citizenship,
    'email' => $email,
    'password' => $password,
    'contact_no' => $contact_no,
    'address' => $address,
    'civil_status' => $civil_status,
    'voter_status' => $voter_status,
];

foreach ($required_fields as $field => $value) {
    if ($value === '') {
        $_SESSION['error_message'] = 'Please complete all resident and account fields before saving.';
        redirect_to('../pages/add-resident.php');
    }
}

if (!preg_match('/^[A-Za-z\s\'\-\.]+$/', $first_name)) {
    $_SESSION['error_message'] = 'First Name must contain only letters and valid punctuation.';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^[A-Za-z\s\'\-\.]+$/', $last_name)) {
    $_SESSION['error_message'] = 'Last Name must contain only letters and valid punctuation.';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^[A-Za-z\.\- ]+$/', $middle_initial)) {
    $_SESSION['error_message'] = 'Middle Initial must contain only letters.';
    redirect_to('../pages/add-resident.php');
}

if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $_SESSION['error_message'] = 'Please select a valid gender.';
    redirect_to('../pages/add-resident.php');
}

if (!in_array($civil_status, ['Single', 'Married', 'Widowed', 'Separated'], true)) {
    $_SESSION['error_message'] = 'Please select a valid civil status.';
    redirect_to('../pages/add-resident.php');
}

if (!in_array($voter_status, ['Yes', 'No'], true)) {
    $_SESSION['error_message'] = 'Please select a valid voter status.';
    redirect_to('../pages/add-resident.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = 'Please enter a valid email address.';
    redirect_to('../pages/add-resident.php');
}

if (strlen($password) < 8) {
    $_SESSION['error_message'] = 'Password must be at least 8 characters long.';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/[0-9]/', $password)) {
    $_SESSION['error_message'] = 'Password must contain at least one number (0-9).';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"",.<>?\/\\|`~]/', $password)) {
    $_SESSION['error_message'] = 'Password must contain at least one special character (!@#$%^&*).';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^(\+?63|0)9\d{9}$/', $contact_no)) {
    $_SESSION['error_message'] = 'Please enter a valid Philippine mobile number (e.g. 09123456789).';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^[A-Za-z\s\'\-\.]+$/', $religion)) {
    $_SESSION['error_message'] = 'Religion must contain only letters and valid punctuation.';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^[A-Za-z\s\'\-\.]+$/', $citizenship)) {
    $_SESSION['error_message'] = 'Citizenship must contain only letters and valid punctuation.';
    redirect_to('../pages/add-resident.php');
}

if (strlen($address) < 5) {
    $_SESSION['error_message'] = 'Please enter a complete address.';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9\s\-\.,\'#/()]+$/', $place_of_birth)) {
    $_SESSION['error_message'] = 'Place of Birth must include both letters and numbers.';
    redirect_to('../pages/add-resident.php');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
    $_SESSION['error_message'] = 'Please enter a valid date of birth.';
    redirect_to('../pages/add-resident.php');
}

$birth_date = DateTime::createFromFormat('Y-m-d', $date_of_birth);
if (!$birth_date || $birth_date->format('Y-m-d') !== $date_of_birth) {
    $_SESSION['error_message'] = 'Please enter a valid date of birth.';
    redirect_to('../pages/add-resident.php');
}

$today = new DateTime('today');
if ($birth_date > $today) {
    $_SESSION['error_message'] = 'Date of birth cannot be in the future.';
    redirect_to('../pages/add-resident.php');
}

$calculated_age = $today->diff($birth_date)->y;
if ((int)$age_input !== $calculated_age) {
    $_SESSION['error_message'] = 'Age must match the selected date of birth.';
    redirect_to('../pages/add-resident.php');
}

$age = $calculated_age;
$email = sanitize_input($email);
$first_name = sanitize_input($first_name);
$middle_initial = sanitize_input($middle_initial);
$last_name = sanitize_input($last_name);
$gender = sanitize_input($gender);
$date_of_birth = sanitize_input($date_of_birth);
$place_of_birth = sanitize_input($place_of_birth);
$religion = sanitize_input($religion);
$citizenship = sanitize_input($citizenship);
$contact_no = sanitize_input($contact_no);
$address = sanitize_input($address);
$civil_status = sanitize_input($civil_status);
$voter_status = sanitize_input($voter_status);

// Generate automatic ID number based on current year when resident is added
function generateResidentId($pdo) {
    $current_year = date('Y'); // This ensures ID is based on the year when resident is added
    
    // Get the last ID number for this year
    $stmt = $pdo->prepare("SELECT id_number FROM residents WHERE id_number LIKE ? ORDER BY id_number DESC LIMIT 1");
    $stmt->execute(["BR-{$current_year}-%"]);
    $last_id = $stmt->fetchColumn();
    
    if ($last_id) {
        // Extract the number part and increment
        $parts = explode('-', $last_id);
        $last_number = intval($parts[2]);
        $new_number = $last_number + 1;
    } else {
        // First resident for this year
        $new_number = 1;
    }
    
    // Format: BR-YYYY-XXXX (4-digit sequence)
    return sprintf("BR-%s-%04d", $current_year, $new_number);
}

$id_number = generateResidentId($pdo);

try {
    $pdo->beginTransaction();

    // Check for duplicate account/resident email before inserting
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to add resident. The email address '{$email}' already exists in the account list.";
        redirect_to('../pages/add-resident.php');
    }

    $stmt = $pdo->prepare("SELECT id FROM residents WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to add resident. The email address '{$email}' already exists.";
        redirect_to('../pages/add-resident.php');
    }

    $full_name = trim($first_name . ' ' . $middle_initial . ' ' . $last_name);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql_user = "INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, 'resident')";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$email, $hashed_password, $full_name, $email]);
    $user_id = (int) $pdo->lastInsertId();

    // Prepare SQL statement
    $sql = "INSERT INTO residents (first_name, middle_initial, last_name, gender, date_of_birth, place_of_birth, age, religion, citizenship, email, contact_no, address, civil_status, id_number, voter_status, user_id, profile_image_path, signature_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Execute the statement with an array of values
    $stmt->execute([
        $first_name, $middle_initial, $last_name, $gender, $date_of_birth, $place_of_birth, $age, $religion, $citizenship, $email, $contact_no, $address, $civil_status, $id_number, $voter_status, $user_id, $profile_image_path, $signature_path
    ]);

    // Success
    $pdo->commit();
    unset($_SESSION['form_data']);
    $current_date = date('Y-m-d H:i:s');
    log_activity_db(
        $pdo,
        'add',
        'resident',
        $user_id,
        "New resident {$full_name} added with ID: {$id_number} on {$current_date}.",
        null,
        json_encode([
            'first_name' => $first_name,
            'middle_initial' => $middle_initial,
            'last_name' => $last_name,
            'gender' => $gender,
            'date_of_birth' => $date_of_birth,
            'place_of_birth' => $place_of_birth,
            'age' => $age,
            'religion' => $religion,
            'citizenship' => $citizenship,
            'email' => $email,
            'contact_no' => $contact_no,
            'address' => $address,
            'civil_status' => $civil_status,
            'id_number' => $id_number,
            'voter_status' => $voter_status,
            'profile_image_path' => $profile_image_path,
            'signature_path' => $signature_path
        ])
    );
    $_SESSION['success_message'] = "Resident account successfully created with ID: {$id_number} (Year: " . date('Y') . ").";
    redirect_to('../pages/residents.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Error
    $user_id_for_log = $_SESSION['user_id'] ?? 0; // Use 0 if user_id is not in session
    log_activity('Error', "Failed to add resident. Error: " . $e->getMessage(), $user_id_for_log);
    
    // Check for duplicate entry
    if ($e->getCode() == 23000) { // 23000 is the SQLSTATE for integrity constraint violation
        if (strpos($e->getMessage(), 'id_number') !== false) {
            $_SESSION['error_message'] = "Failed to add resident. The ID number '{$id_number}' already exists.";
        } elseif (strpos($e->getMessage(), 'email') !== false) {
            $_SESSION['error_message'] = "Failed to add resident. The email address '{$email}' already exists.";
        } else {
            $_SESSION['error_message'] = "Failed to add resident due to a duplicate entry. Please check the details and try again.";
        }
    } else {
        $_SESSION['error_message'] = "A database error occurred. Please try again later.";
    }
    
    redirect_to('../pages/add-resident.php');
} 