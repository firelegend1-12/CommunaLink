<?php
/**
 * Lightweight smoke test for NotificationSystem service behavior.
 *
 * Usage (CLI):
 *   php test_notification_service_smoke.php --user-id=123 --business-id=1
 *
 * If --user-id is omitted, the script will auto-pick the first resident with linked user_id.
 * Changes are wrapped in a transaction and rolled back.
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'This smoke script is CLI-only.',
    ]);
    exit(1);
}

require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/notification_system.php';

header('Content-Type: application/json');

function smoke_exit($code, array $payload) {
    if (php_sapi_name() !== 'cli') {
        http_response_code($code === 0 ? 200 : 400);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT);
    if (php_sapi_name() === 'cli') {
        echo PHP_EOL;
    }
    exit($code);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    smoke_exit(1, ['success' => false, 'error' => 'Database connection unavailable.']);
}

try {
    $options = [];
    if (php_sapi_name() === 'cli') {
        $options = getopt('', ['user-id::', 'business-id::']);
    } else {
        $options = [
            'user-id' => $_GET['user_id'] ?? null,
            'business-id' => $_GET['business_id'] ?? null,
        ];
    }

    $user_id = isset($options['user-id']) ? (int) $options['user-id'] : 0;
    $business_id = isset($options['business-id']) ? (int) $options['business-id'] : 1;

    if ($business_id <= 0) {
        $business_id = 1;
    }

    if ($user_id <= 0) {
        $pick_stmt = $pdo->query("SELECT r.user_id FROM residents r WHERE r.user_id IS NOT NULL ORDER BY r.id ASC LIMIT 1");
        $user_id = (int) $pick_stmt->fetchColumn();
    }

    if ($user_id <= 0) {
        smoke_exit(1, [
            'success' => false,
            'error' => 'No resident-linked user found. Pass --user-id or create a resident with user_id.',
        ]);
    }

    $pdo->beginTransaction();

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $before_count = (int) $count_stmt->fetchColumn();

    $result = NotificationSystem::notify_business_expiry(
        $pdo,
        $user_id,
        'Smoke Test User',
        '', // intentionally blank to avoid sending external email during smoke test
        $business_id,
        'Smoke Test Business',
        '2099-12-31',
        'my-requests.php'
    );

    $count_stmt->execute([$user_id]);
    $after_count = (int) $count_stmt->fetchColumn();

    $insert_delta = $after_count - $before_count;
    $passed = !empty($result['success'])
        && !empty($result['notification_created'])
        && $insert_delta === 1
        && ($result['email_sent'] === false);

    $last_stmt = $pdo->prepare("SELECT id, user_id, title, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $last_stmt->execute([$user_id]);
    $last_row = $last_stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->rollBack();

    smoke_exit($passed ? 0 : 1, [
        'success' => $passed,
        'phase' => 'phase4-smoke',
        'checks' => [
            'service_call_success' => !empty($result['success']),
            'notification_created_flag' => !empty($result['notification_created']),
            'notification_insert_delta' => $insert_delta,
            'email_sent_expected_false' => ($result['email_sent'] === false),
        ],
        'result' => $result,
        'sample_last_row_inside_tx' => $last_row,
        'note' => 'All DB changes were rolled back.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    smoke_exit(1, [
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
