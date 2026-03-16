<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

require_role('resident');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('barangay-services.php');
}

// Validate and sanitize input
$resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
$document_type = sanitize_input($_POST['document_type'] ?? '');
$purpose = sanitize_input($_POST['purpose'] ?? '');
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

// Additional form fields
$application_type = sanitize_input($_POST['application_type'] ?? '');
$urgency = sanitize_input($_POST['urgency'] ?? '');
$additional_notes = sanitize_input($_POST['additional_notes'] ?? '');

// Business-specific fields
$business_name = sanitize_input($_POST['business_name'] ?? '');
$business_type = sanitize_input($_POST['business_type'] ?? '');
$business_address = sanitize_input($_POST['business_address'] ?? '');
$business_contact = sanitize_input($_POST['business_contact'] ?? '');
$number_of_employees = sanitize_input($_POST['number_of_employees'] ?? '');
$business_description = sanitize_input($_POST['business_description'] ?? '');

// Indigency-specific fields
$monthly_income = sanitize_input($_POST['monthly_income'] ?? '');
$family_size = sanitize_input($_POST['family_size'] ?? '');
$source_of_income = sanitize_input($_POST['source_of_income'] ?? '');

// Residency-specific fields
$length_of_residence = sanitize_input($_POST['length_of_residence'] ?? '');
$residence_type = sanitize_input($_POST['residence_type'] ?? '');

// Validate required fields
if (!$resident_id || empty($document_type) || empty($purpose)) {
    $_SESSION['error_message'] = "Please fill in all required fields.";
    redirect_to('barangay-services.php');
}

// Verify that the resident_id matches the logged-in user
if ($resident_id != ($_SESSION['resident_id'] ?? null)) {
    $_SESSION['error_message'] = "Invalid request.";
    redirect_to('barangay-services.php');
}

try {
    // Prepare the details JSON
    $details = [
        'application_type' => $application_type,
        'urgency' => $urgency,
        'additional_notes' => $additional_notes,
        'submitted_at' => date('Y-m-d H:i:s'),
        'submitted_by' => $_SESSION['fullname'] ?? 'Resident',
        
        // Business-specific details
        'business_name' => $business_name,
        'business_type' => $business_type,
        'business_address' => $business_address,
        'business_contact' => $business_contact,
        'number_of_employees' => $number_of_employees,
        'business_description' => $business_description,
        
        // Indigency-specific details
        'monthly_income' => $monthly_income,
        'family_size' => $family_size,
        'source_of_income' => $source_of_income,
        
        // Residency-specific details
        'length_of_residence' => $length_of_residence,
        'residence_type' => $residence_type
    ];

    // Insert into document_requests table
    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, price, details, status, requested_by_user_id) 
            VALUES (?, ?, ?, ?, ?, 'Pending', ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id,
        $document_type,
        $purpose,
        $price,
        json_encode($details),
        $_SESSION['user_id'] ?? null
    ]);

    $request_id = $pdo->lastInsertId();

    // Log the activity
    log_activity('Document Request Submitted', "New {$document_type} request submitted by resident ID {$resident_id}.", $_SESSION['user_id'] ?? null);

    $_SESSION['success_message'] = "Your {$document_type} request has been submitted successfully! Request ID: {$request_id}. You will be notified once it's ready for pickup.";

} catch (PDOException $e) {
    error_log("Document request submission error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while submitting your request. Please try again.";
}

// Redirect back to the services page
redirect_to('barangay-services.php');
?> 