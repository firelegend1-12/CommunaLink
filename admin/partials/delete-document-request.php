<?php
/**
 * Delete Document Request Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check authorization
if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get parameters from GET request
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate parameters
if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get request details before deletion for logging
    $stmt = $pdo->prepare('SELECT resident_id, document_type, status FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    $request_data = $stmt->fetch();
    
    if (!$request_data) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }
    
    $resident_id = $request_data['resident_id'];
    $document_type = $request_data['document_type'];
    $status = $request_data['status'];
    
    // Delete the document request
    $stmt = $pdo->prepare('DELETE FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        // Log the deletion
        log_activity_db(
            $pdo,
            'delete',
            'document_request',
            $id,
            "Document request deleted: {$document_type} (Resident ID: {$resident_id}, Status: {$status})",
            "Document request ID: {$id}",
            "Deleted"
        );
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete request']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit; 