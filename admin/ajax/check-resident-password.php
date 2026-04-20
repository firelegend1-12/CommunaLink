<?php
/**
 * AJAX endpoint: Check whether provided password matches resident's account password
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Only admins may run this lookup
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$resident_id = isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0;
$password = (string)($_POST['password'] ?? '');

if ($resident_id <= 0 || $password === '') {
    http_response_code(400);
    echo json_encode(['matches' => false, 'error' => 'missing parameters']);
    exit;
}

try {
    // Try to fetch password hash via resident->user relationship
    $stmt = $pdo->prepare('SELECT u.password FROM users u JOIN residents r ON r.user_id = u.id WHERE r.id = ? LIMIT 1');
    $stmt->execute([$resident_id]);
    $hash = (string)($stmt->fetchColumn() ?: '');

    // Fallback: if not found by user_id, try matching by resident email
    if ($hash === '') {
        $stmt2 = $pdo->prepare('SELECT email FROM residents WHERE id = ? LIMIT 1');
        $stmt2->execute([$resident_id]);
        $resEmail = (string)($stmt2->fetchColumn() ?: '');
        if ($resEmail !== '') {
            $stmt3 = $pdo->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
            $stmt3->execute([$resEmail]);
            $hash = (string)($stmt3->fetchColumn() ?: '');
        }
    }

    $matches = false;
    if ($hash !== '') {
        $matches = password_verify($password, $hash);
    }

    echo json_encode(['matches' => $matches]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['matches' => false, 'error' => 'server error']);
    exit;
}
