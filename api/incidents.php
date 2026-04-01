<?php
require_once '../config/init.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/storage_manager.php';

// Apply security headers for API endpoints
apply_page_security_headers('api');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$response = ['error' => 'Invalid request.'];
$user_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_incident') {
    // Check API rate limiting
    $api_rate_limit = RateLimiter::checkRateLimit('api_calls', RateLimiter::getClientIP());
    if (!$api_rate_limit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'error' => 'Too Many Requests',
            'message' => $api_rate_limit['message'],
            'retry_after' => $api_rate_limit['lockout_remaining'] ?? 60
        ]);
        exit;
    }
    
    // Record API call attempt
    RateLimiter::recordAttempt('api_calls', RateLimiter::getClientIP());

    if (!csrf_validate()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }
    
    require_role('resident');

    if ($_POST['action'] === 'report_incident') {
        $type = trim(htmlspecialchars($_POST['type'] ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
        
        // Generate location string from coordinates
        $location = 'Coordinates: ' . number_format($latitude, 6) . ', ' . number_format($longitude, 6);
        
        $media_path = null;
        
        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['media'];
            
            // Enhanced file validation using InputValidator
            $file_validation = validate_input($file, 'file', [
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime']
            ]);
            
            if (!$file_validation['valid']) {
                $response = ['error' => 'File validation failed: ' . implode(' ', $file_validation['errors'])];
                echo json_encode($response);
                exit;
            }
            
            $validated_file = $file_validation['sanitized'];
            
            $storage_result = StorageManager::saveUploadedFile($validated_file, 'admin/images/incidents', 'incident_');
            if ($storage_result['success']) {
                $media_path = (string) $storage_result['path'];
            } else {
                $response = ['error' => (string) ($storage_result['error'] ?? 'Failed to store uploaded file.')];
                echo json_encode($response);
                exit;
            }
        }

        if (!empty($type) && !empty($description) && $latitude !== false && $longitude !== false) {
            try {
                $sql = "INSERT INTO incidents (resident_user_id, type, location, latitude, longitude, description, media_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $type, $location, $latitude, $longitude, $description, $media_path]);
                $response = ['success' => true, 'message' => 'Incident reported successfully.'];
            } catch (PDOException $e) {
                $response = ['error' => 'Database error while reporting incident.'];
            }
        } else {
            $response = ['error' => 'Required fields are missing or invalid. Please select a location on the map.'];
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // Check API rate limiting for GET requests
    $api_rate_limit = RateLimiter::checkRateLimit('api_calls', RateLimiter::getClientIP());
    if (!$api_rate_limit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'error' => 'Too Many Requests',
            'message' => $api_rate_limit['message'],
            'retry_after' => $api_rate_limit['lockout_remaining'] ?? 60
        ]);
        exit;
    }
    
    // Record API call attempt
    RateLimiter::recordAttempt('api_calls', RateLimiter::getClientIP());
    
    if ($_GET['action'] === 'get_my_reports') {
        require_role('resident');
        try {
            $sql = "SELECT * FROM incidents WHERE resident_user_id = ? ORDER BY reported_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'reports' => $reports];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error while fetching reports.'];
        }
    } elseif ($_GET['action'] === 'get_all_reports' && ($_SESSION['role'] === 'admin')) {
        try {
            $sql = "SELECT i.*, u.fullname AS resident_name 
                    FROM incidents i
                    JOIN users u ON i.resident_user_id = u.id
                    ORDER BY i.reported_at DESC";
            $stmt = $pdo->query($sql);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'reports' => $reports];
        } catch (PDOException $e) {
            $response = ['error' => 'Database error while fetching all reports.'];
        }
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_report_status') {
    if (!csrf_validate()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }

    if ($_SESSION['role'] === 'admin') {
        $report_id = intval($_POST['report_id'] ?? 0);
        $status = trim(htmlspecialchars($_POST['status'] ?? ''));
        $allowed_statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

        if ($report_id > 0 && in_array($status, $allowed_statuses)) {
            try {
                $sql = "UPDATE incidents SET status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$status, $report_id]);
                $response = ['success' => true, 'message' => 'Report status updated successfully.'];
            } catch (PDOException $e) {
                $response = ['error' => 'Database error while updating status.'];
            }
        } else {
            $response = ['error' => 'Invalid report ID or status.'];
        }
    } else {
        $response = ['error' => 'Unauthorized.'];
    }
}

echo json_encode($response); 