<?php
/**
 * Bulk Action Handler for Requests
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/notification_system.php';

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
$status = isset($_POST['status']) ? normalize_request_status_display($_POST['status']) : '';

if (empty($ids) || empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$valid_statuses = canonical_request_statuses();
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
    $notification_failed_count = 0;
    $notification_missing_count = 0;

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

        $doc_notification_rows = [];
        $biz_notification_rows = [];
        if (!empty($doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($doc_ids), '?'));
            $notify_stmt = $pdo->prepare("SELECT id, status, document_type FROM document_requests WHERE id IN ({$placeholders})");
            $notify_stmt->execute($doc_ids);
            $doc_notification_rows = $notify_stmt->fetchAll(PDO::FETCH_ASSOC);

            $doc_status = normalize_request_status_for_storage($pdo, 'document_requests', $status);
            $params = array_merge([$doc_status], $doc_ids);
            $stmt = $pdo->prepare("UPDATE document_requests SET status = ? WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $updated_count += $stmt->rowCount();
        }

        if (!empty($biz_ids)) {
            $placeholders = implode(',', array_fill(0, count($biz_ids), '?'));
            $notify_stmt = $pdo->prepare("SELECT id, status, business_name, transaction_type, remarks FROM business_transactions WHERE id IN ({$placeholders})");
            $notify_stmt->execute($biz_ids);
            $biz_notification_rows = $notify_stmt->fetchAll(PDO::FETCH_ASSOC);

            $biz_status = normalize_request_status_for_storage($pdo, 'business_transactions', $status);
            $params = array_merge([$biz_status], $biz_ids);
            $stmt = $pdo->prepare("UPDATE business_transactions SET status = ? WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $updated_count += $stmt->rowCount();
        }

        foreach ($doc_notification_rows as $doc_row) {
            $doc_id = (int)($doc_row['id'] ?? 0);
            $old_status = normalize_request_status_display($doc_row['status'] ?? null);
            if ($doc_id <= 0 || $old_status === $status) {
                continue;
            }

            $recipient_user_id = get_document_request_recipient_user_id($pdo, $doc_id);
            if ($recipient_user_id === null) {
                $notification_missing_count++;
                error_log('No recipient user found in bulk-action-requests for request_id=' . $doc_id);
                continue;
            }

            $notification_sent = NotificationSystem::notify_document_status(
                $pdo,
                $recipient_user_id,
                (string)($doc_row['document_type'] ?? 'Document Request'),
                $status,
                'my-document-requests.php'
            );
            if (!$notification_sent) {
                $notification_failed_count++;
                $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
                error_log('Notification delivery failed in bulk-action-requests for request_id=' . $doc_id . ($detail ? ' detail=' . $detail : ''));
            }
        }

        foreach ($biz_notification_rows as $biz_row) {
            $biz_id = (int)($biz_row['id'] ?? 0);
            $old_status = normalize_request_status_display($biz_row['status'] ?? null);
            if ($biz_id <= 0 || $old_status === $status) {
                continue;
            }

            $recipient_user_id = get_business_transaction_recipient_user_id($pdo, $biz_id);
            if ($recipient_user_id === null) {
                $notification_missing_count++;
                error_log('No recipient user found in bulk-action-requests for business_transaction_id=' . $biz_id);
                continue;
            }

            $request_label = get_business_transaction_display_name(
                $biz_row['transaction_type'] ?? ($biz_row['business_name'] ?? 'Business Request'),
                $biz_row['remarks'] ?? null
            );

            $notification_sent = NotificationSystem::notify_business_status(
                $pdo,
                $recipient_user_id,
                $request_label,
                $status,
                'my-requests.php'
            );
            if (!$notification_sent) {
                $notification_failed_count++;
                $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
                error_log('Notification delivery failed in bulk-action-requests for business_transaction_id=' . $biz_id . ($detail ? ' detail=' . $detail : ''));
            }
        }

        $response = [
            'success' => true,
            'message' => "Updated {$updated_count} request(s) to '{$status}'",
            'updated_count' => $updated_count
        ];
        if ($notification_failed_count > 0 || $notification_missing_count > 0) {
            $response['warning'] = "Status updated, but {$notification_failed_count} notification(s) failed and {$notification_missing_count} recipient(s) were missing.";
            $detail = function_exists('get_last_notification_error') ? get_last_notification_error() : null;
            if ($detail) {
                $response['notification_error'] = $detail;
            }
        }

        echo json_encode($response);

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
            $cancelled_status = normalize_request_status_for_storage($pdo, 'business_transactions', 'Cancelled');
            $stmt = $pdo->prepare("UPDATE business_transactions
                                   SET status = ?,
                                       processed_date = COALESCE(processed_date, NOW())
                                   WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$cancelled_status], $biz_ids));
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
