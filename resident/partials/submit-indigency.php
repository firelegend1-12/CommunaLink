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
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Resident profile not found.']);
        exit;
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized submission profile mismatch.']);
        exit;
    }

    $resident_id = $resolved_resident_id;

    if (!$resident_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid resident profile.']);
        exit;
    }

    $applicant_name = sanitize_input($_POST['applicant_name'] ?? '');
    $age = sanitize_input($_POST['age'] ?? '');
    if (empty($applicant_name) || empty($age)) {
        echo json_encode(['success' => false, 'error' => 'Applicant name and age are required.']);
        exit;
    }

    // Store only the fields that match the SVG certificate
    $details = [
        'applicant_name' => $applicant_name,
        'age'            => $age,
        'day_issued'     => sanitize_input($_POST['day_issued'] ?? date('jS')),
        'month_issued'   => sanitize_input($_POST['month_issued'] ?? date('F')),
        'year_issued'    => date('Y')
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
