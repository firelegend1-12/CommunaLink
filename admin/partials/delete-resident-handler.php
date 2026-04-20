<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

require_login();
require_permission_or_redirect('manage_residents', '../pages/residents.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../pages/residents.php');
    exit;
}

if (!csrf_validate()) {
    $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
    header('Location: ../pages/residents.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resident_id'])) {
    $resident_id = intval($_POST['resident_id']);
    try {
        // Check for active document requests
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE resident_id = ? AND status NOT IN ('Completed', 'Rejected', 'Cancelled')");
        $stmt->execute([$resident_id]);
        $active_requests = $stmt->fetchColumn();
        if ($active_requests > 0) {
            $_SESSION['error_message'] = 'Cannot remove resident with active document requests.';
        } else {
            // Fetch old data before deletion
            $stmt_old = $pdo->prepare('SELECT * FROM residents WHERE id = ?');
            $stmt_old->execute([$resident_id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare('DELETE FROM residents WHERE id = ?');
            $stmt->execute([$resident_id]);
            // Log the deletion with old data
            log_activity_db(
                $pdo,
                'delete',
                'resident',
                $resident_id,
                'Resident deleted',
                json_encode($old_data),
                null
            );
            $_SESSION['success_message'] = 'Resident removed successfully.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Failed to remove resident.';
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}
header('Location: ../pages/residents.php');
exit; 