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

if (!csrf_validate()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

try {
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $purpose = sanitize_input($_POST['purpose'] ?? '');
    
    if (!$resident_id || empty($purpose)) {
        echo json_encode(['success' => false, 'error' => 'Purpose is required.']);
        exit;
    }

    // Store only the fields that match the SVG certificate
    $details = [
        'applicant_name' => sanitize_input($_POST['applicant_name'] ?? ''),
        'age'            => sanitize_input($_POST['age'] ?? ''),
        'purpose'          => $purpose,
        'day_issued'       => sanitize_input($_POST['day_issued'] ?? date('jS')),
        'month_issued'     => sanitize_input($_POST['month_issued'] ?? date('F')),
        'year_issued'      => date('Y')
    ];

    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status) 
            VALUES (?, 'Barangay Clearance', ?, ?, ?, 'Pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id, 
        $purpose, 
        json_encode($details), 
        $_SESSION['user_id']
    ]);

    log_activity('Document Request', "New Barangay Clearance requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Barangay Clearance Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
