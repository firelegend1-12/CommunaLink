<?php
/**
 * Admin-Authorized Queue Worker
 * Processes pending public_post_dispatch_queue jobs inline.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/notification_system.php';

session_start();

// Verify active admin session
if (empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
if ($limit === false || $limit === null) {
    $limit = 10;
}
$limit = max(1, min(100, (int)$limit));

$result = NotificationSystem::process_public_post_queue($pdo, $limit);

header('Content-Type: application/json');
echo json_encode([
    'success' => !empty($result['success']),
    'error' => $result['error'] ?? null,
    'processed' => (int)($result['processed'] ?? 0),
    'completed' => (int)($result['completed'] ?? 0),
    'failed' => (int)($result['failed'] ?? 0),
    'requeued' => (int)($result['requeued'] ?? 0),
    'remaining' => (int)($result['remaining'] ?? 0),
]);
