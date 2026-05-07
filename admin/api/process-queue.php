<?php
/**
 * Admin-Authorized Queue Worker
 * Processes pending public_post_dispatch_queue jobs inline.
 */

require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/permission_checker.php';
require_once __DIR__ . '/../../includes/notification_system.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_announcements', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
if ($limit === false || $limit === null) {
    $limit = 10;
}
$limit = max(1, min(100, (int)$limit));

$result = NotificationSystem::process_public_post_queue($pdo, $limit);

echo json_encode([
    'success' => !empty($result['success']),
    'error' => $result['error'] ?? null,
    'processed' => (int)($result['processed'] ?? 0),
    'completed' => (int)($result['completed'] ?? 0),
    'failed' => (int)($result['failed'] ?? 0),
    'requeued' => (int)($result['requeued'] ?? 0),
    'remaining' => (int)($result['remaining'] ?? 0),
]);
