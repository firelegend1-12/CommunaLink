<?php
header('Content-Type: application/json');
// Use lightweight database-only bootstrap — not init.php, which runs all schema migrations on every poll
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

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
                $response = ['error' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['error' => 'Missing message or recipient.'];
        }

    } elseif ($_POST['action'] === 'mark_as_read' && isset($_POST['sender_id'])) {
        // Mark all messages from a specific resident as read
        $sender_id = intval($_POST['sender_id']);
        $admin_id  = getAdminId($pdo);
        try {
            $stmt = $pdo->prepare(
                "UPDATE chat_messages SET is_read = 1
                 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
            );
            $stmt->execute([$sender_id, $admin_id]);
            $response = ['success' => true, 'marked' => $stmt->rowCount()];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_messages' && isset($_GET['partner_id'])) {
        $partner_id = intval($_GET['partner_id']);
        try {
            if ($role === 'resident') {
                $admin_id = getAdminId($pdo);
                // Resident always chats with the admin; ignore partner_id for safety
                $stmt = $pdo->prepare(
                    "SELECT * FROM chat_messages
                     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                     ORDER BY sent_at ASC"
                );
                $stmt->execute([$user_id, $admin_id, $admin_id, $user_id]);
            } else {
                // Admin: chat with a specific resident
                $stmt = $pdo->prepare(
                    "SELECT * FROM chat_messages
                     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                     ORDER BY sent_at ASC"
                );
                $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
            }
            $messages  = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response  = ['success' => true, 'messages' => $messages];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }

    } elseif ($_GET['action'] === 'get_conversations' && $role === 'admin') {
        $admin_id = getAdminId($pdo);
        try {
            $sql = "
            SELECT u.id AS user_id, u.fullname, m.message, m.sent_at
            FROM users u
            LEFT JOIN (
                SELECT
                    CASE
                        WHEN sender_id = ? THEN receiver_id
                        ELSE sender_id
                    END AS resident_id,
                    message,
                    sent_at
                FROM chat_messages
                WHERE sender_id = ? OR receiver_id = ?
                ORDER BY sent_at DESC
            ) m ON u.id = m.resident_id
            WHERE u.role = 'resident'
            GROUP BY u.id
            ORDER BY MAX(m.sent_at) DESC, u.fullname ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$admin_id, $admin_id, $admin_id]);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'conversations' => $conversations];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }
    } elseif ($_GET['action'] === 'get_unread_count' && $role === 'admin') {
        $admin_id = getAdminId($pdo);
        try {
            // Count all unread messages directed at any admin user
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM chat_messages
                 WHERE receiver_id = ? AND is_read = 0"
            );
            $stmt->execute([$admin_id]);
            $count = (int)$stmt->fetchColumn();
            $response = ['success' => true, 'unread' => $count];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }
    }
}

echo json_encode($response); 