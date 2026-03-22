<?php
/**
 * Revoke Active Session Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

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

$session_record_id = filter_input(INPUT_POST, 'session_record_id', FILTER_VALIDATE_INT);
if (!$session_record_id) {
    $_SESSION['error_message'] = 'Invalid session record selected.';
    redirect_to('../pages/user-management.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT aus.id, aus.session_id, aus.user_id, aus.role, aus.is_active, u.username, u.fullname
                           FROM active_user_sessions aus
                           LEFT JOIN users u ON u.id = aus.user_id
                           WHERE aus.id = ?
                           FOR UPDATE");
    $stmt->execute([$session_record_id]);
    $session_row = $stmt->fetch();

    if (!$session_row) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Session record not found.';
        redirect_to('../pages/user-management.php');
    }

    if ((int) $session_row['is_active'] !== 1) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Session is already inactive.';
        redirect_to('../pages/user-management.php');
    }

    if (!empty($session_row['session_id']) && $session_row['session_id'] === session_id()) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'You cannot terminate your current session from this page.';
        redirect_to('../pages/user-management.php');
    }

    $update_stmt = $pdo->prepare("UPDATE active_user_sessions
                                  SET is_active = 0,
                                      ended_at = NOW(),
                                      ended_reason = 'revoked_by_admin'
                                  WHERE id = ?");
    $update_stmt->execute([$session_record_id]);

    $pdo->commit();

    $target_user = $session_row['username'] ?: ('user_id=' . (int) ($session_row['user_id'] ?? 0));
    $details = 'Admin revoked active session for ' . $target_user;
    $old_value = sprintf('session_id=%s; role=%s; is_active=1', (string) $session_row['session_id'], (string) $session_row['role']);
    $new_value = sprintf('session_id=%s; role=%s; is_active=0; ended_reason=revoked_by_admin', (string) $session_row['session_id'], (string) $session_row['role']);

    log_activity_db(
        $pdo,
        'edit',
        'session',
        (int) $session_row['id'],
        $details,
        $old_value,
        $new_value,
        'warning'
    );

    $_SESSION['success_message'] = 'Session terminated successfully.';
    redirect_to('../pages/user-management.php');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Failed to revoke session: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to terminate session due to a database error.';
    redirect_to('../pages/user-management.php');
}
