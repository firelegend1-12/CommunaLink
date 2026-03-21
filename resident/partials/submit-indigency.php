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
    $recipient_name = sanitize_input($_POST['recipient_name'] ?? '');
    $civil_status = sanitize_input($_POST['civil_status'] ?? '');
    
    if (!$resident_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid resident profile.']);
        exit;
    }

    // Mirror the exact JSON structure the admin handler uses
    $details = [
        'recipient_name' => $recipient_name,
        'civil_status' => $civil_status,
        'day_issued' => '', // Admin fills this
        'month_issued' => '', // Admin fills this
        'year_issued' => '' // Admin fills this
    ];

    $purpose = "Requesting for Certificate of Indigency";

    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status) 
            VALUES (?, 'Certificate of Indigency', ?, ?, ?, 'Pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id, 
        $purpose, 
        json_encode($details), 
        $_SESSION['user_id']
    ]);

    log_activity('Document Request', "New Certificate of Indigency requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Indigency Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
