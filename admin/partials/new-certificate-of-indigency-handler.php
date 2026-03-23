<?php
/**
 * New Certificate of Indigency Form Handler
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    redirect_to('../pages/new-certificate-of-indigency.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/new-certificate-of-indigency.php');
}

try {
    // Sanitize and validate form inputs
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $recipient_name = sanitize_input($_POST['recipient_name']);
    $civil_status = sanitize_input($_POST['civil_status']);
    $day_issued = sanitize_input($_POST['day_issued']);
    $month_issued = sanitize_input($_POST['month_issued']);
    $year_issued = sanitize_input($_POST['year_issued']);
    
    // Validate required fields
    if (!$resident_id) {
        $_SESSION['error_message'] = "Please select a recipient for the certificate.";
        redirect_to('../pages/new-certificate-of-indigency.php');
    }

    // Prepare details for storage (JSON format)
    $details = json_encode([
        'recipient_name' => $recipient_name,
        'civil_status' => $civil_status,
        'day_issued' => $day_issued,
        'month_issued' => $month_issued,
        'year_issued' => $year_issued
    ]);

    // Insert into document_requests table
    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status) 
            VALUES (?, 'Certificate of Indigency', ?, ?, ?, 'Pending')";
    
    $purpose = "Requesting for Certificate of Indigency"; // A default purpose
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$resident_id, $purpose, $details, $_SESSION['user_id']]);

    // Success
    log_activity('Document Request', "New Certificate of Indigency application submitted for {$recipient_name}.", $_SESSION['user_id']);
    $_SESSION['success_message'] = "Certificate of Indigency request submitted successfully.";
    redirect_to('../pages/monitoring-of-request.php');

} catch (PDOException $e) {
    // Error
    $user_id_for_log = $_SESSION['user_id'] ?? 0;
    log_activity('Error', "Failed to submit Certificate of Indigency request. Error: " . $e->getMessage(), $user_id_for_log);
    
    $_SESSION['error_message'] = "A database error occurred. Please try again later.";
    redirect_to('../pages/new-certificate-of-indigency.php');
} 