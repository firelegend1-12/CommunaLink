<?php
/**
 * AJAX endpoint: Check resident by full name
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

$fullname = trim((string)($_POST['fullname'] ?? $_GET['fullname'] ?? ''));
if ($fullname === '') {
    http_response_code(400);
    echo json_encode(['found' => false, 'error' => 'fullname required']);
    exit;
}

$normalize = static function (string $name): string {
    $normalized = strtolower(str_replace(['.', ','], ' ', trim($name)));
    return (string)preg_replace('/\s+/', ' ', $normalized);
};

$remove_middle_initial_token = static function (string $name): string {
    $parts = array_values(array_filter(explode(' ', $name), static fn($part) => $part !== ''));
    if (count($parts) <= 2) {
        return $name;
    }
    $filtered = [];
    foreach ($parts as $index => $part) {
        $is_middle_token = $index > 0 && $index < (count($parts) - 1);
        if ($is_middle_token && strlen($part) === 1) {
            continue;
        }
        $filtered[] = $part;
    }
    return implode(' ', $filtered);
};

$normalized_fullname = $normalize($fullname);
$normalized_without_middle_initial = $remove_middle_initial_token($normalized_fullname);

$stmt = $pdo->prepare(
    "SELECT id, email, CONCAT_WS(' ', first_name, IFNULL(middle_initial, ''), last_name) AS full_name
     FROM residents
     WHERE LOWER(TRIM(REPLACE(REPLACE(CONCAT_WS(' ', first_name, NULLIF(middle_initial, ''), last_name), '.', ''), ',', ''))) IN (?, ?)
        OR LOWER(TRIM(REPLACE(REPLACE(CONCAT_WS(' ', first_name, last_name), '.', ''), ',', ''))) IN (?, ?)
     LIMIT 1"
);
$stmt->execute([
    $normalized_fullname,
    $normalized_without_middle_initial,
    $normalized_fullname,
    $normalized_without_middle_initial,
]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo json_encode([
        'found' => true,
        'id' => (int)$row['id'],
        'email' => $row['email'] ?? '',
        'full_name' => trim($row['full_name'] ?? $fullname),
    ]);
    exit;
}

echo json_encode(['found' => false]);
exit;
