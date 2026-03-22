<?php
/**
 * Bulk Action Handler for Requests
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
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

try {
    $updated_count = 0;
    $deleted_count = 0;

    if ($action === 'bulk_status') {
        if (!in_array($status, $valid_statuses)) {
            throw new Exception('Invalid status');
        }

        // Update documents
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

        // Bulk update documents
        if (!empty($doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
            $params = array_merge([$status], $doc_ids);
            $stmt = $pdo->prepare("UPDATE document_requests SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $updated_count += $stmt->rowCount();
        }

        // Bulk update business transactions
        if (!empty($biz_ids)) {
            $placeholders = implode(',', array_fill(0, count($biz_ids), '?'));
            $params = array_merge([$status], $biz_ids);
            $stmt = $pdo->prepare("UPDATE business_transactions SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $updated_count += $stmt->rowCount();
        }

        echo json_encode([
            'success' => true,
            'message' => "Updated {$updated_count} request(s) to '{$status}'",
            'updated_count' => $updated_count
        ]);

    } elseif ($action === 'bulk_delete') {
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

        // Bulk delete documents
        if (!empty($doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM document_requests WHERE id IN ({$placeholders})");
            $stmt->execute($doc_ids);
            $deleted_count += $stmt->rowCount();
        }

        // Bulk delete business transactions
        if (!empty($biz_ids)) {
            $placeholders = implode(',', array_fill(0, count($biz_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM business_transactions WHERE id IN ({$placeholders})");
            $stmt->execute($biz_ids);
            $deleted_count += $stmt->rowCount();
        }

        echo json_encode([
            'success' => true,
            'message' => "Deleted {$deleted_count} request(s)",
            'deleted_count' => $deleted_count
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
