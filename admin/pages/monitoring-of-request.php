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
    
    // Base queries for both types of requests
    $doc_sql = "SELECT dr.id, r.first_name, r.last_name, dr.document_type, dr.date_requested, dr.status, 'document' as request_type 
                FROM document_requests dr 
                JOIN residents r ON dr.resident_id = r.id";

    $biz_sql = "SELECT bt.id, r.first_name, r.last_name, bt.transaction_type as document_type, bt.application_date as date_requested, bt.status, 'business' as request_type 
                FROM business_transactions bt 
                JOIN residents r ON bt.resident_id = r.id";
    
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Walk-In Request Form -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2">Walk-In Request</h2>
                            <form action="../partials/walk-in-request-handler.php" method="POST" x-data="{ price: 0, types: <?php echo htmlspecialchars(json_encode($document_types), ENT_QUOTES, 'UTF-8'); ?> }">
                                <div class="space-y-4">
                                    <div>
                                        <label for="requestor_name" class="block text-sm font-medium text-gray-700">Requestor Name</label>
                                        <select id="requestor_name" name="resident_id" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                            <option value="" disabled selected>Select a resident...</option>
                                            <?php foreach ($residents as $resident): ?>
                                                <option value="<?php echo $resident['id']; ?>"><?php echo htmlspecialchars($resident['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="certificate_type" class="block text-sm font-medium text-gray-700">Certificate Type</label>
                                        <select id="certificate_type" name="document_type" x-on:change="price = types[$event.target.value] || 0" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                                            <option value="" disabled selected>Select a certificate...</option>
                                            <?php foreach ($document_types as $type => $cost): ?>
                                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose</label>
                                        <textarea id="purpose" name="purpose" rows="3" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required></textarea>
                                    </div>
                                    <div>
                                        <label for="date_requested" class="block text-sm font-medium text-gray-700">Date Requested</label>
                                        <input type="text" id="date_requested" value="<?php echo date('F j, Y'); ?>" class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly>
                                    </div>
                                    <div>
                                        <label for="price" class="block text-sm font-medium text-gray-700">Price</label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">₱</span>
                                            </div>
                                            <input type="text" name="price" id="price" x-model="price.toFixed(2)" class="block w-full pl-7 pr-12 py-2 sm:text-sm bg-gray-200 border border-gray-300 rounded-md" readonly>
                                        </div>
                                    </div>
                                    <div>
                                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Submit Request
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Pending Requests Table -->
                    <div class="lg:col-span-2">
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
                                                    $name = htmlspecialchars($req['first_name'] . ' ' . $req['last_name']);
                                                    $doc_type = htmlspecialchars($req['document_type']);
                                                    $date = date('M. d, Y h:i A', strtotime($req['date_requested']));
                                                    $status = trim((string)($req['status'] ?? ''));
                                                    $status = htmlspecialchars($status);
                                                    $status_bg = 'bg-yellow-100 text-yellow-800'; // Default for Pending
                                                    $status_text = $status ?: 'Pending';
                                                    $is_printable = false;
                                                    $print_url = '';

                                                    if (strcasecmp($status, 'Processing') === 0) {
                                                        $status_bg = 'bg-blue-100 text-blue-800';
                                                        $status_text = 'Processing';
                                                    } elseif (
                                                        strcasecmp($status, 'Ready for Pickup') === 0 ||
                                                        strcasecmp($status, 'READY FOR PICKUP') === 0 ||
                                                        strcasecmp($status, 'READY') === 0 ||
                                                        strcasecmp($status, 'Completed') === 0
                                                    ) {
                                                        $status_bg = 'bg-blue-100 text-blue-800';
                                                        $status_text = 'Ready for Pickup';
                                                    } elseif (strcasecmp($status, 'Rejected') === 0) {
                                                        $status_bg = 'bg-red-100 text-red-800';
                                                        $status_text = 'Rejected';
                                                    } elseif (strcasecmp($status, 'APPROVED') === 0) {
                                                        $status_bg = 'bg-green-100 text-green-800';
                                                        $status_text = 'Approved';
                                                    } elseif (strcasecmp($status, 'Pending') === 0) {
                                                        $status_bg = 'bg-yellow-100 text-yellow-800';
                                                        $status_text = 'Pending';
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
                                                                            <!-- Status change options -->
                                                                            <?php if ($req['request_type'] === 'document'): ?>
                                                                                <?php
                                                                                $doc_statuses = ["Pending", "Processing", "Ready for Pickup", "Approved", "Rejected"];
                                                                                foreach ($doc_statuses as $opt):
                                                                                    if (strcasecmp($status, $opt) !== 0): ?>
                                                                                        <button type="button" onclick="changeRequestStatus('<?php echo $req['id']; ?>', 'document', '<?php echo $opt; ?>')" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 <?php echo $opt === 'Rejected' ? 'text-red-600' : ($opt === 'Approved' ? 'text-green-700' : 'text-gray-700'); ?>">Set as <?php echo $opt; ?></button>
                                                                                <?php endif;
                                                                                endforeach; ?>
                                                                            <?php elseif ($req['request_type'] === 'business'): ?>
                                                                                <?php
                                                                                $biz_statuses = ["PENDING", "PROCESSING", "READY FOR PICKUP", "APPROVED", "REJECTED"];
                                                                                foreach ($biz_statuses as $opt):
                                                                                    if (strcasecmp($status, $opt) !== 0): ?>
                                                                                        <button type="button" onclick="changeRequestStatus('<?php echo $req['id']; ?>', 'business', '<?php echo $opt; ?>')" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 <?php echo $opt === 'REJECTED' ? 'text-red-600' : ($opt === 'APPROVED' ? 'text-green-700' : 'text-gray-700'); ?>">Set as <?php echo ucwords(strtolower($opt)); ?></button>
                                                                                <?php endif;
                                                                                endforeach; ?>
                                                                            <?php endif; ?>
                                                                            <!-- Delete option -->
                                                                            <div class="border-t border-gray-100 my-1"></div>
                                                                            <button type="button" onclick="deleteRequest('<?php echo $req['id']; ?>', '<?php echo $req['request_type']; ?>')" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete Request</button>
                                                                            <?php if ($is_printable): ?>
                                                                                <a href="<?php echo $print_url; ?>" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50" target="_blank">Print</a>
                                                                            <?php endif; ?>
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
                                                                details: <?php echo $req['details'] ? json_encode(json_decode($req['details'], true)) : 'null'; ?>
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
                           <div class="border-t border-gray-200 pt-6 mt-8" x-show="selectedReq.status === 'Pending' || selectedReq.status === 'PENDING'">
                               <h3 class="text-sm font-medium text-gray-900 mb-3">Quick Actions</h3>
                               <div class="flex space-x-3">
                                   <!-- For documents -->
                                   <template x-if="selectedReq.type === 'document'">
                                        <button @click="changeRequestStatus(selectedReq.id, 'document', 'Processing'); viewPanelOpen = false;" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">Start Processing</button>
                                   </template>
                                   <!-- For businesses -->
                                   <template x-if="selectedReq.type === 'business'">
                                        <button @click="changeRequestStatus(selectedReq.id, 'business', 'PROCESSING'); viewPanelOpen = false;" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">Start Processing</button>
                                   </template>
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
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('monitoring-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });

    function changeRequestStatus(id, type, status) {
        let url = '';
        if (type === 'document') {
            url = '../partials/update-document-request-status.php?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status);
        } else {
            url = '../partials/update-transaction-status.php?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status);
        }
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
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