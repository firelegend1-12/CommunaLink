<?php
/**
 * Handler for the New Certificate of Residency form.
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/functions.php';
require_once '../../includes/permission_checker.php';

require_login();
require_permission_or_redirect('manage_documents', '../pages/new-certificate-of-residency.php');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/new-certificate-of-residency.php');
    exit();
}

if (!csrf_validate()) {
    $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
    header('Location: ../pages/new-certificate-of-residency.php');
    exit();
}

try {
    // Sanitize and retrieve POST data
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_SANITIZE_NUMBER_INT);
    $applicant_name = filter_input(INPUT_POST, 'applicant_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $property_owner = filter_input(INPUT_POST, 'property_owner', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $sitio = filter_input(INPUT_POST, 'sitio', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $district = filter_input(INPUT_POST, 'district', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $status = isset($_POST['status']) ? (array)$_POST['status'] : [];
    $issued_on = filter_input(INPUT_POST, 'issued_on', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate required fields
    if (empty($resident_id) || empty($applicant_name) || empty($property_owner) || empty($sitio) || empty($district) || empty($issued_on)) {
        $_SESSION['error_message'] = "Please fill in all required fields.";
        header('Location: ../pages/new-certificate-of-residency.php');
        exit();
    }

    // Create a details array
    $details = [
        'applicant_name' => $applicant_name,
        'property_owner' => $property_owner,
        'sitio' => $sitio,
        'district' => $district,
        'status' => $status,
        'issued_on' => $issued_on
    ];

    // Encode the details into JSON
    $details_json = json_encode($details);
    if ($details_json === false) {
        throw new Exception("Failed to encode details to JSON. Error: " . json_last_error_msg());
    }

    // Prepare SQL statement to insert into the document_requests table
    $stmt = $pdo->prepare(
        "INSERT INTO document_requests (resident_id, document_type, details, status, purpose, requested_by_user_id) 
         VALUES (:resident_id, :document_type, :details, :status, :purpose, :requested_by_user_id)"
    );

    $purpose = "iKonek Electrification Program";

    // Execute the statement
    $stmt->execute([
        ':resident_id' => $resident_id,
        ':document_type' => 'Certificate of Residency',
        ':details' => $details_json,
        ':status' => 'Pending',
        ':purpose' => $purpose,
        ':requested_by_user_id' => $_SESSION['user_id']
    ]);

    // Log the activity
    log_activity('Document Request', "Submitted a new Certificate of Residency request for {$applicant_name}", $_SESSION['user_id']);

    // Set success message and redirect
    $_SESSION['success_message'] = "Certificate of Residency request submitted successfully.";
    header('Location: ../pages/monitoring-of-request.php');
    exit();

} catch (PDOException $e) {
    // Log PDO errors
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "A database error occurred. Please try again.";
    header('Location: ../pages/new-certificate-of-residency.php');
    exit();
} catch (Exception $e) {
    // Log other errors
    error_log("Error: " . $e->getMessage());
    $_SESSION['error_message'] = "An unexpected error occurred. Please try again.";
    header('Location: ../pages/new-certificate-of-residency.php');
    exit();
} 