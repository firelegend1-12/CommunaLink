<?php
header('Content-Type: application/json');

require_once '../config/init.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/storage_manager.php';
require_once '../includes/permission_checker.php';

// Apply security headers for API endpoints
apply_page_security_headers('api');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function incidents_json_error(int $statusCode, string $error, ?string $requiredPermission = null): void
{
    http_response_code($statusCode);

    $payload = [
        'success' => false,
        'error' => $error,
    ];

    if ($requiredPermission !== null && $requiredPermission !== '') {
        $payload['required_permission'] = $requiredPermission;
    }

    echo json_encode($payload);
    exit;
}

if (!is_logged_in()) {
    incidents_json_error(401, 'Authentication required.');
}

$api_rate_limit = RateLimiter::checkRateLimit('api_calls', RateLimiter::getClientIP());
if (!$api_rate_limit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too Many Requests',
        'message' => $api_rate_limit['message'] ?? 'Rate limit exceeded. Please try again later.',
        'retry_after' => $api_rate_limit['lockout_remaining'] ?? 60,
    ]);
    exit;
}

RateLimiter::recordAttempt('api_calls', RateLimiter::getClientIP());

$response = ['success' => false, 'error' => 'Invalid request.'];
$user_id = (int)($_SESSION['user_id'] ?? 0);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'report_incident') {
        if (!csrf_validate()) {
            incidents_json_error(403, 'Invalid security token.', 'csrf_token');
        }

        require_permission_or_json('report_incidents', 403, 'Forbidden');

        $type = trim((string)($_POST['type'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

        if ($type === '' || $description === '' || $latitude === false || $longitude === false) {
            incidents_json_error(400, 'Required fields are missing or invalid. Please select a location on the map.');
        }

        $location = 'Coordinates: ' . number_format((float)$latitude, 6, '.', '') . ', ' . number_format((float)$longitude, 6, '.', '');
        $media_path = null;

        if (isset($_FILES['media']) && is_array($_FILES['media']) && (int)($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['media'];

            $file_validation = validate_input($file, 'file', [
                'allowed_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'image/heic',
                    'image/heif',
                    'video/mp4',
                    'video/quicktime',
                    'video/webm',
                    'video/3gpp'
                ]
            ]);

            if (!$file_validation['valid']) {
                incidents_json_error(400, 'File validation failed: ' . implode(' ', $file_validation['errors']));
            }

            $validated_file = $file_validation['sanitized'];
            $storage_result = StorageManager::saveUploadedFile($validated_file, 'uploads/incidents', 'incident_');

            if (!$storage_result['success']) {
                incidents_json_error(500, (string)($storage_result['error'] ?? 'Failed to store uploaded file.'));
            }

            $media_path = (string)$storage_result['path'];
        }

        try {
            $sql = "INSERT INTO incidents (resident_user_id, type, location, latitude, longitude, description, media_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $type, $location, $latitude, $longitude, $description, $media_path]);

            $response = ['success' => true, 'message' => 'Incident reported successfully.'];
        } catch (PDOException $e) {
            error_log('incidents report_incident database error: ' . $e->getMessage());
            incidents_json_error(500, 'Database error while reporting incident.');
        }
    } elseif ($action === 'update_report_status') {
        if (!csrf_validate()) {
            incidents_json_error(403, 'Invalid security token.', 'csrf_token');
        }

        require_permission_or_json('manage_incidents', 403, 'Forbidden');

        $report_id = (int)($_POST['report_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        $allowed_statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

        if ($report_id <= 0 || !in_array($status, $allowed_statuses, true)) {
            incidents_json_error(400, 'Invalid report ID or status.');
        }

        try {
            $sql = "UPDATE incidents SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $report_id]);

            $response = ['success' => true, 'message' => 'Report status updated successfully.'];
        } catch (PDOException $e) {
            error_log('incidents update_report_status database error: ' . $e->getMessage());
            incidents_json_error(500, 'Database error while updating status.');
        }
    } else {
        incidents_json_error(400, 'Invalid request.');
    }
} elseif ($requestMethod === 'GET') {
    $action = (string)($_GET['action'] ?? '');

    if ($action === 'get_my_reports') {
        require_permission_or_json('report_incidents', 403, 'Forbidden');

        try {
            $sql = "SELECT * FROM incidents WHERE resident_user_id = ? ORDER BY reported_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'reports' => $reports];
        } catch (PDOException $e) {
            error_log('incidents get_my_reports database error: ' . $e->getMessage());
            incidents_json_error(500, 'Database error while fetching reports.');
        }
    } elseif ($action === 'get_all_reports') {
        require_permission_or_json('manage_incidents', 403, 'Forbidden');

        try {
            $sql = "SELECT i.*, u.fullname AS resident_name
                    FROM incidents i
                    JOIN users u ON i.resident_user_id = u.id
                    ORDER BY i.reported_at DESC";
            $stmt = $pdo->query($sql);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'reports' => $reports];
        } catch (PDOException $e) {
            error_log('incidents get_all_reports database error: ' . $e->getMessage());
            incidents_json_error(500, 'Database error while fetching all reports.');
        }
    } else {
        incidents_json_error(400, 'Invalid request.');
    }
} else {
    incidents_json_error(405, 'Method not allowed.');
}

echo json_encode($response);