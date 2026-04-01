<?php
header('Content-Type: application/json');
// Use lightweight database-only bootstrap — not init.php, which runs all schema migrations on every poll
require_once '../config/database.php';
define('AUTH_LIGHTWEIGHT_BOOTSTRAP', true);
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$chat_rate_limit = RateLimiter::checkRateLimit('chat_api', RateLimiter::getClientIP());
if (!$chat_rate_limit['allowed']) {
    $retry_after = (int) ($chat_rate_limit['lockout_remaining'] ?? 60);
    header('Retry-After: ' . $retry_after);
    http_response_code(429);
    echo json_encode([
        'error' => 'Too Many Requests',
        'message' => $chat_rate_limit['message'] ?? 'Rate limit exceeded. Please try again later.',
        'retry_after' => $retry_after
    ]);
    exit;
}

RateLimiter::recordAttempt('chat_api', RateLimiter::getClientIP());

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

function getAdminIds($pdo) {
    static $admin_ids = null;

    if ($admin_ids === null) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $admin_ids = array_values(array_filter(array_map('intval', (array) $rows), function ($id) {
            return $id > 0;
        }));
    }

    return $admin_ids;
}

function getPrimaryAdminId($pdo) {
    $admin_ids = getAdminIds($pdo);
    return !empty($admin_ids) ? (int) $admin_ids[0] : 1;
}

function getAdminIdsForInClause($pdo) {
    $admin_ids = getAdminIds($pdo);
    return !empty($admin_ids) ? $admin_ids : [0];
}

function buildInPlaceholders(array $values) {
    return implode(', ', array_fill(0, count($values), '?'));
}

