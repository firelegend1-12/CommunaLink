<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    send_json_error_response('Unauthorized', 401, null, 'Business Permit Request Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_response('Invalid request', 405, null, 'Business Permit Request Invalid Method');
}

if (!csrf_validate()) {
    send_json_error_response('Invalid security token. Please refresh and try again.', 403, null, 'Business Permit Request CSRF');
}

try {
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        send_json_error_response('Resident profile not found.', 404, null, 'Business Permit Request Missing Resident');
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        send_json_error_response('Unauthorized submission profile mismatch.', 403, null, 'Business Permit Request Profile Mismatch');
    }

    $resident_id       = $resolved_resident_id;
    $owner_name        = sanitize_input($_POST['owner_name'] ?? '');
    $business_name     = sanitize_input($_POST['business_name'] ?? '');
    $business_type     = sanitize_input($_POST['business_type'] ?? '');
    $business_address  = sanitize_input($_POST['business_address'] ?? '');
    $application_type  = sanitize_input($_POST['application_type'] ?? 'New');
    $remarks           = sanitize_input($_POST['remarks'] ?? '');

    if ($business_name === '' || $business_type === '' || $business_address === '') {
        send_json_error_response('Please complete all required fields.', 400, null, 'Business Permit Request Validation');
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
    $request_id = (int) $pdo->lastInsertId();
    ensure_request_reference_number($pdo, 'business', $request_id);

    log_activity('Document Request', "New Barangay Business Permit ({$transaction_type}) requested natively by resident.", $_SESSION['user_id']);

    echo json_encode([
        'success' => true,
        'request_id' => $request_id,
        'detail_url' => get_business_transaction_detail_url($request_id),
    ]);
} catch (Throwable $e) {
    send_json_error_response('A database error occurred.', 500, $e, 'Business Permit Request Error');
}
