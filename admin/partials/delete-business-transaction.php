<?php
/**
 * Delete Business Transaction Handler
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

// Get parameters from GET request
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate parameters
if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get transaction details before deletion for logging
    $stmt = $pdo->prepare('SELECT resident_id, business_name, transaction_type, status FROM business_transactions WHERE id = ?');
    $stmt->execute([$id]);
    $transaction_data = $stmt->fetch();
    
    if (!$transaction_data) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    
    $resident_id = $transaction_data['resident_id'];
    $business_name = $transaction_data['business_name'];
    $transaction_type = $transaction_data['transaction_type'];
    $status = $transaction_data['status'];
    
    // Delete the business transaction
    $stmt = $pdo->prepare('DELETE FROM business_transactions WHERE id = ?');
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        // Log the deletion
        log_activity_db(
            $pdo,
            'delete',
            'business_transaction',
            $id,
            "Business transaction deleted: {$business_name} - {$transaction_type} (Resident ID: {$resident_id}, Status: {$status})",
            "Business transaction ID: {$id}",
            "Deleted"
        );
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete transaction']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit; 