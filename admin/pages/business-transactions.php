<?php
/**
 * Business Transactions Management Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Business Transactions - CommuniLink";

try {
    // Handle search
    $search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $sql = "SELECT * FROM business_transactions WHERE status IS NOT NULL AND status != '' AND status != 'DELETED'";
    $params = [];

    if (!empty($search_query)) {
        $sql .= " AND (owner_name LIKE ? OR business_name LIKE ?)";
        $search_param = "%{$search_query}%";
        $params = [$search_param, $search_param];
    }
    $sql .= " ORDER BY application_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    $transactions = [];
    // error_log("Business transactions page DB error: " . $e->getMessage());
    $_SESSION['error_message'] = "A database error occurred while fetching transactions.";
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
    <div class="flex h-screen">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Business Applications</h1>

                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div id="business-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                    echo '<p>' . htmlspecialchars($_SESSION['success_message']) . '</p>';
                    echo '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo display_error($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                }
                ?>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center">
                        <div class="flex-grow w-full sm:w-auto mb-2 sm:mb-0 sm:mr-4">
                            <form action="business-transactions.php" method="GET">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-search"></i></span>
                                    <input type="text" name="search" id="search" class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </form>
                        </div>
                        <div class="flex items-center space-x-2">
                             <a href="business-application-form.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-plus mr-2"></i> New Form
                            </a>
                             <a href="business-clearance-template.php" target="_blank" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-download mr-2"></i> Business Clearance Form
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant Details</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Applied</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($transactions)): ?>
                                        <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No transactions found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $trans): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($trans['owner_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($trans['address']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($trans['business_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('F j, Y, g:i a', strtotime($trans['application_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php
                                                    $status = $trans['status'];
                                                    $status_label = $status;
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    if ($status === 'APPROVED') {
                                                        $status_class = 'bg-green-100 text-green-800';
                                                        $status_label = 'Approved';
                                                    } elseif ($status === 'REJECTED') {
                                                        $status_class = 'bg-red-100 text-red-800';
                                                        $status_label = 'Rejected';
                                                    } elseif ($status === 'READY FOR PICKUP' || $status === 'READY') {
                                                        $status_class = 'bg-blue-100 text-blue-800';
                                                        $status_label = 'Ready for Pickup';
                                                    } elseif ($status === 'PROCESSING') {
                                                        $status_class = 'bg-yellow-200 text-yellow-900';
                                                        $status_label = 'Processing';
                                                    } elseif ($status === 'PENDING') {
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        $status_label = 'Pending';
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo htmlspecialchars($status_label); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative">
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
                                                                 class="fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                                                 :style="'top: ' + top + 'px; left: ' + left + 'px;'">
                                                                <div class="py-1">
                                                                    <button type="button" onclick="viewTransactionDetails('<?php echo $trans['id']; ?>', '<?php echo htmlspecialchars($trans['business_name']); ?>', '<?php echo htmlspecialchars($trans['owner_name']); ?>', '<?php echo htmlspecialchars($trans['business_type']); ?>', '<?php echo htmlspecialchars($trans['address']); ?>', '<?php echo htmlspecialchars($trans['status']); ?>', '<?php echo $trans['application_date']; ?>')" class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">View Details</button>
                                                                    <button type="button" onclick="changeTransactionStatus('<?php echo $trans['id']; ?>', 'APPROVED')" class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">Set as Approved</button>
                                                                    <button type="button" onclick="changeTransactionStatus('<?php echo $trans['id']; ?>', 'REJECTED')" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">Set as Rejected</button>
                                                                    <?php if ($trans['status'] === 'APPROVED'): ?>
                                                                    <a href="generate-business-permit.php?id=<?php echo $trans['id']; ?>" class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Generate Permit</a>
                                                                    <?php endif; ?>
                                                                    <button type="button" onclick="changeTransactionStatus('<?php echo $trans['id']; ?>', 'DELETED')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50">Delete</button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('business-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });

    function viewTransactionDetails(id, businessName, ownerName, businessType, address, status, applicationDate) {
        const formattedDate = new Date(applicationDate).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        });
        
        const statusColor = status === 'APPROVED' ? 'green' : (status === 'REJECTED' ? 'red' : 'yellow');
        
        const details = `
Business Transaction Details

ID: ${id}
Business Name: ${businessName}
Owner: ${ownerName}
Business Type: ${businessType}
Address: ${address}
Status: ${status}
Application Date: ${formattedDate}
        `;
        
        alert(details);
    }

    function changeTransactionStatus(id, status) {
        // Remove confirmation for delete action
        fetch('../partials/update-transaction-status.php?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status), {
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
            .catch(() => {
                alert('Failed to update status.');
            });
    }

    // Live search for business transactions
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const tableBody = document.querySelector('tbody.bg-white');
        let lastValue = searchInput.value;
        let controller = null;

        function renderTransactions(transactions) {
            if (!transactions.length) {
                tableBody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No transactions found.</td></tr>`;
                return;
            }
            tableBody.innerHTML = transactions.map(trans => `
                <tr>
                    <td class=\"px-6 py-4 whitespace-nowrap\">
                        <div class=\"text-sm font-medium text-gray-900\">${trans.owner_name}</div>
                        <div class=\"text-sm text-gray-500\">${trans.address ?? ''}</div>
                    </td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\">${trans.business_name}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${trans.application_date ? new Date(trans.application_date).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : ''}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm\"><span class=\"px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${trans.status === 'APPROVED' ? 'bg-green-100 text-green-800' : (trans.status === 'REJECTED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')}\">${trans.status}</span></td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm font-medium relative\">
                        <div class=\"relative inline-block text-left\" x-data=\"{ open: false, top: 0, left: 0 }\">
                            <button type=\"button\" x-ref=\"dropdownBtn\" @click=\"
                                open = !open;
                                if (open) {
                                    const rect = $refs.dropdownBtn.getBoundingClientRect();
                                    top = rect.bottom + window.scrollY;
                                    left = rect.left + window.scrollX;
                                }
                            \" class=\"flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-200 focus:outline-none\" aria-haspopup=\"true\" aria-expanded=\"false\">
                                <svg class=\"w-5 h-5 text-gray-500\" fill=\"currentColor\" viewBox=\"0 0 20 20\"><circle cx=\"4\" cy=\"10\" r=\"1.5\"/><circle cx=\"10\" cy=\"10\" r=\"1.5\"/><circle cx=\"16\" cy=\"10\" r=\"1.5\"/></svg>
                            </button>
                            <template x-teleport=\"body\">
                                <div x-show=\"open\" @click.away=\"open = false\" x-cloak class=\"fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5\" :style=\"'top: ' + top + 'px; left: ' + left + 'px;'\">
                                    <div class=\"py-1\">
                                        <button type=\"button\" onclick=\"viewTransactionDetails('${trans.id}', '${trans.business_name}', '${trans.owner_name}', '${trans.business_type || ''}', '${trans.address || ''}', '${trans.status}', '${trans.application_date}')\" class=\"block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50\">View Details</button>
                                        <button type=\"button\" onclick=\"changeTransactionStatus('${trans.id}', 'APPROVED')\" class=\"block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50\">Set as Approved</button>
                                        <button type=\"button\" onclick=\"changeTransactionStatus('${trans.id}', 'REJECTED')\" class=\"block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50\">Set as Rejected</button>
                                        <button type=\"button\" onclick=\"changeTransactionStatus('${trans.id}', 'DELETED')\" class=\"block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50\">Delete</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        searchInput.addEventListener('input', function() {
            const value = searchInput.value;
            if (value === lastValue) return;
            lastValue = value;
            if (controller) controller.abort();
            controller = new AbortController();
            fetch(`../partials/search-business-transactions.php?search=${encodeURIComponent(value)}`, { signal: controller.signal })
                .then(res => res.json())
                .then(data => renderTransactions(data))
                .catch(() => {});
        });
    });
    </script>
</body>
</html> 