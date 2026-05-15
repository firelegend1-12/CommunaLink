<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    send_json_error_response('Unauthorized', 401, null, 'Business Clearance Request Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_response('Invalid request', 405, null, 'Business Clearance Request Invalid Method');
}

if (!csrf_validate()) {
    send_json_error_response('Invalid security token. Please refresh and try again.', 403, null, 'Business Clearance Request CSRF');
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
        send_json_error_response('Please complete all required fields.', 400, null, 'Business Clearance Request Validation');
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
    $request_id = (int) $pdo->lastInsertId();
    ensure_request_reference_number($pdo, 'business', $request_id);

    log_activity('Document Request', "New Barangay Business Clearance requested natively by resident.", $_SESSION['user_id']);
    echo json_encode([
        'success' => true,
        'request_id' => $request_id,
        'detail_url' => get_business_transaction_detail_url($request_id),
    ]);

} catch (Throwable $e) {
    send_json_error_response('A database error occurred.', 500, $e, 'Business Clearance Request Error');
}
