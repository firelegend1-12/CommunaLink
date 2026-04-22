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

function cleanup_scheduler_token_candidates(array $envKeys): array
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

function is_cleanup_scheduler_authorized(): array
{
    $providedToken = scheduler_header('X-Cloud-Scheduler-Token');
    $providedScope = strtolower(scheduler_header('X-Scheduler-Scope'));
    $expectedScope = 'session_cleanup';

    if ($providedToken === '') {
        return ['authorized' => false, 'reason' => 'missing_token'];
    }

    if ($providedScope !== '' && $providedScope !== $expectedScope) {
        return ['authorized' => false, 'reason' => 'invalid_scope'];
    }

    $tokenMap = cleanup_scheduler_token_candidates([
        'SESSION_CLEANUP_SCHEDULER_TOKEN_CURRENT',
        'SESSION_CLEANUP_SCHEDULER_TOKEN_NEXT',
        'SESSION_CLEANUP_SCHEDULER_TOKEN',
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

$schedulerAuth = is_cleanup_scheduler_authorized();
if (!(bool)($schedulerAuth['authorized'] ?? false)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized request.',
        'required_permission' => 'scheduler_token:session_cleanup',
    ]);
    exit;
}

try {
    $updated = clear_expired_active_sessions_with_audit($pdo, 'scheduler_http');

    http_response_code(202);
    echo json_encode([
        'status' => 'success',
        'message' => 'Expired session cleanup completed.',
        'rows_updated' => (int) $updated,
        'scheduler_scope' => (string)($schedulerAuth['scope'] ?? 'unspecified'),
        'scheduler_token_source' => (string)($schedulerAuth['source'] ?? 'unknown'),
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
