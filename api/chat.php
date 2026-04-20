<?php
header('Content-Type: application/json');

// Use lightweight database-only bootstrap — not init.php, which runs all schema migrations on every poll
require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/permission_checker.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function chat_json_error(int $statusCode, string $error, ?string $requiredPermission = null): void
{
    http_response_code($statusCode);

    $payload = [
        'success' => false,
        'error' => $error,
    ];

    if ($requiredPermission !== null && $requiredPermission !== '') {
        $payload['required_permission'] = $requiredPermission;
    }

    echo json_encode($payload);
    exit;
}

function chat_normalize_role($role): string
{
    return normalize_rbac_key((string)$role);
}

function chat_is_resident_role($role): bool
{
    return chat_normalize_role($role) === 'resident';
}

function chat_get_user_role(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();

    return $role === false ? null : (string)$role;
}

function chat_user_is_operator(PDO $pdo, int $userId): bool
{
    $role = chat_get_user_role($pdo, $userId);
    if ($role === null || chat_is_resident_role($role)) {
        return false;
    }

    return require_permission('access_chat', $role);
}

function chat_get_primary_operator_id(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT id, role FROM users ORDER BY
        CASE role
            WHEN 'admin' THEN 0
            WHEN 'barangay-officials' THEN 1
            WHEN 'barangay-kagawad' THEN 2
            WHEN 'barangay-tanod' THEN 3
            ELSE 4
        END,
        id ASC");

    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $row) {
        $candidateId = (int)($row['id'] ?? 0);
        $candidateRole = (string)($row['role'] ?? '');

        if ($candidateId > 0 && !chat_is_resident_role($candidateRole) && require_permission('access_chat', $candidateRole)) {
            return $candidateId;
        }
    }

    return 0;
}

if (!is_logged_in()) {
    chat_json_error(401, 'Authentication required.');
}

$chat_rate_limit = RateLimiter::checkRateLimit('chat_api', RateLimiter::getClientIP());
if (!$chat_rate_limit['allowed']) {
    $retry_after = (int)($chat_rate_limit['lockout_remaining'] ?? 60);
    header('Retry-After: ' . $retry_after);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too Many Requests',
        'message' => $chat_rate_limit['message'] ?? 'Rate limit exceeded. Please try again later.',
        'retry_after' => $retry_after,
    ]);
    exit;
}

RateLimiter::recordAttempt('chat_api', RateLimiter::getClientIP());

$user_id = (int)($_SESSION['user_id'] ?? 0);
$session_role = chat_normalize_role($_SESSION['role'] ?? '');
$is_resident_session = ($session_role === 'resident');
$has_operator_access = (!$is_resident_session && require_permission('access_chat'));

