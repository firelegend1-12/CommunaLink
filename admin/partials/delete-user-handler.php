<?php
/**
 * Delete User Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'You are not authorized to perform this action.';
    redirect_to('../pages/user-management.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../pages/user-management.php');
}

if (!csrf_validate()) {
    $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
    redirect_to('../pages/user-management.php');
}

$target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$target_user_id) {
    $_SESSION['error_message'] = 'Invalid user selected.';
    redirect_to('../pages/user-management.php');
}

$current_user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($target_user_id === $current_user_id) {
    $_SESSION['error_message'] = 'You cannot delete your own account while logged in.';
    redirect_to('../pages/user-management.php');
}

try {
    $pdo->beginTransaction();

    $select_stmt = $pdo->prepare('SELECT id, username, fullname, email, role FROM users WHERE id = ? FOR UPDATE');
    $select_stmt->execute([$target_user_id]);
    $target_user = $select_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_user) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'User not found.';
        redirect_to('../pages/user-management.php');
    }

    if ($target_user['role'] === 'admin') {
        $admin_count = count_admin_users($pdo, true);
        if ($admin_count <= 1) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'At least one admin account must remain in the system.';
            redirect_to('../pages/user-management.php');
        }
    }

    $delete_stmt = $pdo->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
    $delete_stmt->execute([$target_user_id]);

    if ($delete_stmt->rowCount() < 1) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Delete operation failed.';
        redirect_to('../pages/user-management.php');
    }

    $pdo->commit();

    $old_snapshot = sprintf(
        'username=%s; fullname=%s; email=%s; role=%s',
        (string) $target_user['username'],
        (string) $target_user['fullname'],
        (string) $target_user['email'],
        (string) $target_user['role']
    );

    log_activity_db(
        $pdo,
        'delete',
        'user',
        $target_user_id,
        'User account deleted: ' . (string) $target_user['username'],
        $old_snapshot,
        null,
        'warning'
    );

    $_SESSION['success_message'] = 'User deleted successfully.';
    redirect_to('../pages/user-management.php');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete user failed: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to delete user due to a database error.';
    redirect_to('../pages/user-management.php');
}
