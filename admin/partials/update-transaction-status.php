<?php
/**
 * Update Business Transaction Status Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if (!is_admin_or_official()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Not authorized or session expired. Please log in again.'
    ]);
    exit;
}

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Validate input
$status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? $_POST['status'] : '');
$request_id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : '');

if (empty($status) || empty($request_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$pdo->beginTransaction();

try {
    // Fetch transaction details (before update)
    $stmt = $pdo->prepare("SELECT * FROM business_transactions WHERE id = ?");
    $stmt->execute([$request_id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        throw new Exception("Transaction not found.");
    }

    // Get old status for logging
    $old_status = $transaction['status'];

    // Only proceed if status is actually changing
    if ($old_status === $status) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Status unchanged']);
            exit;
        } else {
            $_SESSION['success_message'] = "Status unchanged.";
            redirect_to('../pages/business-transactions.php');
        }
    }

    // Update transaction status
    $update_stmt = $pdo->prepare("UPDATE business_transactions SET status = ?, processed_date = NOW() WHERE id = ?");
    $update_stmt->execute([$status, $request_id]);

    // If approved, create/update the business record with enhanced features
    if ($status === 'APPROVED') {
        // Generate business permit number and expiration date
        $permit_number = 'BP-' . date('Y') . '-' . str_pad($request_id, 4, '0', STR_PAD_LEFT);
        $expiration_date = date('Y-m-d', strtotime('+1 year'));
        $approval_date = date('Y-m-d H:i:s');
        
        // Check if business already exists
        $check_stmt = $pdo->prepare("SELECT id FROM businesses WHERE resident_id = ? AND business_name = ?");
        $check_stmt->execute([$transaction['resident_id'], $transaction['business_name']]);
        $existing_business = $check_stmt->fetch();

        if ($existing_business) {
            // Update existing business with permit details
            $business_stmt = $pdo->prepare("UPDATE businesses SET 
                status = 'Active', 
                business_type = ?, 
                address = ?,
                permit_number = ?,
                permit_expiration_date = ?,
                approval_date = ?,
                approved_by = ?
                WHERE id = ?");
            $business_stmt->execute([
                $transaction['business_type'], 
                $transaction['address'], 
                $permit_number,
                $expiration_date,
                $approval_date,
                $_SESSION['user_id'],
                $existing_business['id']
            ]);
            $business_id = $existing_business['id'];
        } else {
            // Insert new business with permit details
            $business_stmt = $pdo->prepare("INSERT INTO businesses (
                resident_id, business_name, business_type, address, status, 
                permit_number, permit_expiration_date, approval_date, approved_by
            ) VALUES (?, ?, ?, ?, 'Active', ?, ?, ?, ?)");
            $business_stmt->execute([
                $transaction['resident_id'], 
                $transaction['business_name'], 
                $transaction['business_type'], 
                $transaction['address'],
                $permit_number,
                $expiration_date,
                $approval_date,
                $_SESSION['user_id']
            ]);
            $business_id = $pdo->lastInsertId();
            $business_code = 'BIZ-' . str_pad($business_id, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE businesses SET business_code = ? WHERE id = ?")->execute([$business_code, $business_id]);
        }
        
        // Update transaction with permit details
        $update_permit_stmt = $pdo->prepare("UPDATE business_transactions SET 
            permit_number = ?, 
            permit_expiration_date = ?, 
            approval_date = ?, 
            approved_by = ? 
            WHERE id = ?");
        $update_permit_stmt->execute([
            $permit_number, 
            $expiration_date, 
            $approval_date, 
            $_SESSION['user_id'], 
            $request_id
        ]);
        

        
        // Auto-generate welcome announcement
        require_once '../../includes/business_announcement_functions.php';
        createNewBusinessAnnouncement(
            $business_id, 
            $permit_number, 
            $transaction['business_name'], 
            $expiration_date
        );
        

    }
    
    // Commit transaction
    $pdo->commit();

    // Log the status change with improved format
    log_activity_db(
        $pdo,
        'update_status',
        'business_transaction',
        $request_id,
        "Business Transaction: {$transaction['business_name']} - status: {$old_status} → {$status}",
        $old_status,
        $status
    );
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } else {
        $_SESSION['success_message'] = "Transaction status has been updated.";
        if ($status === 'APPROVED') {
            redirect_to('../pages/business-records.php');
        } else {
            redirect_to('../pages/business-transactions.php');
        }
    }

} catch (Exception $e) {
    $pdo->rollBack();
    log_activity_db(
        $pdo,
        'error',
        'business_transaction',
        $request_id ?? null,
        "Failed to update transaction status. Error: " . $e->getMessage(),
        null,
        null
    );
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} 