$response = ['success' => false, 'error' => 'Invalid request.'];
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate()) {
        chat_json_error(403, 'Invalid security token.', 'csrf_token');
    }

    if ($action === 'send_message') {
        $message_text = trim((string)($_POST['message'] ?? ''));
        if ($message_text === '') {
            chat_json_error(400, 'Missing message or recipient.');
        }

        $message_text = trim(htmlspecialchars($message_text));
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);

        if ($is_resident_session) {
            if ($receiver_id <= 0) {
                $receiver_id = chat_get_primary_operator_id($pdo);
            }

            if ($receiver_id <= 0 || !chat_user_is_operator($pdo, $receiver_id)) {
                chat_json_error(403, 'Forbidden', 'access_chat');
            }
        } else {
            if (!$has_operator_access) {
                chat_json_error(403, 'Forbidden', 'access_chat');
            }

            if ($receiver_id <= 0) {
                chat_json_error(400, 'Missing message or recipient.');
            }

            $receiver_role = chat_get_user_role($pdo, $receiver_id);
            if (!chat_is_resident_role($receiver_role)) {
                chat_json_error(400, 'Invalid recipient.');
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $receiver_id, $message_text]);
            $response = ['success' => true, 'message' => 'Message sent.'];
        } catch (PDOException $e) {
            error_log('chat send_message database error: ' . $e->getMessage());
            chat_json_error(500, 'Database error while sending message.');
        }
    } elseif ($action === 'mark_as_read') {
        $sender_id = (int)($_POST['sender_id'] ?? 0);
        if ($sender_id <= 0) {
            chat_json_error(400, 'Invalid sender.');
        }

        if ($is_resident_session) {
            if (!chat_user_is_operator($pdo, $sender_id)) {
                chat_json_error(400, 'Invalid sender.');
            }
        } else {
            if (!$has_operator_access) {
                chat_json_error(403, 'Forbidden', 'access_chat');
            }

            $sender_role = chat_get_user_role($pdo, $sender_id);
            if (!chat_is_resident_role($sender_role)) {
                chat_json_error(400, 'Invalid sender.');
            }
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE chat_messages
                 SET is_read = 1
                 WHERE sender_id = ?
                   AND receiver_id = ?
                   AND is_read = 0"
            );
            $stmt->execute([$sender_id, $user_id]);
            $response = ['success' => true, 'marked' => (int)$stmt->rowCount()];
        } catch (PDOException $e) {
            error_log('chat mark_as_read database error: ' . $e->getMessage());
            chat_json_error(500, 'Database error while updating messages.');
        }
    } else {
        chat_json_error(400, 'Invalid request.');
    }
} elseif ($requestMethod === 'GET') {
    $action = (string)($_GET['action'] ?? '');

    if ($action === 'get_messages') {
        $partner_id = (int)($_GET['partner_id'] ?? 0);

        if ($is_resident_session && $partner_id <= 0) {
            $partner_id = chat_get_primary_operator_id($pdo);
        }

        if ($partner_id <= 0) {
            chat_json_error(400, 'Invalid partner.');
        }

        try {
            if ($is_resident_session) {
                if (!chat_user_is_operator($pdo, $partner_id)) {
                    chat_json_error(403, 'Forbidden', 'access_chat');
                }
            } else {
                if (!$has_operator_access) {
                    chat_json_error(403, 'Forbidden', 'access_chat');
                }

                $partner_role = chat_get_user_role($pdo, $partner_id);
                if (!chat_is_resident_role($partner_role)) {
                    chat_json_error(400, 'Invalid partner.');
                }
            }

            $mark_stmt = $pdo->prepare(
                "UPDATE chat_messages
                 SET is_read = 1
                 WHERE sender_id = ?
                   AND receiver_id = ?
                   AND is_read = 0"
            );
            $mark_stmt->execute([$partner_id, $user_id]);

            $stmt = $pdo->prepare(
                "SELECT cm.*, sender.role AS sender_role, sender.fullname AS sender_name
                 FROM chat_messages cm
                 LEFT JOIN users sender ON sender.id = cm.sender_id
                 WHERE (
                        cm.sender_id = ? AND cm.receiver_id = ?
                 ) OR (
                        cm.sender_id = ? AND cm.receiver_id = ?
                 )
                 ORDER BY cm.sent_at ASC"
            );
            $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'messages' => $messages];
        } catch (PDOException $e) {
            error_log('chat get_messages database error: ' . $e->getMessage());
            chat_json_error(500, 'Database error while fetching messages.');
        }
    } elseif ($action === 'get_conversations') {
        if (!$has_operator_access) {
            chat_json_error(403, 'Forbidden', 'access_chat');
        }

        try {
            $sql = "
                SELECT u.id AS user_id,
                       u.fullname,
                       latest_msg.message,
                       latest_msg.sent_at,
                       COALESCE(unread.unread_count, 0) AS unread_count
                FROM users u
                LEFT JOIN (
                    SELECT thread.resident_id, cm.message, cm.sent_at
                    FROM (
                        SELECT
                            CASE
                                WHEN sender_id = ? THEN receiver_id
                                ELSE sender_id
                            END AS resident_id,
                            MAX(id) AS latest_message_id
                        FROM chat_messages
                        WHERE sender_id = ? OR receiver_id = ?
                        GROUP BY resident_id
                    ) thread
                    INNER JOIN chat_messages cm ON cm.id = thread.latest_message_id
                ) latest_msg ON latest_msg.resident_id = u.id
                LEFT JOIN (
                    SELECT sender_id AS resident_id, COUNT(*) AS unread_count
                    FROM chat_messages
                    WHERE receiver_id = ?
                      AND is_read = 0
                    GROUP BY sender_id
                ) unread ON unread.resident_id = u.id
                WHERE u.role = 'resident'
                ORDER BY (latest_msg.sent_at IS NULL) ASC, latest_msg.sent_at DESC, u.fullname ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'conversations' => $conversations];
        } catch (PDOException $e) {
            error_log('chat get_conversations database error: ' . $e->getMessage());
            chat_json_error(500, 'Database error while fetching conversations.');
        }
    } elseif ($action === 'get_unread_count') {
        try {
            if ($is_resident_session) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM chat_messages cm
                     INNER JOIN users u ON u.id = cm.sender_id
                     WHERE cm.receiver_id = ?
                       AND cm.is_read = 0
                       AND u.role <> 'resident'"
                );
                $stmt->execute([$user_id]);
            } else {
                if (!$has_operator_access) {
                    chat_json_error(403, 'Forbidden', 'access_chat');
                }

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM chat_messages cm
                     INNER JOIN users u ON u.id = cm.sender_id
                     WHERE cm.receiver_id = ?
                       AND cm.is_read = 0
                       AND u.role = 'resident'"
                );
                $stmt->execute([$user_id]);
            }

            $count = (int)$stmt->fetchColumn();
            $response = ['success' => true, 'unread' => $count];
        } catch (PDOException $e) {
            error_log('chat get_unread_count database error: ' . $e->getMessage());
            chat_json_error(500, 'Database error while fetching unread count.');
        }
    } else {
        chat_json_error(400, 'Invalid request.');
    }
} else {
    chat_json_error(405, 'Method not allowed.');
}

echo json_encode($response);