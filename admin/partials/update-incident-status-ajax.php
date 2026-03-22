<?php
/**
 * AJAX Handler: Update Incident Status
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in as an authorized official
if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
$allowed_statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

if (!$id || !in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid Request Data']);
    exit;
}

try {
    // 1. Fetch old status for logging
    $stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
    $stmt->execute([$id]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $old_status = $old_data ? $old_data['status'] : null;

    if ($old_status === $status) {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit;
    }

    // 2. Update Status
    $stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);

    if ($result) {
        // 3. Log Activity
        log_activity_db(
            $pdo,
            'edit',
            'incident',
            $id,
            "Updated incident #{$id} status from '{$old_status}' to '{$status}'",
            "status: {$old_status}",
            "status: {$status}"
        );

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

        echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'stats' => $stats]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
