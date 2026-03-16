<?php
/**
 * Update Document Request Status Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check authorization
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters from GET request (as sent by the JavaScript)
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Validate parameters
if (empty($id) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get old status for logging
    $stmt = $pdo->prepare('SELECT status, resident_id, document_type FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    $old_data = $stmt->fetch();
    
    if (!$old_data) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }
    
    $old_status = $old_data['status'];
    $resident_id = $old_data['resident_id'];
    $document_type = $old_data['document_type'];
    
    // Update the status
    $stmt = $pdo->prepare('UPDATE document_requests SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    // Log the status change
    $old_str = "status: $old_status";
    $new_str = "status: $status";
    log_activity_db(
        $pdo,
        'update_status',
        'document_request',
        $id,
        "Document request status changed from '{$old_status}' to '{$status}' for {$document_type} (Resident ID: {$resident_id})",
        $old_str,
        $new_str
    );


    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit; 