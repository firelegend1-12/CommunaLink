<?php
require_once '../config/database.php';
require_once '../includes/auth.php'; // Ensure user is logged in
require_once '../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$reactions_rate_limit = RateLimiter::checkRateLimit('post_reactions_api', RateLimiter::getClientIP());
if (!$reactions_rate_limit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $reactions_rate_limit['message'] ?? 'Too many requests. Please try again later.',
        'retry_after' => $reactions_rate_limit['lockout_remaining'] ?? 60
    ]);
    exit;
}

RateLimiter::recordAttempt('post_reactions_api', RateLimiter::getClientIP());

if (!csrf_validate()) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$post_id = $_POST['post_id'] ?? null;
$reaction_type = $_POST['reaction_type'] ?? null;

if (!$user_id || !$post_id || !in_array($reaction_type, ['like', 'acknowledge'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
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
    echo json_encode(['success' => false, 'message' => 'Database error while updating reaction']);
}
