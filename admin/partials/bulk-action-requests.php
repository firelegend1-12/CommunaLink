<?php
/**
 * Bulk Action Handler for Requests
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';
$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
$types = isset($_POST['types']) && is_array($_POST['types']) ? array_map('sanitize_input', $_POST['types']) : [];
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';

if (empty($ids) || empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$valid_statuses = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled'];
$valid_actions = ['bulk_status', 'bulk_delete'];

if (!in_array($action, $valid_actions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$doc_ids = [];
$biz_ids = [];
foreach ($ids as $i => $id) {
    $type = $types[$i] ?? '';
    if ($type === 'document') {
        $doc_ids[] = $id;
    } elseif ($type === 'business') {
        $biz_ids[] = $id;
    }
}

if (empty($doc_ids) && empty($biz_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid request targets provided']);
    exit;
}

$deny_permission = function ($permission_key) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
        'required_permission' => $permission_key,
    ]);
    exit;
};

try {
    $updated_count = 0;
    $deleted_count = 0;
    $cancelled_count = 0;

    if ($action === 'bulk_status') {
        if (!in_array($status, $valid_statuses, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }

        if (!empty($doc_ids) && !require_permission('manage_documents')) {
            $deny_permission('manage_documents');
        }

        if (!empty($biz_ids) && !require_permission('manage_businesses')) {
            $deny_permission('manage_businesses');
        }

        if (!empty($doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
            $params = array_merge([$status], $doc_ids);
            $stmt = $pdo->prepare("UPDATE document_requests SET status = ? WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $updated_count += $stmt->rowCount();
        }

        if (!empty($biz_ids)) {
            $placeholders = implode(',', array_fill(0, count($biz_ids), '?'));
            $params = array_merge([$status], $biz_ids);
            $stmt = $pdo->prepare("UPDATE business_transactions SET status = ? WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $updated_count += $stmt->rowCount();
        }

        echo json_encode([
            'success' => true,
            'message' => "Updated {$updated_count} request(s) to '{$status}'",
            'updated_count' => $updated_count
        ]);

    } elseif ($action === 'bulk_delete') {
        if (!empty($doc_ids) && !require_permission('manage_documents')) {
            $deny_permission('manage_documents');
        }

        if (!empty($biz_ids) && !require_permission('manage_businesses')) {
            $deny_permission('manage_businesses');
        }

        $pdo->beginTransaction();

        if (!empty($doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM document_requests WHERE id IN ({$placeholders})");
            $stmt->execute($doc_ids);
            $deleted_count += $stmt->rowCount();
        }

        if (!empty($biz_ids)) {
            $placeholders = implode(',', array_fill(0, count($biz_ids), '?'));
            $stmt = $pdo->prepare("UPDATE business_transactions
                                   SET status = 'Cancelled',
                                       processed_date = COALESCE(processed_date, NOW())
                                   WHERE id IN ({$placeholders})");
            $stmt->execute($biz_ids);
            $cancelled_count += $stmt->rowCount();
        }

        $pdo->commit();

        $message_parts = [];
        if ($deleted_count > 0) {
            $message_parts[] = "Deleted {$deleted_count} document request(s)";
        }
        if ($cancelled_count > 0) {
            $message_parts[] = "Cancelled {$cancelled_count} business transaction(s)";
        }

        if (empty($message_parts)) {
            $message_parts[] = 'No requests were updated';
        }

        echo json_encode([
            'success' => true,
            'message' => implode('; ', $message_parts),
            'deleted_count' => $deleted_count,
            'cancelled_count' => $cancelled_count
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
