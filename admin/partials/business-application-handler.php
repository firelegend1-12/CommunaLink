<?php
/**
 * Business Application Form Handler
 * Processes the data from the new business application form
 */

// Include authentication and database
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in
require_login();

if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    redirect_to('../pages/business-application-form.php');
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/business-application-form.php');
}

try {
    // Sanitize and validate form inputs
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $business_name = sanitize_input($_POST['business_name']);
    $business_type = sanitize_input($_POST['business_type']);
    $address = sanitize_input($_POST['address']);
    $transaction_type = sanitize_input($_POST['transaction_type']);

    // Validate data
    if (empty($resident_id) || empty($business_name) || empty($business_type) || empty($address) || empty($transaction_type)) {
        $_SESSION['error_message'] = "All fields are required.";
        redirect_to('../pages/business-application-form.php');
    }

    // Fetch the resident's full name
    $stmt = $pdo->prepare("SELECT first_name, last_name, middle_initial FROM residents WHERE id = ?");
    $stmt->execute([$resident_id]);
    $resident = $stmt->fetch();

    if (!$resident) {
        $_SESSION['error_message'] = "Invalid resident selected.";
        redirect_to('../pages/business-application-form.php');
    }

    $owner_name = $resident['last_name'] . ', ' . $resident['first_name'] . ' ' . $resident['middle_initial'];

    // Prepare SQL statement to insert into business_transactions
    $sql = "INSERT INTO business_transactions (resident_id, owner_name, business_name, business_type, address, transaction_type) 
            VALUES (?, ?, ?, ?, ?, ?)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id, $owner_name, $business_name, $business_type, $address, $transaction_type
    ]);
    
    // Prepare business details for logging
    $business_details = "Business Name: {$business_name}, Type: {$business_type}, Address: {$address}, Owner: {$owner_name}, Transaction Type: {$transaction_type}";
    
    // Success
    log_activity_db(
        $pdo,
        'add',
        'business_transaction',
        $pdo->lastInsertId(),
        "New business application submitted: {$business_name}",
        null,
        $business_details
    );
    $_SESSION['success_message'] = "Business application submitted successfully.";
    redirect_to('../pages/monitoring-of-request.php?type=business');

} catch (PDOException $e) {
    // Error
    log_activity_db(
        $pdo,
        'error',
        'business_transaction',
        null,
        "Failed to submit business application. Error: " . $e->getMessage(),
        null,
        null
    );
    $_SESSION['error_message'] = "A database error occurred. Please try again later.";
    redirect_to('../pages/business-application-form.php');
} 