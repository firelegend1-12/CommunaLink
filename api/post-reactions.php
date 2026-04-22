<?php
require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php'; // Ensure user is logged in
require_once '../includes/csrf.php';
require_once '../includes/permission_checker.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

require_permission_or_json('view_announcements', 403, 'Forbidden');

$reactions_rate_limit = RateLimiter::checkRateLimit('post_reactions_api', RateLimiter::getClientIP());
if (!$reactions_rate_limit['allowed']) {
    $retry_after = (int) ($reactions_rate_limit['lockout_remaining'] ?? 60);
    header('Retry-After: ' . $retry_after);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too Many Requests',
        'message' => $reactions_rate_limit['message'] ?? 'Too many requests. Please try again later.',
        'retry_after' => $retry_after
    ]);
    exit;
}

RateLimiter::recordAttempt('post_reactions_api', RateLimiter::getClientIP());

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token', 'required_permission' => 'csrf_token']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$post_id = $_POST['post_id'] ?? null;
$reaction_type = $_POST['reaction_type'] ?? null;

if (!$user_id || !$post_id || !in_array($reaction_type, ['like', 'acknowledge'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    // Check if reaction already exists
    $stmt = $pdo->prepare("SELECT id FROM post_reactions WHERE post_id = ? AND resident_id = ? AND reaction_type = ?");
    $stmt->execute([$post_id, $user_id, $reaction_type]);
    $existing = $stmt->fetch();

    $is_active = false;
    if ($existing) {
        // Toggle off
        $stmt = $pdo->prepare("DELETE FROM post_reactions WHERE id = ?");
        $stmt->execute([$existing['id']]);
        $is_active = false;
    } else {
        // Toggle on
        $stmt = $pdo->prepare("INSERT INTO post_reactions (post_id, resident_id, reaction_type) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $user_id, $reaction_type]);
        $is_active = true;
    }

    // Get new count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_reactions WHERE post_id = ? AND reaction_type = ?");
    $stmt->execute([$post_id, $reaction_type]);
    $new_count = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'is_active' => $is_active,
        'new_count' => (int)$new_count
    ]);

} catch (PDOException $e) {
    error_log('post-reactions database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while updating reaction']);
}