$response = ['error' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }

    if ($_POST['action'] === 'send_message' && !empty($_POST['message'])) {
        $message_text = trim(htmlspecialchars($_POST['message']));

        if ($role === 'resident') {
            $receiver_id = getPrimaryAdminId($pdo);
        } else {
            $receiver_id = intval($_POST['receiver_id'] ?? 0);
        }

        if ($message_text && $receiver_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $receiver_id, $message_text]);
                $response = ['success' => true, 'message' => 'Message sent.'];
            } catch (PDOException $e) {
                error_log('chat send_message database error: ' . $e->getMessage());
                $response = ['error' => 'Database error while sending message.'];
            }
        } else {
            $response = ['error' => 'Missing message or recipient.'];
        }

    } elseif ($_POST['action'] === 'mark_as_read' && isset($_POST['sender_id'])) {
        // Mark all messages from a specific resident as read
        $sender_id = intval($_POST['sender_id']);
        $admin_ids = getAdminIdsForInClause($pdo);
        $admin_placeholders = buildInPlaceholders($admin_ids);

        if ($role !== 'admin') {
            http_response_code(403);
            $response = ['error' => 'Unauthorized'];
            echo json_encode($response);
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE chat_messages SET is_read = 1
                 WHERE sender_id = ?
                   AND receiver_id IN ({$admin_placeholders})
                   AND is_read = 0"
            );
            $stmt->execute(array_merge([$sender_id], $admin_ids));
            $response = ['success' => true, 'marked' => $stmt->rowCount()];
        } catch (PDOException $e) {
            error_log('chat mark_as_read database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while updating messages.'];
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_messages' && isset($_GET['partner_id'])) {
        $partner_id = intval($_GET['partner_id']);
        $admin_ids = getAdminIdsForInClause($pdo);
        $admin_placeholders = buildInPlaceholders($admin_ids);
        try {
            if ($role === 'resident') {
                // Mark admin -> resident messages as read when resident opens thread.
                $mark_stmt = $pdo->prepare(
                    "UPDATE chat_messages
                     SET is_read = 1
                     WHERE receiver_id = ?
                       AND sender_id IN ({$admin_placeholders})
                       AND is_read = 0"
                );
                $mark_stmt->execute(array_merge([$user_id], $admin_ids));

                // Resident sees full shared admin-history thread.
                $stmt = $pdo->prepare(
                    "SELECT cm.*, sender.role AS sender_role, sender.fullname AS sender_name
                     FROM chat_messages cm
                     LEFT JOIN users sender ON sender.id = cm.sender_id
                     WHERE (
                            cm.sender_id = ?
                        AND cm.receiver_id IN ({$admin_placeholders})
                     ) OR (
                            cm.receiver_id = ?
                        AND cm.sender_id IN ({$admin_placeholders})
                     )
                     ORDER BY cm.sent_at ASC"
                );
                $stmt->execute(array_merge([$user_id], $admin_ids, [$user_id], $admin_ids));
            } else {
                // Mark resident -> admin messages as read when admin opens thread.
                $mark_stmt = $pdo->prepare(
                    "UPDATE chat_messages
                     SET is_read = 1
                     WHERE sender_id = ?
                       AND receiver_id IN ({$admin_placeholders})
                       AND is_read = 0"
                );
                $mark_stmt->execute(array_merge([$partner_id], $admin_ids));

                // Admin sees shared admin-history thread for the resident.
                $stmt = $pdo->prepare(
                    "SELECT cm.*, sender.role AS sender_role, sender.fullname AS sender_name
                     FROM chat_messages cm
                     LEFT JOIN users sender ON sender.id = cm.sender_id
                     WHERE (
                            cm.sender_id IN ({$admin_placeholders})
                        AND cm.receiver_id = ?
                     ) OR (
                            cm.receiver_id IN ({$admin_placeholders})
                        AND cm.sender_id = ?
                     )
                     ORDER BY cm.sent_at ASC"
                );
                $stmt->execute(array_merge($admin_ids, [$partner_id], $admin_ids, [$partner_id]));
            }
            $messages  = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response  = ['success' => true, 'messages' => $messages];
        } catch (PDOException $e) {
            error_log('chat get_messages database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching messages.'];
        }

    } elseif ($_GET['action'] === 'get_conversations' && $role === 'admin') {
        try {
            $admin_ids = getAdminIdsForInClause($pdo);
            $admin_placeholders = buildInPlaceholders($admin_ids);

            $sql = "
            SELECT u.id AS user_id, u.fullname, m.message, m.sent_at, COALESCE(unread.unread_count, 0) AS unread_count
            FROM users u
            LEFT JOIN (
                SELECT latest.resident_id, cm.message, cm.sent_at
                FROM (
                    SELECT
                        CASE
                            WHEN sender_id IN ({$admin_placeholders}) THEN receiver_id
                            ELSE sender_id
                        END AS resident_id,
                        MAX(id) AS latest_message_id
                    FROM chat_messages
                    WHERE sender_id IN ({$admin_placeholders})
                       OR receiver_id IN ({$admin_placeholders})
                    GROUP BY CASE
                        WHEN sender_id IN ({$admin_placeholders}) THEN receiver_id
                        ELSE sender_id
                    END
                ) latest
                INNER JOIN chat_messages cm ON cm.id = latest.latest_message_id
            ) m ON u.id = m.resident_id
                        LEFT JOIN (
                                SELECT sender_id AS resident_id, COUNT(*) AS unread_count
                                FROM chat_messages
                                WHERE receiver_id IN ({$admin_placeholders})
                                    AND is_read = 0
                                GROUP BY sender_id
                        ) unread ON unread.resident_id = u.id
            WHERE u.role = 'resident'
            ORDER BY (m.sent_at IS NULL) ASC, m.sent_at DESC, u.fullname ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($admin_ids, $admin_ids, $admin_ids, $admin_ids, $admin_ids));
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'conversations' => $conversations];
        } catch (PDOException $e) {
            error_log('chat get_conversations database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching conversations.'];
        }
    } elseif ($_GET['action'] === 'get_unread_count' && $role === 'admin') {
        try {
            $admin_ids = getAdminIdsForInClause($pdo);
            $admin_placeholders = buildInPlaceholders($admin_ids);

            // Count all unread messages directed at any admin user (shared inbox semantics)
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM chat_messages
                 WHERE receiver_id IN ({$admin_placeholders})
                   AND is_read = 0"
            );
            $stmt->execute($admin_ids);
            $count = (int)$stmt->fetchColumn();
            $response = ['success' => true, 'unread' => $count];
        } catch (PDOException $e) {
            error_log('chat get_unread_count database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching unread count.'];
        }
    }
}

echo json_encode($response); 