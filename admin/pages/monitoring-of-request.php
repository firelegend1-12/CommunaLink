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

    // Fetch all requests
    $search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    
    // Base queries for both types of requests (details for Quick View: dr.details for docs, synthetic JSON for business)
    $doc_sql = "SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.status, 'document' as request_type, dr.details, dr.or_number, dr.payment_status 
                FROM document_requests dr 
                LEFT JOIN residents r ON dr.resident_id = r.id";

    $biz_sql = "SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.status, 'business' as request_type, 
                JSON_OBJECT('business_name', bt.business_name, 'business_type', bt.business_type, 'owner_name', bt.owner_name, 'address', bt.address, 'transaction_type', bt.transaction_type) as details, 
                bt.or_number, bt.payment_status 
                FROM business_transactions bt 
                LEFT JOIN residents r ON bt.resident_id = r.id";
    
    $params = [];
    if (!empty($search_query)) {
        $search_condition = " WHERE CONCAT(r.first_name, ' ', r.last_name) LIKE ?";
        $doc_sql .= $search_condition;
        $biz_sql .= $search_condition;
        $params[] = "%{$search_query}%";
    }

    // Since we are UNIONing, the search param needs to be duplicated if it exists
    $all_params = !empty($search_query) ? array_merge($params, $params) : [];

    // Combine queries
    $final_sql = "($doc_sql) UNION ALL ($biz_sql) ORDER BY date_requested DESC";

    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($all_params);
    $requests = $stmt->fetchAll();

} catch (PDOException $e) {
    $requests = [];
    $residents = [];
    $_SESSION['error_message'] = "A database error occurred: " . $e->getMessage();
}

