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

// Dynamically resolve the admin's user ID — never hardcode it
function getAdminId($pdo) {
    static $admin_id = null;
    if ($admin_id === null) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $admin_id = $admin ? (int)$admin['id'] : 1;
    }
    return $admin_id;
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
            $receiver_id = getAdminId($pdo);
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
                   AND receiver_id IN (SELECT id FROM users WHERE role = 'admin')
                   AND is_read = 0"
            );
            $stmt->execute([$sender_id]);
            $response = ['success' => true, 'marked' => $stmt->rowCount()];
        } catch (PDOException $e) {
            error_log('chat mark_as_read database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while updating messages.'];
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_messages' && isset($_GET['partner_id'])) {
        $partner_id = intval($_GET['partner_id']);
        try {
            if ($role === 'resident') {
                // Resident sees full shared admin-history thread.
                $stmt = $pdo->prepare(
                    "SELECT * FROM chat_messages
                     WHERE (
                            sender_id = ?
                        AND receiver_id IN (SELECT id FROM users WHERE role = 'admin')
                     ) OR (
                            receiver_id = ?
                        AND sender_id IN (SELECT id FROM users WHERE role = 'admin')
                     )
                     ORDER BY sent_at ASC"
                );
                $stmt->execute([$user_id, $user_id]);
            } else {
                // Admin sees shared admin-history thread for the resident.
                $stmt = $pdo->prepare(
                    "SELECT * FROM chat_messages
                     WHERE (
                            sender_id IN (SELECT id FROM users WHERE role = 'admin')
                        AND receiver_id = ?
                     ) OR (
                            receiver_id IN (SELECT id FROM users WHERE role = 'admin')
                        AND sender_id = ?
                     )
                     ORDER BY sent_at ASC"
                );
                $stmt->execute([$partner_id, $partner_id]);
            }
            $messages  = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response  = ['success' => true, 'messages' => $messages];
        } catch (PDOException $e) {
            error_log('chat get_messages database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching messages.'];
        }

    } elseif ($_GET['action'] === 'get_conversations' && $role === 'admin') {
        try {
            $sql = "
            SELECT u.id AS user_id, u.fullname, m.message, m.sent_at
            FROM users u
            LEFT JOIN (
                SELECT latest.resident_id, cm.message, cm.sent_at
                FROM (
                    SELECT
                        CASE
                            WHEN sender_id IN (SELECT id FROM users WHERE role = 'admin') THEN receiver_id
                            ELSE sender_id
                        END AS resident_id,
                        MAX(id) AS latest_message_id
                    FROM chat_messages
                    WHERE sender_id IN (SELECT id FROM users WHERE role = 'admin')
                       OR receiver_id IN (SELECT id FROM users WHERE role = 'admin')
                    GROUP BY CASE
                        WHEN sender_id IN (SELECT id FROM users WHERE role = 'admin') THEN receiver_id
                        ELSE sender_id
                    END
                ) latest
                INNER JOIN chat_messages cm ON cm.id = latest.latest_message_id
            ) m ON u.id = m.resident_id
            WHERE u.role = 'resident'
            ORDER BY (m.sent_at IS NULL) ASC, m.sent_at DESC, u.fullname ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'conversations' => $conversations];
        } catch (PDOException $e) {
            error_log('chat get_conversations database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching conversations.'];
        }
    } elseif ($_GET['action'] === 'get_unread_count' && $role === 'admin') {
        try {
            // Count all unread messages directed at any admin user (shared inbox semantics)
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM chat_messages
                 WHERE receiver_id IN (SELECT id FROM users WHERE role = 'admin')
                   AND is_read = 0"
            );
            $stmt->execute();
            $count = (int)$stmt->fetchColumn();
            $response = ['success' => true, 'unread' => $count];
        } catch (PDOException $e) {
            error_log('chat get_unread_count database error: ' . $e->getMessage());
            $response = ['error' => 'Database error while fetching unread count.'];
        }
    }
}

echo json_encode($response); 