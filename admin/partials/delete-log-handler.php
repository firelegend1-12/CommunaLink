<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Unauthorized.';
    header('Location: ../pages/logs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_id'])) {
    $log_id = (int)$_POST['log_id'];
    try {
        $stmt = $pdo->prepare('DELETE FROM activity_logs WHERE id = ?');
        $stmt->execute([$log_id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = 'Log entry deleted.';
        } else {
            $_SESSION['error_message'] = 'Log entry not found or already deleted.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting log entry.';
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}
header('Location: ../pages/logs.php');
exit; 