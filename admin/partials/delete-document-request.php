<?php
/**
 * Delete Document Request Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_documents', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

// Get parameters from POST request
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate parameters
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get request details before deletion for logging
    $stmt = $pdo->prepare('SELECT resident_id, document_type, status FROM document_requests WHERE id = ?');
    $stmt->execute([$id]);
    $request_data = $stmt->fetch();
    
    if (!$request_data) {
        http_response_code(404);
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
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete request']);
    }
    
} catch (PDOException $e) {
    error_log('delete-document-request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while deleting request']);
}
exit; 