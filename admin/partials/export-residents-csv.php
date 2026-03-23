<?php
/**
 * Export Residents Data to CSV
 */

// Include core files
require_once '../../includes/auth.php';
require_once '../../config/init.php'; // Use init.php to get $pdo and all config
require_once '../../includes/functions.php';

// Ensure user is logged in
require_login();

if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    redirect_to('../pages/residents.php');
}

// Set headers for CSV file download
$filename = "residents_export_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the CSV column headers
fputcsv($output, [
    'ID Number', 
    'Last Name', 
    'First Name', 
    'Middle Initial', 
    'Gender', 
    'Date of Birth', 
    'Age', 
    'Civil Status', 
    'Address', 
    'Email', 
    'Contact Number', 
    'Voter Status',
    'Religion',
    'Citizenship',
    'Place of Birth'
]);

// Fetch all residents from the database using PDO
$sql = "SELECT id_number, last_name, first_name, middle_initial, gender, date_of_birth, age, civil_status, address, email, contact_no, voter_status, religion, citizenship, place_of_birth FROM residents ORDER BY last_name ASC";
$stmt = $pdo->query($sql);

if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format date_of_birth as YYYY-MM-DD if not empty
        if (!empty($row['date_of_birth'])) {
            $row['date_of_birth'] = date('Y-m-d', strtotime($row['date_of_birth']));
        }
        // Force ID Number as text for Excel (preserve leading zeros)
        if (isset($row['id_number'])) {
            $row['id_number'] = "'" . $row['id_number'];
        }
        fputcsv($output, $row);
    }
}

fclose($output);
exit(); 