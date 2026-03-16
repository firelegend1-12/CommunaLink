<?php
/**
 * Export Business Data to CSV
 */

// Include core files
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Ensure user is logged in
require_login();

// Set headers for CSV file download
$filename = "business_records_export_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the CSV column headers
fputcsv($output, [
    'Business ID',
    'Owner Name',
    'Owner ID',
    'Business Name',
    'Business Address',
    'Status',
    'Date Registered'
]);

try {
    $stmt = $pdo->query("SELECT b.id, r.first_name, r.last_name, r.id as owner_id, b.business_name, b.address, b.status, b.date_registered
                         FROM businesses b
                         LEFT JOIN residents r ON b.resident_id = r.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            trim($row['first_name'] . ' ' . $row['last_name']),
            $row['owner_id'],
            $row['business_name'],
            $row['address'],
            $row['status'],
            $row['date_registered']
        ]);
    }
} catch (Exception $e) {
    // Optionally log error, but don't output to CSV
}

// Close the file pointer
fclose($output);
exit(); 