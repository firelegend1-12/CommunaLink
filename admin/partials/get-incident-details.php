<?php
/**
 * AJAX Handler: Get Incident Details
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/storage_manager.php';

header('Content-Type: application/json');

// Check if user is logged in as an authorized official
if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Incident ID']);
    exit;
}

try {
    // 1. Fetch Incident Info + Reporter Info
    // Incidents table: id, type, description, location, media_path, status, reported_at, admin_remarks, resident_user_id
    $stmt = $pdo->prepare("SELECT i.*, i.media_path AS image_path, u.fullname AS reporter_name, u.email AS reporter_email, r.contact_no AS reporter_contact, r.id AS resident_id
                           FROM incidents i
                           LEFT JOIN users u ON i.resident_user_id = u.id
                           LEFT JOIN residents r ON u.id = r.user_id
                           WHERE i.id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        echo json_encode(['success' => false, 'error' => 'Incident report not found']);
        exit;
    }

    $imagePath = (string)($incident['image_path'] ?? '');
    $incident['image_url'] = $imagePath !== '' ? StorageManager::resolvePublicUrl($imagePath) : '';

    echo json_encode([
        'success' => true,
        'data' => $incident
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
