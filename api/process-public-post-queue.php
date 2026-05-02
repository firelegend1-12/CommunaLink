<?php
/**
 * API Endpoint: Process Public Post Dispatch Queue
 *
 * - POST only
 * - Scheduler-token authorized
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification_system.php';

function queue_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function queue_token_candidates(array $envKeys): array
{
    $tokens = [];
    foreach ($envKeys as $envKey) {
        $value = trim((string) env($envKey, ''));
        if ($value !== '') {
            $tokens[$envKey] = $value;
        }
    }

    return $tokens;
}

function is_queue_scheduler_authorized(): array
{
    $providedToken = queue_header('X-Cloud-Scheduler-Token');
    $providedScope = strtolower(queue_header('X-Scheduler-Scope'));
    $expectedScope = 'public_post_queue';

    if ($providedToken === '') {
        return ['authorized' => false, 'reason' => 'missing_token'];
    }

    if ($providedScope !== '' && $providedScope !== $expectedScope) {
        return ['authorized' => false, 'reason' => 'invalid_scope'];
    }

    $tokenMap = queue_token_candidates([
        'PUBLIC_POST_QUEUE_SCHEDULER_TOKEN_CURRENT',
        'PUBLIC_POST_QUEUE_SCHEDULER_TOKEN_NEXT',
        'PUBLIC_POST_QUEUE_SCHEDULER_TOKEN',
        'PERMIT_CHECK_SCHEDULER_TOKEN_CURRENT',
        'PERMIT_CHECK_SCHEDULER_TOKEN_NEXT',
        'PERMIT_CHECK_SCHEDULER_TOKEN',
    ]);

    foreach ($tokenMap as $source => $expectedToken) {
        if (hash_equals($expectedToken, $providedToken)) {
            return [
                'authorized' => true,
                'source' => $source,
                'scope' => ($providedScope === '' ? 'unspecified' : $providedScope),
            ];
        }
    }

    return ['authorized' => false, 'reason' => 'token_mismatch'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$schedulerAuth = is_queue_scheduler_authorized();
if (!(bool)($schedulerAuth['authorized'] ?? false)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized request.',
        'required_permission' => 'scheduler_token:public_post_queue',
    ]);
    exit;
}

$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
if ($limit === false || $limit === null) {
    $limit = 10;
}

$limit = max(1, min(100, (int)$limit));

$lockName = 'public_post_queue_scheduler_lock';
$lockAcquired = false;

try {
    ignore_user_abort(true);
    @set_time_limit(120);

    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 1)');
    $lockStmt->execute([$lockName]);
    $lockAcquired = ((int)$lockStmt->fetchColumn() === 1);

    if (!$lockAcquired) {
        http_response_code(202);
        echo json_encode([
            'status' => 'success',
            'message' => 'Queue worker already running; skipping duplicate scheduler execution.',
            'timestamp' => gmdate('c'),
            'scheduler_scope' => (string)($schedulerAuth['scope'] ?? 'unspecified'),
            'scheduler_token_source' => (string)($schedulerAuth['source'] ?? 'unknown'),
        ]);
        exit;
    }

    $result = NotificationSystem::process_public_post_queue($pdo, $limit);

    http_response_code(202);
    echo json_encode([
        'status' => !empty($result['success']) ? 'success' : 'error',
        'message' => !empty($result['success']) ? 'Queue processing completed.' : (string)($result['error'] ?? 'Queue processing failed.'),
        'processed' => (int)($result['processed'] ?? 0),
        'completed' => (int)($result['completed'] ?? 0),
        'failed' => (int)($result['failed'] ?? 0),
        'requeued' => (int)($result['requeued'] ?? 0),
        'remaining' => (int)($result['remaining'] ?? 0),
        'scheduler_scope' => (string)($schedulerAuth['scope'] ?? 'unspecified'),
        'scheduler_token_source' => (string)($schedulerAuth['source'] ?? 'unknown'),
        'timestamp' => gmdate('c'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Queue processing failed.',
        'detail' => $e->getMessage(),
        'timestamp' => gmdate('c'),
    ]);
} finally {
    if ($lockAcquired) {
        $unlockStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $unlockStmt->execute([$lockName]);
    }
}
