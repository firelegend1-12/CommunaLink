<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $applicant_name = sanitize_input($_POST['applicant_name'] ?? '');
    $property_owner = sanitize_input($_POST['property_owner'] ?? '');
    $sitio = sanitize_input($_POST['sitio'] ?? '');
    $district = sanitize_input($_POST['district'] ?? '');
    $status = isset($_POST['status']) ? (array)$_POST['status'] : [];
    
    // Provide a default issued_on so the admin handler schema is exactly matched
    $issued_on = date('Y-m-d');
    
    if (!$resident_id || empty($property_owner) || empty($sitio) || empty($district)) {
        echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
        exit;
    }

    // Mirror the exact JSON structure the admin handler uses
    $details = [
        'applicant_name' => $applicant_name,
        'property_owner' => $property_owner,
        'sitio' => $sitio,
        'district' => $district,
        'status' => $status,
        'issued_on' => '' // Actually admin can rewrite this on print
    ];

    // Admin handler uses a specific string for purpose
    $purpose = "iKonek Electrification Program";

    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status) 
            VALUES (?, 'Certificate of Residency', ?, ?, ?, 'Pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id, 
        $purpose, 
        json_encode($details), 
        $_SESSION['user_id']
    ]);

    log_activity('Document Request', "New Certificate of Residency requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Residency Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
