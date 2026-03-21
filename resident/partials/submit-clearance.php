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
    $purpose = sanitize_input($_POST['purpose'] ?? '');
    
    if (!$resident_id || empty($purpose)) {
        echo json_encode(['success' => false, 'error' => 'Purpose is required.']);
        exit;
    }

    // Mirror the exact JSON structure the admin handler uses
    $details = [
        'application_type' => sanitize_input($_POST['application_type'] ?? 'New'),
        'clearance_no' => '', // Admin fills this
        'clearance_date' => '', // Admin fills this
        'precinct_no' => sanitize_input($_POST['precinct_no'] ?? ''),
        'resident_since' => sanitize_input($_POST['resident_since'] ?? ''),
        'company_name' => sanitize_input($_POST['company_name'] ?? ''),
        'references' => [
            ['name' => sanitize_input($_POST['reference_1'] ?? '')],
            ['name' => sanitize_input($_POST['reference_2'] ?? '')]
        ],
        'reference_tel_no' => sanitize_input($_POST['reference_tel_no'] ?? ''),
        'ctc' => [
            'no' => sanitize_input($_POST['ctc_no'] ?? ''),
            'issued_at' => sanitize_input($_POST['ctc_issued_at'] ?? ''),
            'issued_on' => sanitize_input($_POST['ctc_issued_on'] ?? ''),
        ],
        'fees' => [
            'clearance_fee' => null,
            'or_no' => '',
            'or_date' => '',
        ],
        'remarks' => '' // Admin remarks
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
