<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    send_json_error_response('Unauthorized', 401, null, 'Indigency Request Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_response('Invalid request', 405, null, 'Indigency Request Invalid Method');
}

if (!csrf_validate()) {
    send_json_error_response('Invalid security token. Please refresh and try again.', 403, null, 'Indigency Request CSRF');
}

try {
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        send_json_error_response('Resident profile not found.', 404, null, 'Indigency Request Missing Resident');
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        send_json_error_response('Unauthorized submission profile mismatch.', 403, null, 'Indigency Request Profile Mismatch');
    }

    $resident_id = $resolved_resident_id;

    if (!$resident_id) {
        send_json_error_response('Invalid resident profile.', 400, null, 'Indigency Request Invalid Resident');
    }

    $applicant_name = sanitize_input($_POST['applicant_name'] ?? '');
    $age = sanitize_input($_POST['age'] ?? '');
    if (empty($applicant_name) || empty($age)) {
        send_json_error_response('Applicant name and age are required.', 400, null, 'Indigency Request Validation');
    }

    // Store only the fields that match the SVG certificate
    $details = [
        'applicant_name' => $applicant_name,
        'age'            => $age,
        'civil_status'   => sanitize_input($_POST['civil_status'] ?? ''),
        'day_issued'     => sanitize_input($_POST['day_issued'] ?? date('jS')),
        'month_issued'   => sanitize_input($_POST['month_issued'] ?? date('F')),
        'year_issued'    => date('Y')
    ];

    $purpose = "Requesting for Certificate of Indigency";

    $document_type = 'Certificate of Indigency';
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
    ensure_request_reference_number($pdo, 'document', (int) $pdo->lastInsertId());

    log_activity('Document Request', "New Certificate of Indigency requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    send_json_error_response('A database error occurred.', 500, $e, 'Indigency Request Error');
}
