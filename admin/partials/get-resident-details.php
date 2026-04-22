<?php
/**
 * AJAX Handler: Get Resident Details
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';
require_once '../../includes/storage_manager.php';

function admin_resident_profile_image_url(string $storedPath): string
{
    $path = trim($storedPath);
    if ($path === '') {
        return '';
    }

    if (strpos($path, 'gs://') === 0 || preg_match('#^https?://#i', $path) === 1) {
        return StorageManager::resolvePublicUrl($path);
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return '';
    }

    if (stripos($normalized, 'admin/') === 0) {
        return app_url('/' . $normalized);
    }

    return app_url('/admin/' . $normalized);
}

header('Content-Type: application/json');

require_login();
require_permission_or_json('view_residents', 403, 'Forbidden');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
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
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Resident not found']);
        exit;
    }

    $resident['profile_image_url'] = admin_resident_profile_image_url((string)($resident['profile_image_path'] ?? ''));

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
    error_log('get-resident-details failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error while fetching resident details.']);
}
