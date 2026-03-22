<?php
/**
 * Monitoring of Requests Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Monitoring of Request - CommuniLink";

try {
    // Fetch residents for the dropdown
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name FROM residents ORDER BY last_name ASC");
    $residents = $resident_stmt->fetchAll();

    // Pagination and Filtering Parameters
    $search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';
    $time_from = isset($_GET['time_from']) ? sanitize_input($_GET['time_from']) : '';
    $time_to = isset($_GET['time_to']) ? sanitize_input($_GET['time_to']) : '';
    $date_mode = isset($_GET['date_mode']) ? sanitize_input($_GET['date_mode']) : 'request';
    $payment_filter = isset($_GET['payment']) ? sanitize_input($_GET['payment']) : '';
    $page_number = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $sort_by = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'date_requested';
    $sort_dir = isset($_GET['dir']) ? sanitize_input($_GET['dir']) : 'DESC';
    
    $rows_per_page = 50;
    $offset = ($page_number - 1) * $rows_per_page;
    $total_pages = 1;
    $total_count = 0;
    
    // Validate filter values
    $valid_statuses = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled'];
    $valid_payments = ['Paid', 'Unpaid'];
    $valid_sort_columns = ['date_requested', 'status', 'payment_status', 'first_name', 'last_name'];
    $valid_date_modes = ['request', 'payment'];
    
    // Validate and sanitize sort parameters
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
    
    // Build WHERE conditions for the combined result
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
    
    // Base UNION query (without filters)
    $union_query = "(
        SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.payment_date, dr.status, 'document' as request_type, dr.details, dr.or_number, dr.payment_status 
        FROM document_requests dr 
        LEFT JOIN residents r ON dr.resident_id = r.id
    ) UNION ALL (
        SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.payment_date, bt.status, 'business' as request_type, 
        JSON_OBJECT('business_name', bt.business_name, 'business_type', bt.business_type, 'owner_name', bt.owner_name, 'address', bt.address, 'transaction_type', bt.transaction_type) as details, 
        bt.or_number, bt.payment_status 
        FROM business_transactions bt 
        LEFT JOIN residents r ON bt.resident_id = r.id
    )";
    
    // Apply type filter by modifying the union query
    if ($type_filter === 'document') {
        $union_query = "(
            SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.payment_date, dr.status, 'document' as request_type, dr.details, dr.or_number, dr.payment_status 
            FROM document_requests dr 
            LEFT JOIN residents r ON dr.resident_id = r.id
        )";
    } else if ($type_filter === 'business') {
        $union_query = "(
            SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.payment_date, bt.status, 'business' as request_type, 
            JSON_OBJECT('business_name', bt.business_name, 'business_type', bt.business_type, 'owner_name', bt.owner_name, 'address', bt.address, 'transaction_type', bt.transaction_type) as details, 
            bt.or_number, bt.payment_status 
            FROM business_transactions bt 
            LEFT JOIN residents r ON bt.resident_id = r.id
        )";
    }

    // Count total requests
    $count_sql = "SELECT COUNT(*) FROM ({$union_query}) AS combined {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($exec_params);
    $total_count = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_count / $rows_per_page));
    
    // Enforce page bounds
    if ($page_number > $total_pages) {
        $page_number = $total_pages;
    }

    // Fetch paginated results
    $final_sql = "SELECT * FROM ({$union_query}) AS combined {$where_clause} ORDER BY {$sort_by} {$sort_dir} LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($final_sql);
    $final_params = $exec_params;
    $final_params[] = $rows_per_page;
    $final_params[] = $offset;
    
    $stmt->execute($final_params);
    $requests = $stmt->fetchAll();

} catch (PDOException $e) {
    $requests = [];
    $residents = [];
    $total_pages = 1;
    $page_number = 1;
    $_SESSION['error_message'] = "A database error occurred: " . $e->getMessage();
}

// Document types for the form
$document_types = [
    'Barangay Clearance' => 50.00,
    'Certificate of Residency' => 50.00,
    'Certificate of Indigency' => 0.00,
    'Business Clearance' => 500.00,
];

// Helper function to build pagination URLs
function buildPaginationUrl($page, $search = '', $status = '', $type = '', $date_from = '', $date_to = '', $payment = '', $time_from = '', $time_to = '') {
    return buildPaginationUrlFull($page, $search, $status, $type, $date_from, $date_to, $payment, '', '', 'request', $time_from, $time_to);
}

// Enhanced helper function with sorting
function buildPaginationUrlFull($page, $search = '', $status = '', $type = '', $date_from = '', $date_to = '', $payment = '', $sort = '', $dir = '', $date_mode = 'request', $time_from = '', $time_to = '') {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = urlencode($search);
    if (!empty($status)) $params['status'] = urlencode($status);
    if (!empty($type)) $params['type'] = urlencode($type);
    if (!empty($date_from)) $params['date_from'] = urlencode($date_from);
    if (!empty($date_to)) $params['date_to'] = urlencode($date_to);
    if (!empty($time_from)) $params['time_from'] = urlencode($time_from);
    if (!empty($time_to)) $params['time_to'] = urlencode($time_to);
    if (!empty($payment)) $params['payment'] = urlencode($payment);
    if (!empty($sort)) $params['sort'] = urlencode($sort);
    if (!empty($dir)) $params['dir'] = urlencode($dir);
    if (!empty($date_mode) && in_array($date_mode, ['request', 'payment'])) $params['date_mode'] = urlencode($date_mode);
    return 'monitoring-of-request.php?' . http_build_query($params);
}

// Helper function to get sort URL (toggles direction if same column)
function getSortUrl($sort_column, $current_sort, $current_dir, $search, $status, $type, $date_from, $date_to, $payment, $date_mode = 'request', $time_from = '', $time_to = '') {
    $new_dir = 'ASC';
    if ($sort_column === $current_sort && $current_dir === 'ASC') {
        $new_dir = 'DESC';
    }
    return buildPaginationUrlFull(1, $search, $status, $type, $date_from, $date_to, $payment, $sort_column, $new_dir, $date_mode, $time_from, $time_to);
}

// --- Analytics Calculations ---
$start_of_day = date('Y-m-d 00:00:00');
$end_of_day = date('Y-m-d 23:59:59');
$stats = [
    'today_queue' => 0,
    'workload' => 0,
    'ready' => 0,
    'revenue' => 0.00
];

try {
    // 1. Queue Today (Using indexed range)
    $q_today = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM document_requests WHERE date_requested BETWEEN ? AND ?) + 
        (SELECT COUNT(*) FROM business_transactions WHERE application_date BETWEEN ? AND ?) as total");
    $q_today->execute([$start_of_day, $end_of_day, $start_of_day, $end_of_day]);
    $stats['today_queue'] = $q_today->fetchColumn();

    // 2. Active Workload (Pending + Processing)
    $q_workload = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM document_requests WHERE status IN ('Pending', 'Processing')) + 
        (SELECT COUNT(*) FROM business_transactions WHERE status IN ('Pending', 'Processing')) as total");
    $stats['workload'] = $q_workload->fetchColumn();

    // 3. Ready for Pickup
    $q_ready = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM document_requests WHERE status = 'Ready for Pickup') + 
        (SELECT COUNT(*) FROM business_transactions WHERE status = 'Ready for Pickup') as total");
    $stats['ready'] = $q_ready->fetchColumn();

    // 4. Daily Revenue (Paid today)
    $biz_fee = $document_types['Business Clearance'] ?? 500.00;
    $q_revenue = $pdo->prepare("SELECT 
        COALESCE((SELECT SUM(
            CASE 
                WHEN cash_received IS NOT NULL THEN (cash_received - COALESCE(change_amount, 0))
                ELSE COALESCE(price, 0)
            END
        ) FROM document_requests WHERE payment_status = 'Paid' AND payment_date BETWEEN ? AND ?), 0) + 
        COALESCE((SELECT SUM(
            CASE 
                WHEN cash_received IS NOT NULL THEN (cash_received - COALESCE(change_amount, 0))
                ELSE $biz_fee
            END
        ) FROM business_transactions WHERE payment_status = 'Paid' AND payment_date BETWEEN ? AND ?), 0) as total");
    $q_revenue->execute([$start_of_day, $end_of_day, $start_of_day, $end_of_day]);
    $stats['revenue'] = $q_revenue->fetchColumn();

} catch (PDOException $e) {
    error_log("Stats Error: " . $e->getMessage());
}

// Summary metrics on current page for quick scanning.
$page_total = count($requests);
$page_paid = 0;
$page_unpaid = 0;
$page_ready = 0;
foreach ($requests as $summary_req) {
    $payment_state = (string)($summary_req['payment_status'] ?? 'Unpaid');
    $status_state = trim((string)($summary_req['status'] ?? ''));
    if ($payment_state === 'Paid') {
        $page_paid++;
    } else {
        $page_unpaid++;
    }
    if ($status_state === 'Ready for Pickup') {
        $page_ready++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        selectedReq: null, 
        viewPanelOpen: false, 
        openView(req) { 
            this.selectedReq = req; 
            this.viewPanelOpen = true; 
        },
        async submitCashPayment() {
            if (!this.selectedReq) return;

            const due = parseFloat(this.selectedReq.amountDue || 0);
            const cash = parseFloat(this.selectedReq.cashInput || 0);

            if (Number.isNaN(cash) || cash <= 0) {
                showToast('Please enter a valid cash amount.', 'error');
                return;
            }

            if (cash < due) {
                showToast('Cash amount is not enough for this request.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('id', this.selectedReq.id);
            formData.append('type', this.selectedReq.type);
            formData.append('cash_received', cash.toFixed(2));

            try {
                const response = await fetch('../partials/make-cash-payment.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (!data.success) {
                    showToast(data.error || 'Failed to process cash payment.', 'error');
                    return;
                }

                this.selectedReq.paymentStatus = 'Paid';
                this.selectedReq.orNumber = data.or_number || this.selectedReq.orNumber;
                this.selectedReq.changeValue = Number(data.change_amount || 0).toFixed(2);
                this.selectedReq.cashInput = '';
                this.selectedReq.isPaying = false;

                updateRowPaymentBadge(this.selectedReq.id, this.selectedReq.type, this.selectedReq.orNumber);
                showToast('Cash payment recorded. You can now print.');
            } catch (e) {
                showToast('Failed to process cash payment.', 'error');
            }
        }
    }">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10 h-0" :class="{'opacity-50 pointer-events-none': viewPanelOpen}">
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div id="monitoring-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                    echo '<p>' . htmlspecialchars($_SESSION['success_message']) . '</p>';
                    echo '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo display_error($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                }
                ?>

                <!-- Analytics Dashboard -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Queue Today Card -->
                    <a href="?page=1&status=&type=&date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>&payment=" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4 hover:shadow-md hover:border-blue-200 transition cursor-pointer">
                        <div class="bg-blue-50 p-3 rounded-xl"><i class="fas fa-file-invoice text-blue-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Queue Today</p>
                            <h4 class="text-2xl font-black text-gray-900"><?= number_format($stats['today_queue']) ?></h4>
                        </div>
                    </a>
                    <!-- Workload Card -->
                    <a href="?page=1&status=Pending,Processing&type=&date_from=&date_to=&payment=" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4 hover:shadow-md hover:border-amber-200 transition cursor-pointer">
                        <div class="bg-amber-50 p-3 rounded-xl"><i class="fas fa-clock text-amber-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Workload</p>
                            <h4 class="text-2xl font-black text-gray-900"><?= number_format($stats['workload']) ?></h4>
                        </div>
                    </a>
                    <!-- Ready Card -->
                    <a href="?page=1&status=Ready%20for%20Pickup&type=&date_from=&date_to=&payment=" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4 hover:shadow-md hover:border-green-200 transition cursor-pointer">
                        <div class="bg-green-50 p-3 rounded-xl"><i class="fas fa-check-double text-green-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Ready</p>
                            <h4 class="text-2xl font-black text-gray-900"><?= number_format($stats['ready']) ?></h4>
                        </div>
                    </a>
                    <!-- Revenue Card -->
                    <a href="?page=1&status=&type=&date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>&payment=Paid&date_mode=payment" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4 hover:shadow-md hover:border-indigo-200 transition cursor-pointer">
                        <div class="bg-indigo-50 p-3 rounded-xl"><i class="fas fa-coins text-indigo-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Revenue Today</p>
                            <h4 class="text-2xl font-black text-gray-900">₱<?= number_format($stats['revenue'], 2) ?></h4>
                        </div>
                    </a>
                </div>
                <div class="grid grid-cols-1 gap-8">
                    <!-- Pending Requests Table -->
                    <div class="w-full">
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-xl font-bold text-gray-800">Pending Requests</h2>
                                    <div class="flex items-center space-x-2">
                                        <a href="monitoring-of-request.php" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Clear Filters</a>
                                        <a id="exportCsvBtn" href="#" onclick="exportRequests(event)" class="text-xs text-green-600 hover:text-green-800 font-medium flex items-center">
                                            <i class="fas fa-download mr-1"></i>Export CSV
                                        </a>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Filtered Total</p>
                                        <p class="text-lg font-black text-gray-900"><?php echo number_format($total_count); ?></p>
                                    </div>
                                    <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-green-700 font-bold">Paid On Page</p>
                                        <p class="text-lg font-black text-green-800"><?php echo number_format($page_paid); ?></p>
                                    </div>
                                    <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-red-700 font-bold">Unpaid On Page</p>
                                        <p class="text-lg font-black text-red-800"><?php echo number_format($page_unpaid); ?></p>
                                    </div>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-blue-700 font-bold">Ready On Page</p>
                                        <p class="text-lg font-black text-blue-800"><?php echo number_format($page_ready); ?></p>
                                    </div>
                                </div>
                                
                                <!-- Bulk Action Bar (Hidden by default) -->
                                <div id="bulkActionBar" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center space-x-3">
                                            <span class="text-sm font-medium text-gray-700">
                                                <span id="selectedCount">0</span> selected
                                            </span>
                                            <select id="bulkStatusSelect" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Set status...</option>
                                                <option value="Pending">Pending</option>
                                                <option value="Processing">Processing</option>
                                                <option value="Ready for Pickup">Ready for Pickup</option>
                                                <option value="Completed">Completed</option>
                                                <option value="Rejected">Rejected</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                            <button onclick="doBulkStatusUpdate()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Update</button>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <button onclick="doBulkDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Delete</button>
                                            <button onclick="clearSelection()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-400">Clear</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Filters Row 1: Status, Type, Payment -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                                    <!-- Status Filter -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Status</label>
                                        <select onchange="updateFilter('status', this.value)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">All Statuses</option>
                                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Type Filter -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Request Type</label>
                                        <select onchange="updateFilter('type', this.value)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">All Types</option>
                                            <option value="document" <?php echo $type_filter === 'document' ? 'selected' : ''; ?>>Document Request</option>
                                            <option value="business" <?php echo $type_filter === 'business' ? 'selected' : ''; ?>>Business Transaction</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Payment Filter -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Status</label>
                                        <select onchange="updateFilter('payment', this.value)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">All Payments</option>
                                            <option value="Paid" <?php echo $payment_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="Unpaid" <?php echo $payment_filter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Search Box (moved here) -->
                                    <div>
                                        <form action="monitoring-of-request.php" method="GET" class="flex items-end h-full">
                                            <!-- Hidden inputs to preserve other filters -->
                                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>">
                                            <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                                            <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                                            <input type="hidden" name="time_from" value="<?php echo htmlspecialchars($time_from); ?>">
                                            <input type="hidden" name="time_to" value="<?php echo htmlspecialchars($time_to); ?>">
                                            <input type="hidden" name="payment" value="<?php echo htmlspecialchars($payment_filter); ?>">
                                            <input type="hidden" name="date_mode" value="<?php echo htmlspecialchars($date_mode); ?>">
                                            <div class="relative flex-1">
                                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 h-full"><i class="fas fa-search text-sm"></i></span>
                                                <input id="requestSearchInput" type="text" name="search" class="w-full h-full pl-10 pr-3 py-2 text-sm bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Filters Row 2: Date Range -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <!-- Date From -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date From</label>
                                        <input type="date" value="<?php echo htmlspecialchars($date_from); ?>" onchange="updateFilter('date_from', this.value)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <input type="time" value="<?php echo htmlspecialchars($time_from); ?>" onchange="updateFilter('time_from', this.value)" class="w-full mt-2 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" title="Start time">
                                    </div>
                                    
                                    <!-- Date To -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date To</label>
                                        <input type="date" value="<?php echo htmlspecialchars($date_to); ?>" onchange="updateFilter('date_to', this.value)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <input type="time" value="<?php echo htmlspecialchars($time_to); ?>" onchange="updateFilter('time_to', this.value)" class="w-full mt-2 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" title="End time">
                                    </div>

                                    <!-- Date Basis -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date Basis</label>
                                        <select onchange="updateFilter('date_mode', this.value)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="request" <?php echo $date_mode === 'request' ? 'selected' : ''; ?>>Requested Date</option>
                                            <option value="payment" <?php echo $date_mode === 'payment' ? 'selected' : ''; ?>>Payment Date</option>
                                        </select>
                                        <p class="text-[11px] text-gray-500 mt-2">Tip: Revenue Today uses Payment Date.</p>
                                    </div>
                                </div>
                                
                                <!-- Active Filters Display -->
                                <?php if (!empty($search_query) || !empty($status_filter) || !empty($type_filter) || !empty($date_from) || !empty($date_to) || !empty($payment_filter) || !empty($time_from) || !empty($time_to) || $date_mode !== 'request'): ?>
                                <div class="mt-3 flex items-center space-x-2 text-xs text-gray-600">
                                    <span class="font-medium">Active filters:</span>
                                    <?php if (!empty($search_query)): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Search: <?php echo htmlspecialchars($search_query); ?></span><?php endif; ?>
                                    <?php if (!empty($status_filter)): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Status: <?php echo htmlspecialchars($status_filter); ?></span><?php endif; ?>
                                    <?php if (!empty($type_filter)): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Type: <?php echo htmlspecialchars($type_filter === 'document' ? 'Document' : 'Business'); ?></span><?php endif; ?>
                                    <?php if (!empty($payment_filter)): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Payment: <?php echo htmlspecialchars($payment_filter); ?></span><?php endif; ?>
                                    <?php if (!empty($date_from) || !empty($date_to)): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Date: <?php echo htmlspecialchars($date_from . ' to ' . $date_to); ?></span><?php endif; ?>
                                    <?php if (!empty($time_from) || !empty($time_to)): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Time: <?php echo htmlspecialchars(($time_from ?: '00:00') . ' to ' . ($time_to ?: '23:59')); ?></span><?php endif; ?>
                                    <?php if ($date_mode !== 'request'): ?><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">Basis: Payment Date</span><?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status Legend -->
                                <div class="mt-6 p-6 bg-blue-50 rounded-xl border border-blue-100 mb-2">
                                    <p class="text-sm font-bold text-blue-900 mb-4 flex items-center">
                                        <i class="fas fa-info-circle mr-2 text-blue-600 text-lg"></i>Status Guide
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
                                        <div class="flex items-center"><span class="status-badge bg-yellow-100 text-yellow-800 inline-block w-24 text-center mr-3">Pending</span> <span class="text-gray-600 font-medium">Awaiting action</span></div>
                                        <div class="flex items-center"><span class="status-badge bg-blue-100 text-blue-800 inline-block w-24 text-center mr-3">Processing</span> <span class="text-gray-600 font-medium">Being worked on</span></div>
                                        <div class="flex items-center"><span class="status-badge bg-blue-100 text-blue-800 inline-block w-24 text-center mr-3">Ready</span> <span class="text-gray-600 font-medium">For pickup</span></div>
                                        <div class="flex items-center"><span class="status-badge bg-green-100 text-green-800 inline-block w-24 text-center mr-3">Completed</span> <span class="text-gray-600 font-medium">Done</span></div>
                                        <div class="flex items-center"><span class="status-badge bg-red-100 text-red-800 inline-block w-24 text-center mr-3">Rejected</span> <span class="text-gray-600 font-medium">Denied</span></div>
                                        <div class="flex items-center"><span class="status-badge bg-gray-100 text-gray-800 inline-block w-24 text-center mr-3">Cancelled</span> <span class="text-gray-600 font-medium">Withdrawn</span></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">
                                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="rounded border-gray-300">
                                                </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                                        <a href="<?php echo getSortUrl('first_name', $sort_by, $sort_dir, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $date_mode, $time_from, $time_to); ?>" class="flex items-center space-x-1 group">
                                                            <span>Name</span>
                                                            <?php if ($sort_by === 'first_name'): ?>
                                                                <i class="fas fa-sort<?php echo $sort_dir === 'ASC' ? '-up' : '-down'; ?> text-blue-600"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-sort text-gray-300 group-hover:text-gray-400"></i>
                                                            <?php endif; ?>
                                                        </a>
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate Type</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                                        <a href="<?php echo getSortUrl('date_requested', $sort_by, $sort_dir, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $date_mode, $time_from, $time_to); ?>" class="flex items-center space-x-1 group">
                                                            <span>Date Sent</span>
                                                            <?php if ($sort_by === 'date_requested'): ?>
                                                                <i class="fas fa-sort<?php echo $sort_dir === 'ASC' ? '-up' : '-down'; ?> text-blue-600"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-sort text-gray-300 group-hover:text-gray-400"></i>
                                                            <?php endif; ?>
                                                        </a>
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                                        <a href="<?php echo getSortUrl('status', $sort_by, $sort_dir, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $date_mode, $time_from, $time_to); ?>" class="flex items-center space-x-1 group">
                                                            <span>Status</span>
                                                            <?php if ($sort_by === 'status'): ?>
                                                                <i class="fas fa-sort<?php echo $sort_dir === 'ASC' ? '-up' : '-down'; ?> text-blue-600"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-sort text-gray-300 group-hover:text-gray-400"></i>
                                                            <?php endif; ?>
                                                        </a>
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                                        <a href="<?php echo getSortUrl('payment_status', $sort_by, $sort_dir, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $date_mode, $time_from, $time_to); ?>" class="flex items-center space-x-1 group">
                                                            <span>Payment</span>
                                                            <?php if ($sort_by === 'payment_status'): ?>
                                                                <i class="fas fa-sort<?php echo $sort_dir === 'ASC' ? '-up' : '-down'; ?> text-blue-600"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-sort text-gray-300 group-hover:text-gray-400"></i>
                                                            <?php endif; ?>
                                                        </a>
                                                    </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($requests)): ?>
                                                <tr><td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No requests found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($requests as $req): 
                                                    $name = htmlspecialchars(($req['first_name'] ?? 'Unknown') . ' ' . ($req['last_name'] ?? 'Resident'));
                                                    $doc_type = htmlspecialchars($req['document_type']);
                                                    $date = date('M. d, Y h:i A', strtotime($req['date_requested']));
                                                    $status = trim((string)($req['status'] ?? ''));
                                                    $status = htmlspecialchars($status);
                                                    $status_bg = 'bg-yellow-100 text-yellow-800'; // Default
                                                    $status_text = $status ?: 'Pending';

                                                    // Unified status logic
                                                    switch($status) {
                                                        case 'Processing':
                                                            $status_bg = 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'Ready for Pickup':
                                                        case 'Ready':
                                                            $status_bg = 'bg-blue-100 text-blue-800';
                                                            $status_text = 'Ready for Pickup';
                                                            break;
                                                        case 'Completed':
                                                            $status_bg = 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'Rejected':
                                                            $status_bg = 'bg-red-100 text-red-800';
                                                            break;
                                                        case 'Cancelled':
                                                            $status_bg = 'bg-gray-100 text-gray-800';
                                                            break;
                                                        default:
                                                            $status_bg = 'bg-yellow-100 text-yellow-800';
                                                    }

                                                    $raw_doc_type = (string)($req['document_type'] ?? '');
                                                    if (($req['request_type'] ?? '') === 'business') {
                                                        $amount_due = (float)($document_types['Business Clearance'] ?? 500.00);
                                                    } else {
                                                        $amount_due = (float)($document_types[$raw_doc_type] ?? 50.00);
                                                    }
                                                ?>
                                                    <tr id="request-row-<?php echo $req['request_type']; ?>-<?php echo $req['id']; ?>" class="request-row hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <input type="checkbox" class="request-checkbox rounded border-gray-300" data-id="<?php echo $req['id']; ?>" data-type="<?php echo $req['request_type']; ?>" onchange="updateBulkState()">
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $name; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo $doc_type; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <span class="status-badge <?php echo $status_bg; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <?php if (($req['payment_status'] ?? 'Unpaid') === 'Paid'): ?>
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                                                                    <i class="fas fa-check-circle mr-1"></i> PAID
                                                                </span>
                                                                <?php if (!empty($req['or_number'])): ?>
                                                                    <div class="text-xs text-gray-500 mt-1">O.R. <?= htmlspecialchars($req['or_number']) ?></div>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                                                    <i class="fas fa-clock mr-1"></i> UNPAID
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative" data-request-id="<?php echo $req['id']; ?>" data-request-type="<?php echo $req['request_type']; ?>" data-document-type="<?php echo htmlspecialchars($req['document_type']); ?>">
                                                            <div class="flex items-center space-x-1">
                                                                <!-- Quick View Button -->
                                                                <button type="button" @click="openView({
                                                                    id: '<?php echo $req['id']; ?>',
                                                                    type: '<?php echo $req['request_type']; ?>',
                                                                    name: '<?php echo addslashes($name); ?>',
                                                                    docType: '<?php echo addslashes($doc_type); ?>',
                                                                    date: '<?php echo addslashes($date); ?>',
                                                                    status: '<?php echo addslashes($status_text); ?>',
                                                                    statusBg: '<?php echo addslashes($status_bg); ?>',
                                                                    orNumber: '<?php echo addslashes($req['or_number'] ?? ''); ?>',
                                                                    paymentStatus: '<?php echo addslashes($req['payment_status'] ?? 'Unpaid'); ?>',
                                                                    amountDue: '<?php echo number_format($amount_due, 2, '.', ''); ?>',
                                                                    cashInput: '',
                                                                    changeValue: null,
                                                                    isPaying: false,
                                                                    details: <?php echo $req['details'] ? htmlspecialchars(json_encode(json_decode($req['details'], true)), ENT_QUOTES, 'UTF-8') : 'null'; ?>
                                                                })" class="inline-flex items-center justify-center w-8 h-8 rounded text-blue-600 hover:bg-blue-50 focus:outline-none" title="Quick View">
                                                                    <i class="fas fa-eye text-sm"></i>
                                                                </button>

                                                                <!-- Quick Action Button: Mark as Processing -->
                                                                <?php if ($status === 'Pending'): ?>
                                                                    <button type="button" onclick="changeRequestStatus('<?php echo $req['id']; ?>', '<?php echo $req['request_type']; ?>', 'Processing')" class="inline-flex items-center justify-center w-8 h-8 rounded text-blue-600 hover:bg-blue-50 focus:outline-none" title="Start Processing">
                                                                        <i class="fas fa-play text-xs"></i>
                                                                    </button>
                                                                <?php elseif ($status === 'Processing'): ?>
                                                                    <button type="button" onclick="changeRequestStatus('<?php echo $req['id']; ?>', '<?php echo $req['request_type']; ?>', 'Ready for Pickup')" class="inline-flex items-center justify-center w-8 h-8 rounded text-green-600 hover:bg-green-50 focus:outline-none" title="Mark Ready">
                                                                        <i class="fas fa-check text-xs"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Actions Menu -->
                                                                <div class="relative inline-block text-left" x-data="{ 
                                                                    open: false, 
                                                                    top: 0, 
                                                                    left: 0,
                                                                    menuWidth: 192,
                                                                    estimateMenuHeight: 260,
                                                                    updateMenuPosition() {
                                                                        const rect = this.$refs.dropdownBtn.getBoundingClientRect();
                                                                        const viewportW = window.innerWidth;
                                                                        const viewportH = window.innerHeight;
                                                                        const gap = 6;

                                                                        let nextLeft = rect.left;
                                                                        let nextTop = rect.bottom + gap;

                                                                        // Keep menu inside viewport horizontally.
                                                                        if (nextLeft + this.menuWidth > viewportW - 8) {
                                                                            nextLeft = Math.max(8, viewportW - this.menuWidth - 8);
                                                                        }

                                                                        // Flip upward if there is not enough room below.
                                                                        if (nextTop + this.estimateMenuHeight > viewportH - 8) {
                                                                            nextTop = Math.max(8, rect.top - this.estimateMenuHeight - gap);
                                                                        }

                                                                        this.left = Math.round(nextLeft);
                                                                        this.top = Math.round(nextTop);
                                                                    }
                                                                }" @resize.window="if (open) updateMenuPosition()" @scroll.window="if (open) updateMenuPosition()">
                                                                    <button type="button" x-ref="dropdownBtn" @click="
                                                                        open = !open;
                                                                        if (open) {
                                                                            updateMenuPosition();
                                                                        }
                                                                    " class="flex items-center justify-center w-8 h-8 rounded hover:bg-gray-200 focus:outline-none" aria-haspopup="true" aria-expanded="false">
                                                                        <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                                            <circle cx="4" cy="10" r="1.5"/>
                                                                            <circle cx="10" cy="10" r="1.5"/>
                                                                            <circle cx="16" cy="10" r="1.5"/>
                                                                        </svg>
                                                                    </button>
                                                                    <template x-teleport="body">
                                                                        <div x-show="open" @click.away="open = false" x-cloak
                                                                             class="fixed z-50 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                                                             :style="'top: ' + top + 'px; left: ' + left + 'px;'">
                                                                            <div class="py-1">
                                                                                <!-- Unified status options -->
                                                                                <?php 
                                                                                $opt_statuses = ["Pending", "Processing", "Ready for Pickup", "Completed", "Rejected"];
                                                                                foreach ($opt_statuses as $opt):
                                                                                    if ($status !== $opt): ?>
                                                                                        <button type="button" onclick="changeRequestStatus('<?php echo $req['id']; ?>', '<?php echo $req['request_type']; ?>', '<?php echo $opt; ?>')" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 <?php echo $opt === 'Rejected' ? 'text-red-600' : ($opt === 'Completed' ? 'text-green-700' : 'text-gray-700'); ?>">Set as <?php echo $opt; ?></button>
                                                                                    <?php endif;
                                                                                endforeach; ?>
                                                                                
                                                                                <?php if ($status === 'Pending'): ?>
                                                                                    <div class="border-t border-gray-100 my-1"></div>
                                                                                    <button type="button" onclick="cancelRequest('<?php echo $req['id']; ?>', '<?php echo $req['request_type']; ?>')" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">Cancel Request</button>
                                                                                <?php endif; ?>
                                                                                <!-- Delete option -->
                                                                                <div class="border-t border-gray-100 my-1"></div>
                                                                                <button type="button" onclick="deleteRequest('<?php echo $req['id']; ?>', '<?php echo $req['request_type']; ?>')" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete Request</button>
                                                                            </div>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination Controls -->
                                <?php if ($total_pages > 1): ?>
                                <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                                    <div class="text-sm text-gray-600">
                                        Showing <span class="font-medium"><?php echo ($total_count > 0) ? ($offset + 1) : 0; ?></span> to <span class="font-medium"><?php echo min($offset + $rows_per_page, $total_count); ?></span> of <span class="font-medium"><?php echo $total_count; ?></span> requests
                                    </div>
                                    <div class="flex space-x-2">
                                        <!-- Previous Button -->
                                        <?php if ($page_number > 1): ?>
                                            <a href="<?php echo buildPaginationUrlFull($page_number - 1, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $sort_by, $sort_dir, $date_mode, $time_from, $time_to); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                <i class="fas fa-chevron-left mr-1"></i>Previous
                                            </a>
                                        <?php else: ?>
                                            <button disabled class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed">
                                                <i class="fas fa-chevron-left mr-1"></i>Previous
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <div class="flex items-center space-x-1">
                                            <?php 
                                            $start_page = max(1, $page_number - 2);
                                            $end_page = min($total_pages, $page_number + 2);
                                            
                                            if ($start_page > 1): ?>
                                                <a href="<?php echo buildPaginationUrlFull(1, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $sort_by, $sort_dir, $date_mode, $time_from, $time_to); ?>" class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                                <?php if ($start_page > 2): ?><span class="text-gray-500">...</span><?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <?php if ($i === $page_number): ?>
                                                    <span class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium"><?php echo $i; ?></span>
                                                <?php else: ?>
                                                    <a href="<?php echo buildPaginationUrlFull($i, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $sort_by, $sort_dir, $date_mode, $time_from, $time_to); ?>" class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            
                                            <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?><span class="text-gray-500">...</span><?php endif; ?>
                                                <a href="<?php echo buildPaginationUrlFull($total_pages, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $sort_by, $sort_dir, $date_mode, $time_from, $time_to); ?>" class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Next Button -->
                                        <?php if ($page_number < $total_pages): ?>
                                            <a href="<?php echo buildPaginationUrlFull($page_number + 1, $search_query, $status_filter, $type_filter, $date_from, $date_to, $payment_filter, $sort_by, $sort_dir, $date_mode, $time_from, $time_to); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                Next<i class="fas fa-chevron-right ml-1"></i>
                                            </a>
                                        <?php else: ?>
                                            <button disabled class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed">
                                                Next<i class="fas fa-chevron-right ml-1"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Slide-Over Panel for Quick View -->
        <div x-show="viewPanelOpen" class="fixed inset-0 overflow-hidden z-[100]" aria-labelledby="slide-over-title" role="dialog" aria-modal="true" style="display: none;">
          <div class="absolute inset-0 overflow-hidden">
            <div x-show="viewPanelOpen" x-transition.opacity class="absolute inset-0 bg-gray-600 bg-opacity-75 transition-opacity" @click="viewPanelOpen = false"></div>
            <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
              <div x-show="viewPanelOpen" 
                   x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500" 
                   x-transition:enter-start="translate-x-full" 
                   x-transition:enter-end="translate-x-0" 
                   x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500" 
                   x-transition:leave-start="translate-x-0" 
                   x-transition:leave-end="translate-x-full" 
                   class="w-screen max-w-md">
                <div class="h-full flex flex-col bg-white shadow-xl overflow-y-scroll">
                  <!-- Blue Header with Avatar and Close Button -->
                  <div class="px-4 py-6 bg-blue-600 sm:px-6">
                     <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-4">
                           <!-- Avatar with Initials -->
                           <div class="w-16 h-16 rounded-2xl bg-white bg-opacity-20 flex items-center justify-center border-2 border-white" x-show="selectedReq">
                              <span class="text-2xl font-bold text-white" x-text="selectedReq.name ? selectedReq.name.split(' ').map(n => n[0]).join('') : 'N/A'"></span>
                           </div>
                           <!-- Name and ID -->
                           <div>
                              <h2 class="text-xl font-bold text-white" x-text="selectedReq.name"></h2>
                              <p class="text-sm text-blue-100 mt-1" x-text="selectedReq.type === 'document' ? 'Document Request' : 'Business Transaction'"></p>
                           </div>
                        </div>
                        <button type="button" @click="viewPanelOpen = false" class="bg-blue-600 rounded-md text-blue-200 hover:text-white focus:outline-none focus:ring-2 focus:ring-white">
                           <span class="sr-only">Close panel</span>
                           <i class="fas fa-times text-xl"></i>
                        </button>
                     </div>
                  </div>

                  <div class="relative flex-1 px-4 py-6 sm:px-6">
                     <!-- Content inside slider -->
                     <template x-if="selectedReq">
                        <div class="space-y-6">
                           <!-- Stat Cards (Document Count, Status Count) -->
                           <div class="grid grid-cols-2 gap-3">
                              <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                                 <p class="text-xs font-bold text-blue-600 uppercase tracking-wider">Current Status</p>
                                 <p class="mt-2 text-lg font-black text-blue-900" x-text="selectedReq.status"></p>
                              </div>
                              <div class="bg-red-50 rounded-lg p-4 border border-red-100">
                                 <p class="text-xs font-bold text-red-600 uppercase tracking-wider">Payment Status</p>
                                 <p class="mt-2 text-lg font-black text-red-900" x-text="selectedReq.paymentStatus"></p>
                              </div>
                           </div>

                           <!-- Request Details Section -->
                           <div class="bg-gray-50 rounded-lg p-4">
                              <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center">
                                 <i class="fas fa-file-alt mr-2"></i>REQUEST DETAILS
                              </h3>
                              <div class="space-y-3">
                                 <div>
                                    <p class="text-xs font-semibold text-gray-600 uppercase">Document Type</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900" x-text="selectedReq.docType"></p>
                                 </div>
                                 <div>
                                    <p class="text-xs font-semibold text-gray-600 uppercase">Date Requested</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900" x-text="selectedReq.date"></p>
                                 </div>
                                 <div x-show="selectedReq.orNumber">
                                    <p class="text-xs font-semibold text-gray-600 uppercase">O.R. Number</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900" x-text="selectedReq.orNumber || 'N/A'"></p>
                                 </div>
                              </div>
                           </div>

                           <!-- Application Details Section (for business/complex requests) -->
                           <template x-if="selectedReq.details && Object.keys(selectedReq.details).length > 0">
                              <div class="bg-gray-50 rounded-lg p-4">
                                 <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>APPLICATION DETAILS
                                 </h3>
                                 <div class="space-y-3">
                                    <template x-for="(value, key) in selectedReq.details" :key="key">
                                       <div>
                                          <p class="text-xs font-semibold text-gray-600 uppercase" x-text="key.replace(/_/g, ' ')"></p>
                                          <p class="mt-1 text-sm font-medium text-gray-900" x-text="value"></p>
                                       </div>
                                    </template>
                                 </div>
                              </div>
                           </template>
                           
                           <!-- Quick Actions Section -->
                           <div class="border-t border-gray-200 pt-4">
                              <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3 flex items-center">
                                 <i class="fas fa-bolt mr-2"></i>QUICK ACTIONS
                              </h3>
                              <div class="space-y-2">
                                 <!-- Start Processing (if Pending) -->
                                 <button x-show="selectedReq.status === 'Pending'" @click="changeRequestStatus(selectedReq.id, selectedReq.type, 'Processing'); viewPanelOpen = false;" class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg shadow hover:bg-blue-700 transition font-bold uppercase tracking-widest text-xs">
                                    <i class="fas fa-play mr-2"></i>Start Processing
                                 </button>

                                            <!-- Cash Payment Action (required before print) -->
                                            <div x-show="selectedReq.paymentStatus !== 'Paid'" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                                <button type="button" x-show="!selectedReq.isPaying" @click="selectedReq.isPaying = true" class="w-full bg-amber-600 text-white px-4 py-3 rounded-lg shadow hover:bg-amber-700 transition font-bold uppercase tracking-widest text-xs">
                                                    <i class="fas fa-money-bill-wave mr-2"></i>Make Cash Payment
                                                </button>

                                                <div x-show="selectedReq.isPaying" class="space-y-2">
                                                    <p class="text-xs font-semibold text-amber-800">Amount Due: <span class="font-black" x-text="'₱' + Number(selectedReq.amountDue || 0).toFixed(2)"></span></p>
                                                    <input type="number" min="0" step="0.01" x-model="selectedReq.cashInput" placeholder="Enter cash amount" class="w-full px-3 py-2 text-sm border border-amber-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                                                    <p class="text-xs text-gray-700" x-show="selectedReq.cashInput !== ''">
                                                        Change: <span class="font-bold" x-text="'₱' + Math.max(Number(selectedReq.cashInput || 0) - Number(selectedReq.amountDue || 0), 0).toFixed(2)"></span>
                                                    </p>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <button type="button" @click="submitCashPayment()" class="bg-green-600 text-white px-3 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-green-700">Confirm Payment</button>
                                                        <button type="button" @click="selectedReq.isPaying = false; selectedReq.cashInput = ''" class="bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-gray-300">Cancel</button>
                                                    </div>
                                                </div>
                                            </div>

                                 <!-- Print Document (if not rejected/cancelled) -->
                                            <button x-show="selectedReq.status !== 'Rejected' && selectedReq.status !== 'Cancelled'" :disabled="selectedReq.paymentStatus !== 'Paid'" @click="if (selectedReq.paymentStatus !== 'Paid') return; const templates = {
                                    'Barangay Clearance': 'barangay-clearance-template.php',
                                    'Certificate of Residency': 'certificate-of-residency-template.php',
                                    'Certificate of Indigency': 'certificate-of-indigency-template.php',
                                    'business': 'business-clearance-template.php'
                                 };
                                 const templateFile = selectedReq.type === 'business' ? templates['business'] : (templates[selectedReq.docType] || 'barangay-clearance-template.php');
                                            window.open(templateFile + '?id=' + selectedReq.id, '_blank');" :class="selectedReq.paymentStatus === 'Paid' ? 'w-full bg-gray-100 text-gray-800 px-4 py-3 rounded-lg hover:bg-gray-200 transition font-bold uppercase tracking-widest text-xs flex items-center justify-center' : 'w-full bg-gray-100 text-gray-400 px-4 py-3 rounded-lg opacity-70 cursor-not-allowed font-bold uppercase tracking-widest text-xs flex items-center justify-center'">
                                    <i class="fas fa-print mr-2"></i>Print Certificate / Clearance
                                 </button>
                                            <p x-show="selectedReq.paymentStatus !== 'Paid'" class="text-[11px] text-red-600 font-semibold text-center">Payment required before printing.</p>

                                 <!-- View Full Details (Link to main page) -->
                                 <button @click="viewPanelOpen = false;" class="w-full bg-gray-50 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-100 transition font-bold uppercase tracking-widest text-xs border border-gray-200">
                                    <i class="fas fa-expand mr-2"></i>View Full Details
                                 </button>
                              </div>
                           </div>
                        </div>
                     </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
    </div>
    <script>
    // Filter handler function
    function updateFilter(filterName, filterValue) {
        const params = new URLSearchParams(window.location.search);
        
        if (filterValue === '') {
            params.delete(filterName);
        } else {
            params.set(filterName, filterValue);
        }
        
        // Reset to page 1 when filters change
        params.set('page', '1');
        
        window.location.href = 'monitoring-of-request.php?' + params.toString();
    }

    // Bulk Selection State
    let selectedRequests = new Map();

    function updateBulkState() {
        selectedRequests.clear();
        document.querySelectorAll('.request-checkbox:checked').forEach(checkbox => {
            const id = checkbox.getAttribute('data-id');
            const type = checkbox.getAttribute('data-type');
            selectedRequests.set(id, type);
        });
        
        const bulkBar = document.getElementById('bulkActionBar');
        const countSpan = document.getElementById('selectedCount');
        
        if (selectedRequests.size > 0) {
            bulkBar.classList.remove('hidden');
            countSpan.textContent = selectedRequests.size;
        } else {
            bulkBar.classList.add('hidden');
        }
    }

    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.request-checkbox').forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateBulkState();
    }

    function clearSelection() {
        document.querySelectorAll('.request-checkbox').forEach(cb => {
            cb.checked = false;
        });
        document.getElementById('selectAllCheckbox').checked = false;
        updateBulkState();
    }

    function doBulkStatusUpdate() {
        const status = document.getElementById('bulkStatusSelect').value;
        if (!status) {
            showToast('Please select a status.', 'error');
            return;
        }

        confirmModal(`Set ${selectedRequests.size} request(s) to <strong>${status}</strong>?`, () => {

        const ids = Array.from(selectedRequests.keys());
        const types = Array.from(selectedRequests.values());

        const formData = new FormData();
        formData.append('action', 'bulk_status');
        ids.forEach((id, i) => {
            formData.append('ids[]', id);
            formData.append('types[]', types[i]);
        });
        formData.append('status', status);

        fetch('../partials/bulk-action-requests.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove rows from UI without reload
                ids.forEach((id, i) => {
                    const row = document.getElementById(`request-row-${types[i]}-${id}`);
                    if (row) row.style.opacity = '0.5';
                });
                showToast(data.message || 'Status updated successfully.');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('Failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to update requests.', 'error');
        });
        }); // end confirmModal
    }

    function doBulkDelete() {
        confirmModal(`<span class="text-red-600 font-bold">Delete ${selectedRequests.size} request(s)?</span><br><span class="text-sm text-gray-500">This cannot be undone.</span>`, () => {

        const ids = Array.from(selectedRequests.keys());
        const types = Array.from(selectedRequests.values());

        const formData = new FormData();
        formData.append('action', 'bulk_delete');
        ids.forEach((id, i) => {
            formData.append('ids[]', id);
            formData.append('types[]', types[i]);
        });

        fetch('../partials/bulk-action-requests.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove rows from UI
                ids.forEach((id, i) => {
                    const row = document.getElementById(`request-row-${types[i]}-${id}`);
                    if (row) row.remove();
                });
                showToast(data.message || 'Requests deleted.');
                clearSelection();
            } else {
                showToast('Failed: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to delete requests.', 'error');
        });
        }); // end confirmModal
    }

    function exportRequests(event) {
        event.preventDefault();
        const params = new URLSearchParams(window.location.search);
        window.location.href = '../partials/export-requests.php?' + params.toString();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('monitoring-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }

        // Keyboard shortcuts: / focus search, Alt+E export, Alt+C clear selected rows.
        document.addEventListener('keydown', function(e) {
            const tagName = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
            const isTyping = tagName === 'input' || tagName === 'textarea' || tagName === 'select' || (e.target && e.target.isContentEditable);

            if (e.key === '/' && !isTyping) {
                e.preventDefault();
                const searchInput = document.getElementById('requestSearchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'e') {
                e.preventDefault();
                const exportBtn = document.getElementById('exportCsvBtn');
                if (exportBtn) exportBtn.click();
                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'c') {
                e.preventDefault();
                clearSelection();
                showToast('Selection cleared.');
            }
        });
    });

    function changeRequestStatus(id, type, status) {
        confirmModal(`Set status to <strong>${status}</strong>?`, () => {
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('type', type);
        formData.append('status', status);

        fetch('../partials/ajax-update-request-status.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update row status badge without reload
                    const row = document.getElementById(`request-row-${type}-${id}`);
                    if (row) {
                        const statusCell = row.querySelector('.status-badge');
                        if (statusCell) {
                            statusCell.textContent = status;
                            statusCell.className = 'status-badge ' + getStatusBgClass(status);
                        }
                        row.style.backgroundColor = '#f0fdf4';
                        setTimeout(() => row.style.backgroundColor = '', 600);
                    }
                    showToast('Status updated to ' + status + '.');
                } else {
                    showToast('Failed to update status: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast('Failed to update status. Please try again.', 'error');
            });
        }); // end confirmModal
    }

    function getStatusBgClass(status) {
        switch(status) {
            case 'Processing':
                return 'bg-blue-100 text-blue-800';
            case 'Ready for Pickup':
            case 'Ready':
                return 'bg-blue-100 text-blue-800';
            case 'Completed':
                return 'bg-green-100 text-green-800';
            case 'Rejected':
                return 'bg-red-100 text-red-800';
            case 'Cancelled':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-yellow-100 text-yellow-800';
        }
    }

    function updateRowPaymentBadge(id, type, orNumber) {
        const row = document.getElementById(`request-row-${type}-${id}`);
        if (!row) return;

        const cells = row.querySelectorAll('td');
        if (!cells || cells.length < 6) return;

        const paymentCell = cells[5];
        const safeOr = (orNumber || '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;');
        paymentCell.innerHTML = `
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                <i class="fas fa-check-circle mr-1"></i> PAID
            </span>
            ${safeOr ? `<div class="text-xs text-gray-500 mt-1">O.R. ${safeOr}</div>` : ''}
        `;
    }

    function updatePaymentInfo(id, type, orNumber, status) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('type', type);
        formData.append('or_number', orNumber);
        formData.append('payment_status', status);

        fetch('../partials/update-payment-info.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Failed to update payment: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }

    function cancelRequest(id, type) {
        promptModal('Provide a cancellation reason:', '', (reason) => {
            if (reason === null) return;
            const trimmedReason = reason.trim();
            if (!trimmedReason) {
                showToast('Cancellation reason is required.', 'error');
                return;
            }
            fetch('../partials/cancel-request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'id=' + encodeURIComponent(id) + '&type=' + encodeURIComponent(type) + '&reason=' + encodeURIComponent(trimmedReason)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showToast('Failed to cancel request: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(() => {
                showToast('Failed to cancel request. Please try again.', 'error');
            });
        });
    }
    </script>

    <!-- Custom Modal System -->
    <!-- Confirm Modal -->
    <div id="confirmModalOverlay" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 hidden" style="backdrop-filter:blur(2px)">
      <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4 transform transition-all">
        <div class="flex items-center space-x-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-question text-blue-600"></i>
          </div>
          <h3 class="text-base font-bold text-gray-800">Confirm Action</h3>
        </div>
        <p id="confirmModalMessage" class="text-sm text-gray-600 mb-6 leading-relaxed"></p>
        <div class="flex justify-end space-x-3">
          <button id="confirmModalCancel" class="px-5 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Cancel</button>
          <button id="confirmModalOk" class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">Confirm</button>
        </div>
      </div>
    </div>

    <!-- Prompt Modal -->
    <div id="promptModalOverlay" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 hidden" style="backdrop-filter:blur(2px)">
      <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4">
        <div class="flex items-center space-x-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-pen text-amber-600"></i>
          </div>
          <h3 class="text-base font-bold text-gray-800">Input Required</h3>
        </div>
        <p id="promptModalMessage" class="text-sm text-gray-600 mb-3"></p>
        <textarea id="promptModalInput" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Enter reason..."></textarea>
        <div class="flex justify-end space-x-3 mt-4">
          <button id="promptModalCancel" class="px-5 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Cancel</button>
          <button id="promptModalOk" class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">Submit</button>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 right-6 z-[9999] flex items-center space-x-3 px-5 py-3 rounded-xl shadow-2xl hidden transition-all duration-300 max-w-sm" style="min-width:260px">
      <span id="toastIcon" class="text-lg"></span>
      <span id="toastMessage" class="text-sm font-semibold"></span>
    </div>

    <script>
    /* =========================================================
       Custom Modal & Toast System
    ========================================================= */
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const icon  = document.getElementById('toastIcon');
        const msg   = document.getElementById('toastMessage');
        const isErr = type === 'error';
        toast.className = `fixed bottom-6 right-6 z-[9999] flex items-center space-x-3 px-5 py-3 rounded-xl shadow-2xl transition-all duration-300 max-w-sm ${isErr ? 'bg-red-600' : 'bg-green-600'} text-white`;
        icon.textContent  = isErr ? '✕' : '✓';
        msg.textContent   = message;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        toast.classList.remove('hidden');
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            setTimeout(() => toast.classList.add('hidden'), 300);
        }, 3000);
    }

    function confirmModal(messageHtml, onConfirm) {
        const overlay = document.getElementById('confirmModalOverlay');
        document.getElementById('confirmModalMessage').innerHTML = messageHtml;
        overlay.classList.remove('hidden');
        const ok     = document.getElementById('confirmModalOk');
        const cancel = document.getElementById('confirmModalCancel');
        const newOk     = ok.cloneNode(true);
        const newCancel = cancel.cloneNode(true);
        ok.parentNode.replaceChild(newOk, ok);
        cancel.parentNode.replaceChild(newCancel, cancel);
        newOk.addEventListener('click', () => { overlay.classList.add('hidden'); onConfirm(); });
        newCancel.addEventListener('click', () => overlay.classList.add('hidden'));
        overlay.addEventListener('click', function oc(e) {
            if (e.target === overlay) { overlay.classList.add('hidden'); overlay.removeEventListener('click', oc); }
        });
    }

    function promptModal(labelText, defaultValue, onSubmit) {
        const overlay = document.getElementById('promptModalOverlay');
        document.getElementById('promptModalMessage').textContent = labelText;
        const input = document.getElementById('promptModalInput');
        input.value = defaultValue || '';
        overlay.classList.remove('hidden');
        setTimeout(() => input.focus(), 50);
        const ok     = document.getElementById('promptModalOk');
        const cancel = document.getElementById('promptModalCancel');
        const newOk     = ok.cloneNode(true);
        const newCancel = cancel.cloneNode(true);
        ok.parentNode.replaceChild(newOk, ok);
        cancel.parentNode.replaceChild(newCancel, cancel);
        newOk.addEventListener('click', () => { overlay.classList.add('hidden'); onSubmit(input.value); });
        newCancel.addEventListener('click', () => { overlay.classList.add('hidden'); onSubmit(null); });
        overlay.addEventListener('click', function oc(e) {
            if (e.target === overlay) { overlay.classList.add('hidden'); onSubmit(null); overlay.removeEventListener('click', oc); }
        });
    }

    let deleteRequestId = null;
    let deleteRequestType = null;

    function deleteRequest(id, type) {
        deleteRequestId = id;
        deleteRequestType = type;
        const requestTypeText = type === 'document' ? 'document request' : 'business transaction';
        document.getElementById('deleteModalMessage').textContent =
            `Are you sure you want to delete this ${requestTypeText}? This action cannot be undone and all associated data will be permanently removed.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('deleteModalCancel').onclick = function() {
            document.getElementById('deleteModal').classList.add('hidden');
            deleteRequestId = null;
            deleteRequestType = null;
        };
        document.getElementById('deleteModalConfirm').onclick = function() {
            if (!deleteRequestId || !deleteRequestType) return;
            let url = '';
            if (deleteRequestType === 'document') {
                url = '../partials/delete-document-request.php?id=' + encodeURIComponent(deleteRequestId);
            } else {
                url = '../partials/delete-business-transaction.php?id=' + encodeURIComponent(deleteRequestId);
            }
            fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('deleteModal').classList.add('hidden');
                if (data.success) {
                    const rowId = `request-row-${deleteRequestType}-${deleteRequestId}`;
                    const row = document.getElementById(rowId);
                    if (row) row.remove();
                    showToast('Request has been successfully deleted.');
                } else {
                    showToast('Failed to delete request: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(() => {
                document.getElementById('deleteModal').classList.add('hidden');
                showToast('Failed to delete request. Please try again.', 'error');
            });
        };
    });
    </script>

</body>
</html>
 