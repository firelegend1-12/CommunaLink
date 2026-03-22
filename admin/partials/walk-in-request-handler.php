<?php
/**
 * Walk-In Request Form Handler
 */

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/monitoring-of-request.php');
}

// Validate input
$resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
$document_type = sanitize_input($_POST['document_type'] ?? '');
$purpose = sanitize_input($_POST['purpose'] ?? '');
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

if (empty($resident_id) || empty($document_type) || empty($purpose)) {
    $_SESSION['error_message'] = "All fields are required.";
    redirect_to('../pages/monitoring-of-request.php');
}

try {
    $details = json_encode(['purpose' => $purpose]);
    $sql = "INSERT INTO document_requests (resident_id, requested_by_user_id, document_type, purpose, details, price, status) VALUES (?, NULL, ?, ?, ?, ?, 'Processing')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$resident_id, $document_type, $purpose, $details, $price]);
    
    $request_id = $pdo->lastInsertId();
    log_activity_db(
        $pdo,
        'add',
        'document_request',
        $request_id,
        "Created walk-in request for resident ID {$resident_id} - {$document_type}",
        null,
        "status: Processing"
    );
    $_SESSION['success_message'] = "New walk-in request has been successfully created.";

} catch (PDOException $e) {
    log_activity_db(
        $pdo,
        'error',
        'document_request',
        null,
        "Failed to create walk-in request. " . $e->getMessage(),
        null,
        null
    );
    $_SESSION['error_message'] = "Failed to create request: " . $e->getMessage();
}

redirect_to('../pages/monitoring-of-request.php'); 