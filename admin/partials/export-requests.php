<?php
/**
 * CSV Export for Monitoring of Requests
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';

require_login();
require_permission_or_redirect('view_monitoring_requests', '../pages/monitoring-of-request.php');

// Get filter parameters
$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';
$time_from = isset($_GET['time_from']) ? sanitize_input($_GET['time_from']) : '';
$time_to = isset($_GET['time_to']) ? sanitize_input($_GET['time_to']) : '';
$date_mode = isset($_GET['date_mode']) ? sanitize_input($_GET['date_mode']) : 'request';
$payment_filter = isset($_GET['payment']) ? sanitize_input($_GET['payment']) : '';
$sort_by = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'date_requested';
$sort_dir = isset($_GET['dir']) ? sanitize_input($_GET['dir']) : 'DESC';

$valid_statuses = ['Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled'];
$valid_payments = ['Paid', 'Unpaid'];
$valid_sort_columns = ['date_requested', 'status', 'payment_status', 'first_name', 'last_name'];
$valid_date_modes = ['request', 'payment'];

// Validate sort parameters
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'date_requested';
}
if (!in_array($sort_dir, ['ASC', 'DESC'])) {
    $sort_dir = 'DESC';
}
if (!in_array($date_mode, $valid_date_modes)) {
    $date_mode = 'request';
}
if (!preg_match('/^\d{2}:\d{2}$/', $time_from)) {
    $time_from = '';
}
if (!preg_match('/^\d{2}:\d{2}$/', $time_to)) {
    $time_to = '';
}

$date_column = ($date_mode === 'payment') ? 'payment_date' : 'date_requested';
$from_suffix = !empty($time_from) ? ($time_from . ':00') : '00:00:00';
$to_suffix = !empty($time_to) ? ($time_to . ':59') : '23:59:59';

// Build WHERE conditions
$where_parts = [];
$exec_params = [];

if (!empty($search_query)) {
    $where_parts[] = "CONCAT(first_name, ' ', last_name) LIKE ?";
    $exec_params[] = "%{$search_query}%";
}

if (!empty($status_filter) && in_array($status_filter, $valid_statuses)) {
    $where_parts[] = "status = ?";
    $exec_params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_parts[] = "{$date_column} >= ?";
    $exec_params[] = "{$date_from} {$from_suffix}";
}

if (!empty($date_to)) {
    $where_parts[] = "{$date_column} <= ?";
    $exec_params[] = "{$date_to} {$to_suffix}";
}

if (!empty($payment_filter) && in_array($payment_filter, $valid_payments)) {
    $where_parts[] = "payment_status = ?";
    $exec_params[] = $payment_filter;
}

$where_clause = !empty($where_parts) ? " WHERE " . implode(" AND ", $where_parts) : "";

// Base UNION query
$union_query = "(
    SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.payment_date,
    CASE
        WHEN LOWER(TRIM(COALESCE(dr.document_type, ''))) IN ('certificate of indigency', 'certificate of indigency (special)')
            AND UPPER(COALESCE(dr.status, '')) IN ('APPROVED', 'PROCESSING', 'READY FOR PICKUP', 'COMPLETED')
            AND UPPER(COALESCE(dr.status, '')) NOT IN ('REJECTED', 'CANCELLED', 'CANCELED')
        THEN 'Completed'
        WHEN dr.payment_status = 'Paid' AND UPPER(COALESCE(dr.status, '')) NOT IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN 'Completed'
        WHEN dr.status IN ('Processing', 'Ready for Pickup') THEN 'Approved'
        ELSE dr.status
    END AS status,
    'document' as request_type, dr.or_number, dr.payment_status 
    FROM document_requests dr 
    LEFT JOIN residents r ON dr.resident_id = r.id
) UNION ALL (
    SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.payment_date,
    CASE
        WHEN bt.payment_status = 'Paid' AND UPPER(COALESCE(bt.status, '')) NOT IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN 'Completed'
        WHEN bt.status IN ('Processing', 'Ready for Pickup') THEN 'Approved'
        ELSE bt.status
    END AS status,
    'business' as request_type, 
    bt.or_number, bt.payment_status 
    FROM business_transactions bt 
    LEFT JOIN residents r ON bt.resident_id = r.id
)";

// Apply type filter
if ($type_filter === 'document') {
    $union_query = "(
        SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.payment_date,
        CASE
            WHEN LOWER(TRIM(COALESCE(dr.document_type, ''))) IN ('certificate of indigency', 'certificate of indigency (special)')
                AND UPPER(COALESCE(dr.status, '')) IN ('APPROVED', 'PROCESSING', 'READY FOR PICKUP', 'COMPLETED')
                AND UPPER(COALESCE(dr.status, '')) NOT IN ('REJECTED', 'CANCELLED', 'CANCELED')
            THEN 'Completed'
            WHEN dr.payment_status = 'Paid' AND UPPER(COALESCE(dr.status, '')) NOT IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN 'Completed'
            WHEN dr.status IN ('Processing', 'Ready for Pickup') THEN 'Approved'
            ELSE dr.status
        END AS status,
        'document' as request_type, dr.or_number, dr.payment_status 
        FROM document_requests dr 
        LEFT JOIN residents r ON dr.resident_id = r.id
    )";
} else if ($type_filter === 'business') {
    $union_query = "(
        SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.payment_date,
        CASE
            WHEN bt.payment_status = 'Paid' AND UPPER(COALESCE(bt.status, '')) NOT IN ('REJECTED', 'CANCELLED', 'CANCELED') THEN 'Completed'
            WHEN bt.status IN ('Processing', 'Ready for Pickup') THEN 'Approved'
            ELSE bt.status
        END AS status,
        'business' as request_type, 
        bt.or_number, bt.payment_status 
        FROM business_transactions bt 
        LEFT JOIN residents r ON bt.resident_id = r.id
    )";
}

try {
    // Fetch all matching records (no pagination for export)

    $final_sql = "SELECT * FROM ({$union_query}) AS combined {$where_clause} ORDER BY {$sort_by} {$sort_dir}";

    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($exec_params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="requests_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'ID',
        'Name',
        'Document Type',
        'Date Requested',
        'Status',
        'Payment Status',
        'O.R. Number',
        'Type'
    ]);
    
    // Add data rows
    foreach ($requests as $req) {
        $display_status = get_request_display_status(
            $req['status'] ?? null,
            $req['payment_status'] ?? null,
            $req['request_type'] === 'document'
                ? document_request_requires_payment($req['document_type'] ?? '')
                : true
        );
        fputcsv($output, [
            $req['id'],
            $req['first_name'] . ' ' . $req['last_name'],
            $req['document_type'],
            date('M. d, Y h:i A', strtotime($req['date_requested'])),
            $display_status,
            $req['payment_status'],
            $req['or_number'] ?? 'N/A',
            ucfirst($req['request_type'])
        ]);
    }
    
    fclose($output);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
