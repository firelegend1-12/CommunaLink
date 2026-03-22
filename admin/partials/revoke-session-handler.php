<?php
/**
 * Revoke Active Session Handler
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
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

$action_mode = sanitize_input($_POST['action_mode'] ?? 'single');

try {
    if ($action_mode === 'cleanup_expired') {
        $expired_count = clear_expired_active_sessions_with_audit($pdo, 'admin_manual');

        if ($expired_count > 0) {
            $_SESSION['success_message'] = sprintf('Expired session cleanup completed: %d session(s) marked inactive.', $expired_count);
        } else {
            $_SESSION['warning_message'] = 'No expired active sessions were found.';
        }

        redirect_to('../pages/user-management.php');
    }

    if ($action_mode === 'bulk_idle') {
        $idle_minutes = filter_input(INPUT_POST, 'idle_minutes', FILTER_VALIDATE_INT);
        if (!$idle_minutes) {
            $idle_minutes = 15;
        }
        $idle_minutes = max(1, min(720, (int) $idle_minutes));
        $idle_cutoff = date('Y-m-d H:i:s', time() - ($idle_minutes * 60));

        $pdo->beginTransaction();

        $bulk_stmt = $pdo->prepare("UPDATE active_user_sessions
                                    SET is_active = 0,
                                        ended_at = NOW(),
                                        ended_reason = 'revoked_idle_bulk'
                                    WHERE is_active = 1
                                      AND expires_at > NOW()
                                      AND last_seen_at < ?
                                      AND session_id <> ?");
        $bulk_stmt->execute([$idle_cutoff, session_id()]);
        $affected = (int) $bulk_stmt->rowCount();

        $pdo->commit();

        log_activity_db(
            $pdo,
            'revoke',
            'session',
            null,
            sprintf('Bulk idle session termination executed: %d session(s) older than %d minutes.', $affected, $idle_minutes),
            null,
            sprintf('idle_minutes=%d; affected=%d', $idle_minutes, $affected),
            'warning'
        );

        if ($affected > 0) {
            $_SESSION['success_message'] = sprintf('Terminated %d idle active session(s) older than %d minutes.', $affected, $idle_minutes);
        } else {
            $_SESSION['warning_message'] = sprintf('No active sessions exceeded %d idle minutes.', $idle_minutes);
        }

        redirect_to('../pages/user-management.php');
    }

    $session_record_id = filter_input(INPUT_POST, 'session_record_id', FILTER_VALIDATE_INT);
    if (!$session_record_id) {
        $_SESSION['error_message'] = 'Invalid session record selected.';
        redirect_to('../pages/user-management.php');
    }

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
        'revoke',
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
