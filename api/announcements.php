<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

// Allow access only to logged-in residents
if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_latest') {
    try {
        // Fetch the 3 most recent announcements
        $stmt = $pdo->query(
            "SELECT a.title, a.content, a.created_at, u.fullname as author_name 
             FROM announcements a 
             JOIN users u ON a.user_id = u.id 
             ORDER BY a.created_at DESC 
             LIMIT 3"
        );
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'announcements' => $announcements]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database query failed.']);
    }
    exit;
}

// Default response
echo json_encode(['success' => false, 'error' => 'Invalid action.']); 