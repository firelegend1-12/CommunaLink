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

    $resident_id = $resolved_resident_id;

    $requester_name   = sanitize_input($_POST['requester_name'] ?? '');
    $relation         = sanitize_input($_POST['relation'] ?? '');
    $beneficiary_name = sanitize_input($_POST['beneficiary_name'] ?? '');
    $case_type        = sanitize_input($_POST['case_type'] ?? '');
    $purpose_input    = sanitize_input($_POST['purpose'] ?? '');
    $remarks          = sanitize_input($_POST['remarks'] ?? '');

    if ($beneficiary_name === '' || $relation === '' || $case_type === '' || $purpose_input === '') {
        echo json_encode(['success' => false, 'error' => 'Please complete all required fields.']);
        exit;
    }

    $details = [
        'requester_name'   => $requester_name,
        'relation'         => $relation,
        'beneficiary_name' => $beneficiary_name,
        'case_type'        => $case_type,
        'purpose'          => $purpose_input,
        'remarks'          => $remarks,
        'day_issued'       => sanitize_input($_POST['day_issued'] ?? date('jS')),
        'month_issued'     => sanitize_input($_POST['month_issued'] ?? date('F')),
        'year_issued'      => date('Y')
    ];

    $purpose_label = "Certificate of Indigency (Special) - " . $case_type . " assistance for " . $beneficiary_name;

    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status)
            VALUES (?, 'Certificate of Indigency (Special)', ?, ?, ?, 'Pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id,
        $purpose_label,
        json_encode($details),
        $_SESSION['user_id']
    ]);

    log_activity('Document Request', "New Certificate of Indigency (Special) requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Indigency (Special) Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
