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
        throw new Exception("Resident profile not found for the logged-in user.");
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        throw new Exception("Unauthorized submission profile mismatch.");
    }

    $resident_id = $resolved_resident_id;

    $business_name = sanitize_input($_POST['business_name'] ?? '');
    $business_type = sanitize_input($_POST['business_type'] ?? '');
    $owner_name = sanitize_input($_POST['owner_name'] ?? '');
    $business_address = sanitize_input($_POST['business_address'] ?? '');

    if ($business_name === '' || $business_type === '' || $owner_name === '' || $business_address === '') {
        echo json_encode(['success' => false, 'error' => 'Please complete all required fields.']);
        exit;
    }

    $trans_stmt = $pdo->prepare(
        "INSERT INTO business_transactions (resident_id, permit_id, business_name, business_type, owner_name, address, transaction_type, status, remarks) VALUES (?, NULL, ?, ?, ?, ?, 'New Permit', 'Pending', 'Barangay Business Clearance')"
    );
    $trans_stmt->execute([
        $resident_id,
        $business_name,
        $business_type,
        $owner_name,
        $business_address
    ]);

    log_activity('Document Request', "New Barangay Business Clearance requested natively by resident.", $_SESSION['user_id']);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Business Clearance Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
