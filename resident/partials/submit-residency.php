<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    send_json_error_response('Unauthorized', 401, null, 'Residency Request Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_response('Invalid request', 405, null, 'Residency Request Invalid Method');
}

if (!csrf_validate()) {
    send_json_error_response('Invalid security token. Please refresh and try again.', 403, null, 'Residency Request CSRF');
}

try {
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        send_json_error_response('Resident profile not found.', 404, null, 'Residency Request Missing Resident');
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        send_json_error_response('Unauthorized submission profile mismatch.', 403, null, 'Residency Request Profile Mismatch');
    }

    $resident_id = $resolved_resident_id;
    $applicant_name = sanitize_input($_POST['applicant_name'] ?? '');
    $age = sanitize_input($_POST['age'] ?? '');
    $duration = sanitize_input($_POST['duration'] ?? '');
    $day_issued = sanitize_input($_POST['day_issued'] ?? date('j'));
    $month_issued = sanitize_input($_POST['month_issued'] ?? date('F'));
    $year_issued = sanitize_input($_POST['year_issued'] ?? date('Y'));

    if (!$resident_id || empty($applicant_name) || empty($duration)) {
        send_json_error_response('Please fill in all required fields.', 400, null, 'Residency Request Validation');
    }

    // Store the new simplified format while keeping legacy-compatible keys for any older templates.
    $details = [
        'applicant_name' => $applicant_name,
        'age' => $age,
        'duration' => $duration,
        'day_issued' => $day_issued,
        'month_issued' => $month_issued,
        'year_issued' => $year_issued,
        'property_owner' => '',
        'sitio' => '',
        'district' => '',
        'status' => [],
        'issued_on' => ''
    ];

    $purpose = "Requesting for Certificate of Residency";

    $document_type = 'Certificate of Residency';
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

    log_activity('Document Request', "New Certificate of Residency requested natively by resident.", $_SESSION['user_id']);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    send_json_error_response('A database error occurred.', 500, $e, 'Residency Request Error');
}
