<?php
/**
 * Business Monitoring Dashboard
 * - Track business compliance, renewals, and status
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = "Business Monitoring - CommuniLink";

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$expiry_filter = isset($_GET['expiry']) ? $_GET['expiry'] : '';

try {
    // Build query with filters
    $sql = "SELECT b.*, r.first_name, r.last_name, r.contact_no, 
                   DATEDIFF(b.permit_expiration_date, CURDATE()) as days_until_expiry,
                   CASE 
                       WHEN b.permit_expiration_date < CURDATE() THEN 'expired'
                       WHEN b.permit_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring_soon'
                       ELSE 'valid'
                   END as expiry_status
            FROM businesses b
            LEFT JOIN residents r ON b.resident_id = r.id
            WHERE b.status = 'Active'";
    
    $params = [];
    
    if ($status_filter) {
        $sql .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    if ($expiry_filter) {
        switch ($expiry_filter) {
            case 'expired':
                $sql .= " AND b.permit_expiration_date < CURDATE()";
                break;
            case 'expiring_soon':
                $sql .= " AND b.permit_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND b.permit_expiration_date >= CURDATE()";
                break;
            case 'valid':
                $sql .= " AND b.permit_expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    $sql .= " ORDER BY b.permit_expiration_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $businesses = $stmt->fetchAll();
    
    // Get statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_businesses,
            COUNT(CASE WHEN permit_expiration_date < CURDATE() THEN 1 END) as expired,
            COUNT(CASE WHEN permit_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND permit_expiration_date >= CURDATE() THEN 1 END) as expiring_soon,
            COUNT(CASE WHEN permit_expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as valid
        FROM businesses 
        WHERE status = 'Active'
    ");
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    $businesses = [];
    $stats = ['total_businesses' => 0, 'expired' => 0, 'expiring_soon' => 0, 'valid' => 0];
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Business Monitoring</h1>
                        
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md">
                        <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-building text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Businesses</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_businesses']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Valid Permits</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['valid']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Expiring Soon</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['expiring_soon']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-times-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Expired</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['expired']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Permit Status Overview</h3>
                    <div class="h-64">
                        <canvas id="permitChart"></canvas>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex flex-wrap items-center space-x-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status Filter</label>
                            <select id="statusFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Filter</label>
                            <select id="expiryFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="">All</option>
                                <option value="expired" <?php echo $expiry_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="expiring_soon" <?php echo $expiry_filter === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon (30 days)</option>
                                <option value="valid" <?php echo $expiry_filter === 'valid' ? 'selected' : ''; ?>>Valid</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button onclick="applyFilters()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Business List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Business Permits</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permit Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($businesses)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No businesses found matching the criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($businesses as $business): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($business['business_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($business['business_type']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($business['first_name'] . ' ' . $business['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($business['contact_no'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($business['permit_number'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo $business['permit_expiration_date'] ? date('M d, Y', strtotime($business['permit_expiration_date'])) : 'N/A'; ?>
                                                </div>
                                                <?php if ($business['days_until_expiry'] !== null): ?>
                                                    <div class="text-xs text-gray-500">
                                                        <?php 
                                                        if ($business['days_until_expiry'] < 0) {
                                                            echo abs($business['days_until_expiry']) . ' days expired';
                                                        } else {
                                                            echo $business['days_until_expiry'] . ' days remaining';
                                                        }
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $status_class = 'bg-green-100 text-green-800';
                                                $status_text = 'Valid';
                                                
                                                if ($business['expiry_status'] === 'expired') {
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    $status_text = 'Expired';
                                                } elseif ($business['expiry_status'] === 'expiring_soon') {
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    $status_text = 'Expiring Soon';
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="generate-business-permit.php?id=<?php echo $business['id']; ?>" 
                                                       class="text-blue-600 hover:text-blue-900">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <?php if ($business['expiry_status'] === 'expired' || $business['expiry_status'] === 'expiring_soon'): ?>
                                                        <button onclick="sendReminder(<?php echo $business['resident_id']; ?>)" 
                                                                class="text-yellow-600 hover:text-yellow-900">
                                                            <i class="fas fa-bell"></i> Remind
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Chart initialization
        const ctx = document.getElementById('permitChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Valid', 'Expiring Soon', 'Expired'],
                datasets: [{
                    data: [<?php echo $stats['valid']; ?>, <?php echo $stats['expiring_soon']; ?>, <?php echo $stats['expired']; ?>],
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value;
            const expiryFilter = document.getElementById('expiryFilter').value;
            
            let url = 'business-monitoring.php?';
            if (statusFilter) url += 'status=' + statusFilter + '&';
            if (expiryFilter) url += 'expiry=' + expiryFilter;
            
            window.location.href = url;
        }

        function sendReminder(residentId) {
            if (confirm('Send renewal reminder to this business owner?')) {
                // This would call an AJAX endpoint to send the reminder
                alert('Reminder sent successfully!');
            }
        }
    </script>
</body>
</html> 