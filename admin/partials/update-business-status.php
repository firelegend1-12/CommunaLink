<?php
/**
 * Update Business Status Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

// Check if user is logged in
require_login();

// Set content type to JSON
header('Content-Type: application/json');

require_permission_or_json('manage_businesses', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);
    exit;
}

// Validate and sanitize input
$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';

// Validate business ID
if ($business_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid business ID'
    ]);
    exit;
}

// Validate status
$allowed_statuses = ['Active', 'Inactive', 'Pending'];
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit;
}

try {
    // Check if business exists
    $check_sql = "SELECT id, business_name FROM businesses WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$business_id]);
    $business = $check_stmt->fetch();

    if (!$business) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Business not found'
        ]);
        exit;
    }

    // Capture status before update for correct activity logging.
    $old_status = $pdo->prepare("SELECT status FROM businesses WHERE id = ?");
    $old_status->execute([$business_id]);
    $old_status_value = $old_status->fetchColumn();

    // Update business status
    $update_sql = "UPDATE businesses SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $result = $update_stmt->execute([$status, $business_id]);

    if (!$result) {
        throw new Exception('Update query failed');
    }

    // Only log if status actually changed
    if ($old_status_value !== $status) {
        // Log the activity with readable format
        log_activity_db(
            $pdo,
            'update_status',
            'business',
            $business_id,
            "Business: {$business['business_name']} - status: {$old_status_value} → {$status}",
            $old_status_value,
            $status
        );
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Business status updated successfully',
        'business_id' => $business_id,
        'new_status' => $status
    ]);

} catch (Exception $e) {
    error_log('update-business-status failed: ' . $e->getMessage());
    http_response_code(500);
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update business status.'
    ]);
}
?> 