// Document types for the form
$document_types = [
    'Barangay Clearance' => 50.00,
    'Certificate of Residency' => 50.00,
    'Certificate of Indigency' => 0.00,
    'Business Clearance' => 500.00,
];

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
        COALESCE((SELECT SUM(price) FROM document_requests WHERE payment_status = 'Paid' AND payment_date BETWEEN ? AND ?), 0) + 
        COALESCE((SELECT COUNT(*) * $biz_fee FROM business_transactions WHERE payment_status = 'Paid' AND payment_date BETWEEN ? AND ?), 0) as total");
    $q_revenue->execute([$start_of_day, $end_of_day, $start_of_day, $end_of_day]);
    $stats['revenue'] = $q_revenue->fetchColumn();

} catch (PDOException $e) {
    error_log("Stats Error: " . $e->getMessage());
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
        } 
    }">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Monitoring of Request</h1>
                        <!-- User Dropdown can go here -->
                    </div>
                </div>
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
                    <!-- Cards -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4">
                        <div class="bg-blue-50 p-3 rounded-xl"><i class="fas fa-file-invoice text-blue-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Queue Today</p>
                            <h4 class="text-2xl font-black text-gray-900"><?= number_format($stats['today_queue']) ?></h4>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4">
                        <div class="bg-amber-50 p-3 rounded-xl"><i class="fas fa-clock text-amber-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Workload</p>
                            <h4 class="text-2xl font-black text-gray-900"><?= number_format($stats['workload']) ?></h4>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4">
                        <div class="bg-green-50 p-3 rounded-xl"><i class="fas fa-check-double text-green-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Ready</p>
                            <h4 class="text-2xl font-black text-gray-900"><?= number_format($stats['ready']) ?></h4>
                        </div>
                    </div>
                    <div class="bg-white f rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center space-x-4">
                        <div class="bg-indigo-50 p-3 rounded-xl"><i class="fas fa-coins text-indigo-600 text-xl w-6 text-center"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Revenue Today</p>
                            <h4 class="text-2xl font-black text-gray-900">₱<?= number_format($stats['revenue'], 2) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-8">
                    <!-- Pending Requests Table -->
                    <div class="w-full">
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center">
                                <h2 class="text-xl font-bold text-gray-800">Pending Requests</h2>
                                <form action="monitoring-of-request.php" method="GET">
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-search"></i></span>
                                        <input type="text" name="search" class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    </div>
                                </form>
                            </div>
                            
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate Type</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sent</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($requests)): ?>
                                                <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No requests found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($requests as $req): 
                                                    $name = htmlspecialchars(($req['first_name'] ?? 'Unknown') . ' ' . ($req['last_name'] ?? 'Resident'));
                                                    $doc_type = htmlspecialchars($req['document_type']);
                                                    $date = date('M. d, Y h:i A', strtotime($req['date_requested']));
                                                    $status = trim((string)($req['status'] ?? ''));
                                                    $status = htmlspecialchars($status);
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
                                                ?>
                                                    <tr id="request-row-<?php echo $req['request_type']; ?>-<?php echo $req['id']; ?>">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $name; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo $doc_type; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <span class="status-badge <?php echo $status_bg; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                            <div class="mt-1">
                                                                <?php if (($req['payment_status'] ?? 'Unpaid') === 'Paid'): ?>
                                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                                                        <i class="fas fa-check-circle mr-1"></i> PAID (O.R. <?= htmlspecialchars($req['or_number'] ?? 'N/A') ?>)
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-50 text-gray-500 border border-gray-200">
                                                                        <i class="fas fa-clock mr-1"></i> UNPAID
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative" data-request-id="<?php echo $req['id']; ?>" data-request-type="<?php echo $req['request_type']; ?>" data-document-type="<?php echo htmlspecialchars($req['document_type']); ?>">
                                                            <div class="relative inline-block text-left" x-data="{ open: false, top: 0, left: 0 }">
                                                                <button type="button" x-ref="dropdownBtn" @click="
                                                                    open = !open;
                                                                    if (open) {
                                                                        const rect = $refs.dropdownBtn.getBoundingClientRect();
                                                                        top = rect.bottom + window.scrollY;
                                                                        left = rect.left + window.scrollX;
                                                                    }
                                                                " class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-200 focus:outline-none" aria-haspopup="true" aria-expanded="false">
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
                                                                            <!-- Print handled in Quick View -->
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
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
                                                                details: <?php echo $req['details'] ? htmlspecialchars(json_encode(json_decode($req['details'], true)), ENT_QUOTES, 'UTF-8') : 'null'; ?>
                                                            })" class="ml-2 inline-flex items-center justify-center w-8 h-8 rounded-full text-blue-600 hover:bg-blue-50 focus:outline-none" title="Quick View">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
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
                  <div class="px-4 py-6 bg-blue-600 sm:px-6">
                     <div class="flex items-start justify-between">
                        <h2 class="text-xl font-bold text-white" id="slide-over-title">Request Details</h2>
                        <div class="ml-3 h-7 flex items-center">
                           <button type="button" @click="viewPanelOpen = false" class="bg-blue-600 rounded-md text-blue-200 hover:text-white focus:outline-none focus:ring-2 focus:ring-white">
                              <span class="sr-only">Close panel</span>
                              <i class="fas fa-times text-xl"></i>
                           </button>
                        </div>
                     </div>
                     <div class="mt-1">
                        <p class="text-sm text-blue-200">Quick view of resident application information.</p>
                     </div>
                  </div>
                  <div class="relative flex-1 px-4 py-6 sm:px-6">
                     <!-- Content inside slider -->
                     <template x-if="selectedReq">
                        <div class="space-y-6">
                           <div>
                              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Requestor</h3>
                              <p class="mt-1 text-xl font-bold text-gray-900" x-text="selectedReq.name"></p>
                           </div>
                           <div class="grid grid-cols-2 gap-4">
                               <div>
                                  <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Document Type</h3>
                                  <p class="mt-1 text-base font-medium text-gray-900" x-text="selectedReq.docType"></p>
                               </div>
                               <div>
                                  <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Date Requested</h3>
                                  <p class="mt-1 text-sm text-gray-600" x-text="selectedReq.date"></p>
                               </div>
                           </div>
                           <div class="border-t border-gray-200 pt-4 mt-4">
                              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Current Status</h3>
                              <span :class="selectedReq.statusBg + ' px-3 py-1 rounded-full text-sm font-bold'" x-text="selectedReq.status"></span>
                           </div>

                           <template x-if="selectedReq.details">
                                <div class="border-t border-gray-200 pt-4 mt-4 bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Application Details</h3>
                                    <div class="space-y-4">
                                        <template x-for="(value, key) in selectedReq.details" :key="key">
                                            <div class="border-b border-gray-100 pb-2 last:border-0">
                                                <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-tighter" x-text="key.replace(/_/g, ' ')"></span>
                                                <p class="text-sm font-medium text-gray-800" x-text="value"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                           </template>
                           
                           <!-- Quick Approval Action -->
                           <div class="border-t border-gray-200 pt-6 mt-8" x-show="selectedReq.status === 'Pending'">
                               <h3 class="text-sm font-medium text-gray-900 mb-3">Quick Actions</h3>
                               <button @click="changeRequestStatus(selectedReq.id, selectedReq.type, 'Processing'); viewPanelOpen = false;" class="w-full bg-blue-600 text-white px-4 py-3 rounded-xl shadow hover:bg-blue-700 transition font-bold uppercase tracking-widest text-xs">Start Processing</button>
                           </div>

                           <!-- Printing Action -->
                           <div class="border-t border-gray-200 pt-6 mt-8" x-show="selectedReq.status !== 'Rejected' && selectedReq.status !== 'Cancelled'">
                               <h3 class="text-sm font-medium text-gray-900 mb-3">Document Generation</h3>
                               <button @click="const templates = {
                                   'Barangay Clearance': 'barangay-clearance-template.php',
                                   'Certificate of Residency': 'certificate-of-residency-template.php',
                                   'Certificate of Indigency': 'certificate-of-indigency-template.php',
                                   'business': 'business-clearance-template.php'
                               };
                               const templateFile = selectedReq.type === 'business' ? templates['business'] : (templates[selectedReq.docType] || 'barangay-clearance-template.php');
                               window.open(templateFile + '?id=' + selectedReq.id, '_blank');" 
                               class="w-full bg-blue-50 text-blue-700 border border-blue-100 py-3 rounded-xl text-sm font-black uppercase tracking-widest hover:bg-blue-100 transition flex items-center justify-center">
                                   <i class="fas fa-print mr-2 text-lg"></i>
                                   Print Certificate / Clearance
                               </button>
                               <p class="text-[10px] text-gray-400 mt-2 text-center">Form will open in a new tab with pre-filled resident data.</p>
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
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('monitoring-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });

    function changeRequestStatus(id, type, status) {
        if (!confirm('Are you sure you want to set status to ' + status + '?')) return;
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);

        const url = type === 'document' ? '../partials/update-document-request-status.php' : '../partials/update-transaction-status.php';
        
        fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update status: ' + (data.error || 'Unknown error'));
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                alert('Failed to update status. Please try again.');
            });
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
                alert('Failed to update payment: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    function cancelRequest(id, type) {
        const reason = prompt('Provide cancellation reason (required):');
        if (reason === null) return;

        const trimmedReason = reason.trim();
        if (!trimmedReason) {
            alert('Cancellation reason is required.');
            return;
        }

        if (!confirm('Are you sure you want to cancel this request?')) {
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
                alert('Failed to cancel request: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(() => {
            alert('Failed to cancel request. Please try again.');
        });
    }
    </script>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 hidden">
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-2 text-gray-800">Confirm Deletion</h2>
        <p id="deleteModalMessage" class="mb-6 text-gray-700"></p>
        <div class="flex justify-end space-x-2">
          <button id="deleteModalCancel" class="px-4 py-2 rounded bg-gray-200 text-gray-800 hover:bg-gray-300">Cancel</button>
          <button id="deleteModalConfirm" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">Delete</button>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 right-6 z-50 bg-green-600 text-white px-4 py-2 rounded shadow-lg hidden transition-opacity duration-300">
      Request has been successfully deleted.
    </div>

    <script>
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

    function showToast(message) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 1800);
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
                    // Remove the row from the table
                    const rowId = `request-row-${deleteRequestType}-${deleteRequestId}`;
                    const row = document.getElementById(rowId);
                    if (row) row.remove();
                    showToast('Request has been successfully deleted.');
                } else {
                    alert('Failed to delete request: ' + (data.error || 'Unknown error'));
                }
            })
            .catch((error) => {
                document.getElementById('deleteModal').classList.add('hidden');
                alert('Failed to delete request. Please try again.');
            });
        };
    });
    </script>
</body>
</html> 