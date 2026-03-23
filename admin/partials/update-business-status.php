<?php
/**
 * Update Business Status Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
require_login();

// Set content type to JSON
header('Content-Type: application/json');

if (!is_admin_or_official()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate and sanitize input
    $business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
    $status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';

    // Validate business ID
    if ($business_id <= 0) {
        throw new Exception('Invalid business ID');
    }

    // Validate status
    $allowed_statuses = ['Active', 'Inactive', 'Pending'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Invalid status value');
    }

    // Check if business exists
    $check_sql = "SELECT id, business_name FROM businesses WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$business_id]);
    $business = $check_stmt->fetch();

    if (!$business) {
        throw new Exception('Business not found');
    }

    // Update business status
    $update_sql = "UPDATE businesses SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $result = $update_stmt->execute([$status, $business_id]);

    if (!$result) {
        throw new Exception('Failed to update business status');
    }

    // Get old status for logging (before update)
    $old_status_stmt = $pdo->prepare("SELECT status FROM businesses WHERE id = ?");
    $old_status_stmt->execute([$business_id]);
    $old_status = $old_status_stmt->fetchColumn();
    
    // Only log if status actually changed
    if ($old_status !== $status) {
        // Log the activity with readable format
        log_activity_db(
            $pdo,
            'update_status',
            'business',
            $business_id,
            "Business: {$business['business_name']} - status: {$old_status} → {$status}",
            $old_status,
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
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 