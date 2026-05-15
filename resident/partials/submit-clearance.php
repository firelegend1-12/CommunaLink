<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    send_json_error_response('Unauthorized', 401, null, 'Barangay Clearance Request Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_response('Invalid request', 405, null, 'Barangay Clearance Request Invalid Method');
}

if (!csrf_validate()) {
    send_json_error_response('Invalid security token. Please refresh and try again.', 403, null, 'Barangay Clearance Request CSRF');
}

try {
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        send_json_error_response('Resident profile not found.', 404, null, 'Barangay Clearance Request Missing Resident');
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        send_json_error_response('Unauthorized submission profile mismatch.', 403, null, 'Barangay Clearance Request Profile Mismatch');
    }

    $resident_id = $resolved_resident_id;
    $purpose = sanitize_input($_POST['purpose'] ?? '');
    $applicant_name = sanitize_input($_POST['applicant_name'] ?? '');
    $age = sanitize_input($_POST['age'] ?? '');
    $document_gender = sanitize_input($_POST['document_gender'] ?? '');
    $document_civil_status = sanitize_input($_POST['document_civil_status'] ?? '');
    $document_locality = sanitize_input($_POST['document_locality'] ?? '');

    if (empty($purpose)) {
        send_json_error_response('Purpose is required.', 400, null, 'Barangay Clearance Request Validation');
    }
    if (empty($applicant_name) || empty($age)) {
        send_json_error_response('Applicant name and age are required.', 400, null, 'Barangay Clearance Request Validation');
    }

    // Store only the fields that match the SVG certificate
    $details = [
        'applicant_name' => $applicant_name,
        'age'            => $age,
        'purpose'        => $purpose,
        'gender'         => $document_gender,
        'civil_status'   => $document_civil_status,
        'locality'       => $document_locality,
        'day_issued'     => sanitize_input($_POST['day_issued'] ?? date('jS')),
        'month_issued'   => sanitize_input($_POST['month_issued'] ?? date('F')),
        'year_issued'    => date('Y')
    ];

    $document_type = 'Barangay Clearance';
    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, price, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id,
        $document_type,
        $purpose,
        json_encode($details),
        $_SESSION['user_id'],
        get_document_request_fee($document_type)
    ]);

    log_activity('Document Request', "New Barangay Clearance requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    send_json_error_response('A database error occurred.', 500, $e, 'Barangay Clearance Request Error');
}
