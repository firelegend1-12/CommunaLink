<?php
/**
 * Add Resident Form Handler
 * Processes the data from the add resident form
 */

// Include authentication and database
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in
require_login();

// Restrict resident access to admin-only resident creation flow
if (!is_admin_or_official()) {
    http_response_code(403);
    $_SESSION['error_message'] = 'Unauthorized access.';
    redirect_to('../pages/add-resident.php');
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/add-resident.php');
}

// Function to handle file uploads
function handle_upload($file_input_name, $upload_dir) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Sanitize filename
        $safe_filename = preg_replace('/[^A-Za-z0-9\._-]/', '', $file_name);
        $new_filename = uniqid('', true) . '.' . $file_ext;
        
        $dest_path = $upload_dir . $new_filename;
        
        // Check if it is a valid image
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_exts)) {
            return ['error' => 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.'];
        }
        
        if (move_uploaded_file($file_tmp_path, $dest_path)) {
            return ['filename' => $new_filename];
        } else {
            return ['error' => 'Failed to move uploaded file.'];
        }
    }
    return ['filename' => null];
}

// Define upload directories
$relative_profile_dir = 'images/resident-profiles/';
$relative_signature_dir = 'images/signatures/';

$profile_upload_dir = dirname(__DIR__) . '/' . $relative_profile_dir;
$signature_upload_dir = dirname(__DIR__) . '/' . $relative_signature_dir;

// Create directories if they don't exist
if (!file_exists($profile_upload_dir)) {
    mkdir($profile_upload_dir, 0755, true);
}
if (!file_exists($signature_upload_dir)) {
    mkdir($signature_upload_dir, 0755, true);
}

// Handle file uploads
$profile_image_upload = handle_upload('profile_image', $profile_upload_dir);
$signature_upload = handle_upload('signature', $signature_upload_dir);

if (isset($profile_image_upload['error']) || isset($signature_upload['error'])) {
    // Handle upload errors
    $error_message = $profile_image_upload['error'] ?? $signature_upload['error'];
    $_SESSION['error_message'] = $error_message;
    redirect_to('../pages/add-resident.php');
}

// Construct relative paths for database
$profile_image_path = null;
if (isset($profile_image_upload['filename'])) {
    $profile_image_path = $relative_profile_dir . $profile_image_upload['filename'];
}

$signature_path = null;
if (isset($signature_upload['filename'])) {
    $signature_path = $relative_signature_dir . $signature_upload['filename'];
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