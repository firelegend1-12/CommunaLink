<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!csrf_validate()) {
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

    $resident_id       = $resolved_resident_id;
    $owner_name        = sanitize_input($_POST['owner_name'] ?? '');
    $business_name     = sanitize_input($_POST['business_name'] ?? '');
    $business_type     = sanitize_input($_POST['business_type'] ?? '');
    $business_address  = sanitize_input($_POST['business_address'] ?? '');
    $application_type  = sanitize_input($_POST['application_type'] ?? 'New');
    $remarks           = sanitize_input($_POST['remarks'] ?? '');

    if ($business_name === '' || $business_type === '' || $business_address === '') {
        echo json_encode(['success' => false, 'error' => 'Please complete all required fields.']);
        exit;
    }

    $transaction_type = ($application_type === 'Renewal') ? 'Renewal' : 'New Permit';

    // Insert into business_transactions so it appears in admin's monitoring page.
    $sql = "INSERT INTO business_transactions
                (resident_id, permit_id, business_name, business_type, owner_name, address, transaction_type, status, remarks)
            VALUES (?, NULL, ?, ?, ?, ?, ?, 'Pending', ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id,
        $business_name,
        $business_type,
        $owner_name,
        $business_address,
        $transaction_type,
        $remarks,
    ]);

    log_activity('Document Request', "New Barangay Business Permit ({$transaction_type}) requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Business Permit Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
