<?php
/**
 * Cron: Cleanup expired active sessions
 *
 * Usage:
 *   php cron_cleanup_active_sessions.php
 */

require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

try {
    $expired_count = clear_expired_active_sessions_with_audit($pdo, 'cron');
    echo sprintf("Expired session cleanup completed. Rows updated: %d\n", (int) $expired_count);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Session cleanup failed: " . $e->getMessage() . "\n");
    exit(1);
}
