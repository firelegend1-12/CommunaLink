<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/notification_system.php';

header('Content-Type: application/json');

require_login();
require_permission_or_json('manage_businesses', 403, 'Forbidden');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$resident_id = isset($_POST['resident_id']) ? (int) $_POST['resident_id'] : 0;
$business_id = isset($_POST['business_id']) ? (int) $_POST['business_id'] : 0;
$business_name = sanitize_input(trim($_POST['business_name'] ?? 'your business'));
$expiry_date = trim($_POST['expiry_date'] ?? '');

if ($resident_id <= 0 || $business_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$rate_identifier = ($_SESSION['user_id'] ?? 'unknown') . ':' . $resident_id;
$limit = RateLimiter::checkRateLimit('api_calls', $rate_identifier);
if (!$limit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => $limit['message'] ?? 'Too many requests. Please try again later.'
    ]);
    exit;
}

RateLimiter::recordAttempt('api_calls', $rate_identifier);

try {
    $stmt = $pdo->prepare("SELECT r.id, r.user_id, r.first_name, r.last_name, r.email as resident_email, u.email as user_email
                           FROM residents r
                           LEFT JOIN users u ON r.user_id = u.id
                           WHERE r.id = ?");
    $stmt->execute([$resident_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Resident not found']);
        exit;
    }

    $business_owner_stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE id = ? AND resident_id = ?");
    $business_owner_stmt->execute([$business_id, $resident_id]);
    $is_business_owned_by_resident = ((int) $business_owner_stmt->fetchColumn()) > 0;

    if (!$is_business_owned_by_resident) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Business record does not belong to the selected resident']);
        exit;
    }

    $resident_name = trim(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? 'Resident'));
    $resident_email = trim((string) ($resident['resident_email'] ?: $resident['user_email'] ?: ''));

    $resident_user_id = (int) ($resident['user_id'] ?? 0);
    if ($resident_user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Resident account is not linked to a user profile']);
        exit;
    }

    $delivery = NotificationSystem::notify_business_expiry(
        $pdo,
        $resident_user_id,
        $resident_name,
        $resident_email,
        $business_id,
        $business_name,
        $expiry_date,
        'my-requests.php'
    );

    if (!$delivery['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $delivery['error'] ?? 'Failed to send reminder']);
        exit;
    }

    $email_sent = (bool) ($delivery['email_sent'] ?? false);

    log_activity_db(
        $pdo,
        'send_reminder',
        'business',
        $business_id,
        "Renewal reminder sent for business '{$business_name}' to resident ID {$resident_id}",
        null,
        $email_sent ? 'in_app+email' : 'in_app_only'
    );

    echo json_encode([
        'success' => true,
        'message' => $email_sent ? 'Reminder sent via in-app notification and email.' : 'Reminder sent in-app. Email was not delivered.',
        'email_sent' => $email_sent
    ]);
} catch (Exception $e) {
    error_log('send-business-reminder failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send reminder']);
}
