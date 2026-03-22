<?php
/**
 * Export Incidents to CSV
 */
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check authorization
if (!is_admin_or_official()) {
    header("Location: ../../index.php");
    exit();
}

// Get filters from GET
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'All';
$dateFrom = isset($_GET['from']) ? $_GET['from'] : '';
$dateTo = isset($_GET['to']) ? $_GET['to'] : '';

// Build Query
$query = "SELECT i.*, u.fullname AS reporter_name, u.email AS reporter_email 
          FROM incidents i 
          LEFT JOIN users u ON i.resident_user_id = u.id 
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (u.fullname LIKE ? OR i.type LIKE ? OR i.location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== 'All') {
    $query .= " AND i.status = ?";
    $params[] = $status;
}

if ($dateFrom !== '') {
    $query .= " AND DATE(i.reported_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $query .= " AND DATE(i.reported_at) <= ?";
    $params[] = $dateTo;
}

$query .= " ORDER BY i.reported_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for download
    $filename = "Incidents_Report_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Column Headers
    fputcsv($output, [
        'ID', 
        'Type', 
        'Status', 
        'Reporter', 
        'Email', 
        'Location', 
        'Latitude', 
        'Longitude', 
        'Reported At', 
        'Description', 
        'Admin Remarks'
    ]);

    // Data Rows
    foreach ($results as $row) {
        fputcsv($output, [
            $row['id'],
            $row['type'],
            $row['status'],
            $row['reporter_name'],
            $row['reporter_email'],
            $row['location'],
            $row['latitude'],
            $row['longitude'],
            $row['reported_at'],
            $row['description'],
            $row['admin_remarks']
        ]);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Export failed: " . $e->getMessage());
}
