<?php
/**
 * AJAX Handler: Get Incident Details
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
    echo json_encode(['success' => false, 'error' => 'Invalid Incident ID']);
    exit;
}

try {
    // 1. Fetch Incident Info + Reporter Info
    // Assuming incidents table has: id, type, details, location, status, reported_at, image_path, resident_user_id
    $stmt = $pdo->prepare("SELECT i.*, u.fullname AS reporter_name, u.email AS reporter_email, r.contact_no AS reporter_contact
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

    // 2. Format details (if JSON)
    if (isset($incident['details']) && is_string($incident['details'])) {
        $decoded = json_decode($incident['details'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $incident['parsed_details'] = $decoded;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $incident
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
