<?php
/**
 * AJAX Handler: Update Incident Status
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/notification_system.php';

header('Content-Type: application/json');

// Check if user is logged in as an authorized official
if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
$rejection_reason = isset($_POST['rejection_reason']) ? trim((string) $_POST['rejection_reason']) : '';
$allowed_statuses = ['Pending', 'Resolved', 'Rejected'];

if (!$id || !in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid Request Data']);
    exit;
}

if ($status === 'Rejected' && $rejection_reason === '') {
    echo json_encode(['success' => false, 'error' => 'A rejection reason is required.']);
    exit;
}

function normalize_reason_text(string $reason): string
{
    $reason = trim($reason);
    $reason = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $reason);
    $reason = (string) preg_replace('/\s+/u', ' ', $reason);
    return trim($reason);
}

function is_unknown_column_error(PDOException $e): bool
{
    $sql_state = (string) $e->getCode();
    $message = strtolower((string) $e->getMessage());
    return $sql_state === '42S22' || strpos($message, 'unknown column') !== false;
}

try {
    $rejection_reason = normalize_reason_text($rejection_reason);

    // 1. Fetch old status for logging
    $stmt = $pdo->prepare("SELECT id, status, resident_user_id, type FROM incidents WHERE id = ?");
    $stmt->execute([$id]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $old_status = $old_data ? $old_data['status'] : null;
    $resident_user_id = $old_data ? (int) $old_data['resident_user_id'] : 0;
    $incident_type = $old_data ? (string) $old_data['type'] : '';

    if ($old_status === $status) {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit;
    }

    if (($old_status === 'Resolved' || $old_status === 'Rejected') && $status !== $old_status) {
        echo json_encode(['success' => false, 'error' => 'Resolved or rejected reports cannot be changed.']);
        exit;
    }

    // 2. Update Status
    $new_rejection_reason = $status === 'Rejected' ? $rejection_reason : null;
    $reason_persisted = true;
    try {
        $stmt = $pdo->prepare("UPDATE incidents SET status = ?, rejection_reason = ? WHERE id = ?");
        $result = $stmt->execute([$status, $new_rejection_reason, $id]);
    } catch (PDOException $updateException) {
        if (!is_unknown_column_error($updateException)) {
            throw $updateException;
        }

        $reason_persisted = false;
        $stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $id]);
    }

    if ($result) {
        // 3. Log Activity
        log_activity_db(
            $pdo,
            'edit',
            'incident',
            $id,
            "Updated incident #{$id} status from '{$old_status}' to '{$status}'" . ($status === 'Rejected' && $rejection_reason !== '' ? " (reason: {$rejection_reason})" : ''),
            "status: {$old_status}",
            "status: {$status}" . ($status === 'Rejected' && $rejection_reason !== '' ? "\nrejection_reason: {$rejection_reason}" : '')
        );

        if ($resident_user_id > 0) {
            $notification_reason = $status === 'Rejected' ? $rejection_reason : '';
            $notification_link = '/resident/report-details.php?id=' . $id;
            $notification_sent = NotificationSystem::notify_incident_status(
                $pdo,
                $resident_user_id,
                $incident_type,
                $status,
                $notification_reason,
                $notification_link
            );

            if (!$notification_sent) {
                error_log('Incident notification delivery failed for incident_id=' . $id);
            }
        }

        // 4. Recalculate Stats for response
        $stats = [
            'active_cases' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn(),
            'trending_today' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE reported_at >= NOW() - INTERVAL 1 DAY")->fetchColumn(),
            'resolution_rate' => 0
        ];

        // Monthly Stats
        $stmt_m = $pdo->query("SELECT COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved, COUNT(*) as total FROM incidents WHERE MONTH(reported_at) = MONTH(CURRENT_DATE()) AND YEAR(reported_at) = YEAR(CURRENT_DATE())");
        $m_data = $stmt_m->fetch();
        $stats['resolution_rate'] = ($m_data['total'] > 0) ? round(($m_data['resolved'] / $m_data['total']) * 100) : 0;

        $response = ['success' => true, 'message' => 'Status updated successfully', 'stats' => $stats];
        if ($status === 'Rejected' && !$reason_persisted) {
            $response['warning'] = 'Status updated, but rejection reason could not be stored in incidents table.';
        }

        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
