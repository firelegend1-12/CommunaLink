<?php
header('Content-Type: application/json');
require_once '../config/init.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

// All resident messages will be directed to the first admin account.
// This simplifies the "group chat" logic for admins.
// We need to dynamically find the admin user ID since it might not be 1
define('ADMIN_CHAT_TARGET_ID', 4); // Updated to match actual admin user ID

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$response = ['error' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message' && !empty($_POST['message'])) {
        $message_text = trim(htmlspecialchars($_POST['message']));
        
        $receiver_id = ($role === 'resident') ? ADMIN_CHAT_TARGET_ID : intval($_POST['receiver_id']);
        
        if ($message_text && $receiver_id) {
            try {
                $sql = "INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $receiver_id, $message_text]);
                $response = ['success' => true, 'message' => 'Message sent.'];
            } catch (PDOException $e) {
                $response = ['error' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_messages' && isset($_GET['partner_id'])) {
        $partner_id = intval($_GET['partner_id']);
        try {
            if ($role === 'resident') {
                $admin_id = ADMIN_CHAT_TARGET_ID;
                $sql = "SELECT * FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY sent_at ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $admin_id, $admin_id, $user_id]);
            } else { // Admin
                $sql = "SELECT * FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY sent_at ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $partner_id, $partner_id, $user_id]);
            }
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'messages' => $messages];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }
    } elseif ($_GET['action'] === 'get_conversations' && ($role === 'admin')) {
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
            $stmt->execute([ADMIN_CHAT_TARGET_ID, ADMIN_CHAT_TARGET_ID, ADMIN_CHAT_TARGET_ID]);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents('C:/xampp/htdocs/barangay/debug_conversations.json', json_encode($conversations, JSON_PRETTY_PRINT));
            $response = ['success' => true, 'conversations' => $conversations];
        } catch (PDOException $e) {
            file_put_contents('C:/xampp/htdocs/barangay/debug_conversations.json', 'SQL Error: ' . $e->getMessage());
            $response = ['error' => 'Database error: ' . $e->getMessage()];
        }
    }
}

echo json_encode($response); 