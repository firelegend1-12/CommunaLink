<?php
/**
 * New Barangay Clearance Form Handler
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    redirect_to('../pages/new-barangay-clearance.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/new-barangay-clearance.php');
}

try {
    // Sanitize and validate form inputs
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $purpose = sanitize_input($_POST['purpose']);
    $application_type = sanitize_input($_POST['application_type'] ?? 'New');
    $clearance_no = sanitize_input($_POST['clearance_no']);
    $clearance_date = sanitize_input($_POST['clearance_date']);
    $precinct_no = sanitize_input($_POST['precinct_no']);
    $resident_since = sanitize_input($_POST['resident_since']);
    $company_name = sanitize_input($_POST['company_name']);
    $reference_1 = sanitize_input($_POST['reference_1']);
    $reference_2 = sanitize_input($_POST['reference_2']);
    $reference_tel_no = sanitize_input($_POST['reference_tel_no']);
    $ctc_no = sanitize_input($_POST['ctc_no']);
    $ctc_issued_at = sanitize_input($_POST['ctc_issued_at']);
    $ctc_issued_on = sanitize_input($_POST['ctc_issued_on']);
    $clearance_fee = filter_input(INPUT_POST, 'clearance_fee', FILTER_VALIDATE_FLOAT);
    $or_no = sanitize_input($_POST['or_no']);
    $or_date = sanitize_input($_POST['or_date']);
    $remarks = sanitize_input($_POST['remarks']);
    
    // Validate required fields
    if (!$resident_id || !$purpose) {
        $_SESSION['error_message'] = "Applicant and purpose are required.";
        redirect_to('../pages/new-barangay-clearance.php');
    }

    // Prepare details for storage (JSON format)
    $details = json_encode([
        'application_type' => $application_type,
        'clearance_no' => $clearance_no,
        'clearance_date' => $clearance_date,
        'precinct_no' => $precinct_no,
        'resident_since' => $resident_since,
        'company_name' => $company_name,
        'references' => [
            ['name' => $reference_1],
            ['name' => $reference_2]
        ],
        'reference_tel_no' => $reference_tel_no,
        'ctc' => [
            'no' => $ctc_no,
            'issued_at' => $ctc_issued_at,
            'issued_on' => $ctc_issued_on,
        ],
        'fees' => [
            'clearance_fee' => $clearance_fee,
            'or_no' => $or_no,
            'or_date' => $or_date,
        ],
        'remarks' => $remarks
    ]);

    // Insert into document_requests table
    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status) 
            VALUES (?, 'Barangay Clearance', ?, ?, ?, 'Pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$resident_id, $purpose, $details, $_SESSION['user_id']]);

    // Success
    log_activity('Document Request', "New Barangay Clearance application submitted for resident ID {$resident_id}.", $_SESSION['user_id']);
    $_SESSION['success_message'] = "Barangay Clearance application submitted successfully.";
    redirect_to('../pages/monitoring-of-request.php');

} catch (PDOException $e) {
    // Error
    $user_id_for_log = $_SESSION['user_id'] ?? 0;
    log_activity('Error', "Failed to submit Barangay Clearance application. Error: " . $e->getMessage(), $user_id_for_log);
    
    $_SESSION['error_message'] = "A database error occurred. Please try again later.";
    redirect_to('../pages/new-barangay-clearance.php');
} 