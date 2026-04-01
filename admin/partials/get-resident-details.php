<?php
/**
 * AJAX Handler: Get Resident Details
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in as an authorized official
if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Resident ID']);
    exit;
}

try {
    // 1. Fetch Basic Resident Info
    $stmt = $pdo->prepare("SELECT r.*, 
                           CASE 
                               WHEN r.id_number IS NOT NULL AND r.id_number != '' THEN r.id_number
                               ELSE CONCAT('BR-', YEAR(r.created_at), '-', LPAD(r.id, 4, '0'))
                           END AS display_id_number,
                           u.email as user_email
                           FROM residents r
                           LEFT JOIN users u ON r.user_id = u.id
                           WHERE r.id = ?");
    $stmt->execute([$id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        echo json_encode(['success' => false, 'error' => 'Resident not found']);
        exit;
    }

    // 2. Fetch Recent Document Requests (Last 5)
    $stmt = $pdo->prepare("SELECT id, document_type, status, date_requested as requested_at 
                           FROM document_requests 
                           WHERE resident_id = ? 
                           ORDER BY date_requested DESC 
                           LIMIT 5");
    $stmt->execute([$id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Recent Incident Reports (Last 5)
    // Note: incidents table uses resident_user_id (linking to users.id)
    $stmt = $pdo->prepare("SELECT id, type, status, reported_at 
                           FROM incidents 
                           WHERE resident_user_id = ? 
                           ORDER BY reported_at DESC 
                           LIMIT 5");
    $stmt->execute([$resident['user_id']]);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Summary Stats
    $stats_stmt = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM document_requests WHERE resident_id = :resident_id) AS total_requests,
        (SELECT COUNT(*) FROM incidents WHERE resident_user_id = :resident_user_id) AS total_incidents");
    $stats_stmt->execute([
        ':resident_id' => $id,
        ':resident_user_id' => $resident['user_id']
    ]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total_requests = (int)($stats['total_requests'] ?? 0);
    $total_incidents = (int)($stats['total_incidents'] ?? 0);

    echo json_encode([
        'success' => true,
        'data' => [
            'profile' => $resident,
            'history' => [
                'requests' => $requests,
                'incidents' => $incidents
            ],
            'stats' => [
                'total_requests' => $total_requests,
                'total_incidents' => $total_incidents
            ]
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
