<?php
/**
 * Add Resident Form Handler
 * Processes the data from the add resident form
 */

// Include authentication and database
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/storage_manager.php';

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
$first_name = sanitize_input($_POST['first_name']);
$middle_initial = sanitize_input($_POST['middle_initial']);
$last_name = sanitize_input($_POST['last_name']);
$gender = sanitize_input($_POST['gender']);
$date_of_birth = sanitize_input($_POST['date_of_birth']);
$place_of_birth = sanitize_input($_POST['place_of_birth']);
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$religion = sanitize_input($_POST['religion']);
$citizenship = sanitize_input($_POST['citizenship']);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ? sanitize_input($_POST['email']) : null;
$contact_no = sanitize_input($_POST['contact_no']);
$address = sanitize_input($_POST['address']);
$civil_status = sanitize_input($_POST['civil_status']);
$voter_status = sanitize_input($_POST['voter_status']);

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
    // Prepare SQL statement
    $sql = "INSERT INTO residents (first_name, middle_initial, last_name, gender, date_of_birth, place_of_birth, age, religion, citizenship, email, contact_no, address, civil_status, id_number, voter_status, profile_image_path, signature_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    // Execute the statement with an array of values
    $stmt->execute([
        $first_name, $middle_initial, $last_name, $gender, $date_of_birth, $place_of_birth, $age, $religion, $citizenship, $email, $contact_no, $address, $civil_status, $id_number, $voter_status, $profile_image_path, $signature_path
    ]);

    // Success
    $current_date = date('Y-m-d H:i:s');
    log_activity_db(
        $pdo,
        'add',
        'resident',
        null,
        "New resident {$first_name} {$last_name} added with ID: {$id_number} on {$current_date}.",
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
    $_SESSION['success_message'] = "Resident successfully added with ID: {$id_number} (Year: " . date('Y') . ").";
    redirect_to('../pages/residents.php');

} catch (PDOException $e) {
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