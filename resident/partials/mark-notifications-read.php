<?php
/**
 * @deprecated Use /api/notifications.php?action=mark_all_read instead.
 *
 * Compatibility shim kept intentionally to support staggered/cloud rollouts
 * where older clients may still call this endpoint briefly after deployment.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('X-Endpoint-Deprecated: true');
header('X-Deprecated-Use: /api/notifications.php?action=mark_all_read');

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';

if (function_exists('env')) {
    $legacy_enabled_raw = env('LEGACY_MARK_NOTIFICATIONS_READ_ENABLED', 'true');
    $legacy_enabled = in_array(strtolower(trim((string) $legacy_enabled_raw)), ['1', 'true', 'yes', 'on'], true);
    if (!$legacy_enabled) {
        http_response_code(410);
        echo json_encode([
            'success' => false,
            'deprecated' => true,
            'error' => 'Endpoint retired',
            'migrate_to' => '/api/notifications.php?action=mark_all_read',
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Backward compatibility: do not hard-fail when token is missing from older cached clients,
// but reject explicitly invalid tokens when present.
$posted_token = $_POST['csrf_token'] ?? null;
if ($posted_token !== null && $posted_token !== '' && !csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$resident_id = (int) ($_SESSION['resident_id'] ?? 0);

if ($user_id <= 0 && $resident_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

$updated_count = 0;
$used_column = null;

// Prefer canonical user_id path for current schema.
if ($user_id > 0) {
    try {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$user_id]);
        $updated_count = (int) $stmt->rowCount();
        $used_column = 'user_id';
    } catch (PDOException $e) {
        // Fall through to resident_id compatibility path.
    }
}

// Compatibility path for older/transitioning schemas that still rely on resident_id.
if ($used_column === null && $resident_id > 0) {
    try {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE resident_id = ? AND is_read = 0');
        $stmt->execute([$resident_id]);
        $updated_count = (int) $stmt->rowCount();
        $used_column = 'resident_id';
    } catch (PDOException $e) {
        error_log('legacy mark-notifications-read database error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error while updating notifications']);
        exit;
    }
}

if ($used_column === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update notifications']);
    exit;
}

echo json_encode([
    'success' => true,
    'deprecated' => true,
    'migrate_to' => '/api/notifications.php?action=mark_all_read',
    'updated_count' => $updated_count,
    'compat_column' => $used_column,
]);