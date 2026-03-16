<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
    if ($business_id > 0) {
        try {
            // Check if business exists
            $stmt = $pdo->prepare('SELECT * FROM businesses WHERE id = ?');
            $stmt->execute([$business_id]);
            $business = $stmt->fetch();
            if (!$business) {
                $_SESSION['error_message'] = 'Business not found.';
                header('Location: ../pages/business-records.php');
                exit;
            }
            // Prepare business details for logging (before deletion)
            $business_details = "Business Name: {$business['business_name']}, Type: {$business['business_type']}, Address: {$business['address']}, Status: {$business['status']}";
            
            // Delete the business
            $del = $pdo->prepare('DELETE FROM businesses WHERE id = ?');
            $del->execute([$business_id]);
            
            // Log activity with improved format
            log_activity_db(
                $pdo,
                'delete',
                'business',
                $business_id,
                "Deleted business: {$business['business_name']}",
                $business_details,
                null
            );
            $_SESSION['success_message'] = 'Business deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error deleting business: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid business ID.';
    }
    header('Location: ../pages/business-records.php');
    exit;
} else {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../pages/business-records.php');
    exit;
} 