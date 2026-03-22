<?php
/**
 * AJAX Handler: Update Incident Remarks
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
$remarks = isset($_POST['remarks']) ? sanitize_input($_POST['remarks']) : '';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Incident ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE incidents SET admin_remarks = ? WHERE id = ?");
    $result = $stmt->execute([$remarks, $id]);

    if ($result) {
        log_activity_db(
            $pdo,
            'update_remarks',
            'incident',
            $id,
            "Updated admin remarks for incident #{$id}",
            null,
            $remarks
        );
        echo json_encode(['success' => true, 'message' => 'Remarks updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update remarks']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
