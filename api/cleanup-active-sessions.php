<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once __DIR__ . '/../includes/auth.php';

function scheduler_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function is_cleanup_scheduler_authorized(): bool
{
    $token = trim((string) env('SESSION_CLEANUP_SCHEDULER_TOKEN', ''));
    if ($token === '') {
        $token = trim((string) env('PERMIT_CHECK_SCHEDULER_TOKEN', ''));
    }

    $provided = scheduler_header('X-Cloud-Scheduler-Token');
    if ($token === '' || $provided === '') {
        return false;
    }

    return hash_equals($token, $provided);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

if (!is_cleanup_scheduler_authorized()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized request.']);
    exit;
}

try {
    $updated = clear_expired_active_sessions_with_audit($pdo, 'scheduler_http');

    http_response_code(202);
    echo json_encode([
        'status' => 'success',
        'message' => 'Expired session cleanup completed.',
        'rows_updated' => (int) $updated,
        'timestamp' => gmdate('c'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Session cleanup failed.',
        'detail' => $e->getMessage(),
        'timestamp' => gmdate('c'),
    ]);
}
