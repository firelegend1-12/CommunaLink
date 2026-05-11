<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    send_json_error_response('Unauthorized', 401, null, 'Indigency Special Request Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_response('Invalid request', 405, null, 'Indigency Special Request Invalid Method');
}

if (!csrf_validate()) {
    send_json_error_response('Invalid security token. Please refresh and try again.', 403, null, 'Indigency Special Request CSRF');
}

try {
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        send_json_error_response('Resident profile not found.', 404, null, 'Indigency Special Request Missing Resident');
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        send_json_error_response('Unauthorized submission profile mismatch.', 403, null, 'Indigency Special Request Profile Mismatch');
    }

    $resident_id = $resolved_resident_id;

    $requester_name   = sanitize_input($_POST['requester_name'] ?? '');
    $relation         = sanitize_input($_POST['relation'] ?? '');
    $beneficiary_name = sanitize_input($_POST['beneficiary_name'] ?? '');
    $case_type        = sanitize_input($_POST['case_type'] ?? '');
    $purpose_input    = sanitize_input($_POST['purpose'] ?? '');
    $remarks          = sanitize_input($_POST['remarks'] ?? '');

    if ($beneficiary_name === '' || $relation === '' || $case_type === '' || $purpose_input === '') {
        send_json_error_response('Please complete all required fields.', 400, null, 'Indigency Special Request Validation');
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

    $document_type = 'Certificate of Indigency (Special)';
    $sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, price, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $resident_id,
        $document_type,
        $purpose_label,
        json_encode($details),
        $_SESSION['user_id'],
        get_document_request_fee($document_type)
    ]);

    log_activity('Document Request', "New Certificate of Indigency (Special) requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    send_json_error_response('A database error occurred.', 500, $e, 'Indigency Special Request Error');
}
