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

function resident_history_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $cache_key = $table . '.' . $column;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        $cache[$cache_key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Column check failed for {$cache_key}: " . $e->getMessage());
        $cache[$cache_key] = false;
    }

    return $cache[$cache_key];
}

function resident_history_activity_expression(PDO $pdo, string $table, array $candidateColumns): array
{
    $available = [];
    foreach ($candidateColumns as $column) {
        if (resident_history_table_has_column($pdo, $table, $column)) {
            $available[] = "`{$column}`";
        }
    }

    if (empty($available)) {
        return ['NULL', false];
    }

    if (count($available) === 1) {
        return [$available[0], true];
    }

    $wrapped = array_map(static function ($column) {
        return "COALESCE({$column}, '1000-01-01 00:00:00')";
    }, $available);

    return ['GREATEST(' . implode(', ', $wrapped) . ')', true];
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

    [$requestActivityExpr, $hasRequestActivity] = resident_history_activity_expression(
        $pdo,
        'document_requests',
        ['updated_at', 'processed_date', 'payment_date', 'date_requested']
    );

    [$incidentActivityExpr, $hasIncidentActivity] = resident_history_activity_expression(
        $pdo,
        'incidents',
        ['updated_at', 'reported_at']
    );

    // 2. Fetch Recent Document Requests (Last 5)
    $stmt = $pdo->prepare("SELECT id, 'document' AS request_type, document_type, status, payment_status, payment_date, or_number, date_requested as requested_at, {$requestActivityExpr} AS activity_at
                           FROM document_requests 
                           WHERE resident_id = ? 
                           ORDER BY " . ($hasRequestActivity ? "activity_at DESC, " : '') . "date_requested DESC, id DESC
                           LIMIT 5");
    $stmt->execute([$id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requests as &$request_row) {
        $request_row['status'] = get_request_display_status(
            $request_row['status'] ?? null,
            $request_row['payment_status'] ?? null,
            document_request_requires_payment($request_row['document_type'] ?? '')
        );
        if (empty($request_row['activity_at'])) {
            $request_row['activity_at'] = $request_row['requested_at'] ?? null;
        }
    }
    unset($request_row);

    // 3. Fetch Recent Incident Reports (Last 5)
    // Note: incidents table uses resident_user_id (linking to users.id)
    $incidents = [];
    if (!empty($resident['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, type, status, reported_at, {$incidentActivityExpr} AS activity_at
                               FROM incidents 
                               WHERE resident_user_id = ? 
                               ORDER BY " . ($hasIncidentActivity ? "activity_at DESC, " : '') . "reported_at DESC, id DESC
                               LIMIT 5");
        $stmt->execute([$resident['user_id']]);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($incidents as &$incident_row) {
            $incident_row['status'] = normalize_incident_status_display($incident_row['status'] ?? null);
            if (empty($incident_row['activity_at'])) {
                $incident_row['activity_at'] = $incident_row['reported_at'] ?? null;
            }
        }
        unset($incident_row);
    }

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
