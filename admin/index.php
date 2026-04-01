<?php
/**
 * Admin Dashboard
 * Main landing page after successful login
 */

// Include admin authentication and session management
require_once 'partials/admin_auth.php';

// Page-specific requirements
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/cache_manager.php';
require_once '../includes/csrf.php';

// Page title
$page_title = "Dashboard - CommuniLink";
$permit_check_csrf_token = csrf_token();

// Initialize cache manager (Using file driver for Free Tier compatibility)
init_cache_manager(['cache_dir' => '../cache/']);

$cache_key = 'admin_dashboard_stats';
// Try fetching from file cache
$cached_data = cache_get($cache_key, 'file');

if ($cached_data) {
    // Extract variables into current symbol table
    extract($cached_data);
} else {
    try {
        // Fetch latest transactions with selected columns only
        $stmt = $pdo->query("SELECT id, transaction_type, owner_name, application_date, status FROM business_transactions WHERE status IS NOT NULL AND status != '' AND status != 'DELETED' ORDER BY application_date DESC LIMIT 4");
        $latest_transactions = $stmt->fetchAll();

        // Fetch population stats
        $stmt = $pdo->query("SELECT 
                        COUNT(*) as total_population, 
                        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count
                      FROM residents");
        $population_stats = $stmt->fetch();

        // Fetch age group data for chart
        $stmt = $pdo->query("SELECT 
            CASE
                WHEN age BETWEEN 0 AND 12 THEN '0-12 (Kids)'
                WHEN age BETWEEN 13 AND 19 THEN '13-19 (Teens)'
                WHEN age BETWEEN 20 AND 39 THEN '20-39 (Young Adults)'
                WHEN age BETWEEN 40 AND 59 THEN '40-59 (Adults)'
                ELSE '60+ (Seniors)'
            END AS age_group,
            COUNT(*) AS count
        FROM residents
        GROUP BY age_group
        ORDER BY age_group");
        $age_group_data = $stmt->fetchAll();
        $age_labels = json_encode(array_column($age_group_data, 'age_group'));
        $age_counts = json_encode(array_column($age_group_data, 'count'));

        // Generate last 12 months labels
        $month_labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $month_labels[] = date('M Y', strtotime("-{$i} months"));
        }
        // Residents added per month (last 12 months)
        $resident_counts_map = array_fill_keys($month_labels, 0);
        try {
            $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count FROM residents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $resident_counts_map[$row['month']] = (int)$row['count'];
            }
        } catch (PDOException $e) {}
        $resident_months = json_encode(array_keys($resident_counts_map));
        $resident_counts = json_encode(array_values($resident_counts_map));
        // Businesses added per month (last 12 months)
        $business_counts_map = array_fill_keys($month_labels, 0);
        try {
            $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count FROM businesses WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $business_counts_map[$row['month']] = (int)$row['count'];
            }
        } catch (PDOException $e) {}
        $business_months = json_encode(array_keys($business_counts_map));
        $business_counts = json_encode(array_values($business_counts_map));

        // Fetch quick stats for dashboard cards in one query
        $stats_stmt = $pdo->query("SELECT
            (SELECT COUNT(*) FROM businesses) AS business_count,
            (SELECT COUNT(*) FROM document_requests WHERE status = 'Pending') AS pending_doc_requests,
            (SELECT COUNT(*) FROM business_transactions WHERE status = 'Pending') AS pending_biz_requests,
            (SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')) AS active_incidents,
            (SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()) AS upcoming_events,
            (SELECT COUNT(*) FROM businesses WHERE DATEDIFF(permit_expiration_date, CURDATE()) BETWEEN 0 AND 30 AND status IN ('Active', 'Pending')) AS expiring_soon,
            (SELECT COUNT(*) FROM businesses WHERE permit_expiration_date < CURDATE() AND status IN ('Active', 'Pending')) AS expired_permits");
        $dashboard_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $business_count = (int)($dashboard_stats['business_count'] ?? 0);
        $pending_doc_requests = (int)($dashboard_stats['pending_doc_requests'] ?? 0);
        $pending_biz_requests = (int)($dashboard_stats['pending_biz_requests'] ?? 0);
        $pending_requests = $pending_doc_requests + $pending_biz_requests;
        $active_incidents = (int)($dashboard_stats['active_incidents'] ?? 0);
        $upcoming_events = (int)($dashboard_stats['upcoming_events'] ?? 0);
        $expiring_soon = (int)($dashboard_stats['expiring_soon'] ?? 0);
        $expired_permits = (int)($dashboard_stats['expired_permits'] ?? 0);

        $next_expiry_stmt = $pdo->query("SELECT permit_expiration_date FROM businesses WHERE permit_expiration_date >= CURDATE() AND status IN ('Active', 'Pending') ORDER BY permit_expiration_date ASC LIMIT 1");
        $next_expiry = $next_expiry_stmt ? $next_expiry_stmt->fetchColumn() : null;

        $stats_to_cache = compact(
            'latest_transactions', 'population_stats', 'age_group_data',
            'age_labels', 'age_counts', 'resident_months', 'resident_counts',
            'business_months', 'business_counts', 'business_count',
            'pending_requests', 'active_incidents', 'upcoming_events',
            'expiring_soon', 'expired_permits', 'next_expiry'
        );
        cache_set($cache_key, $stats_to_cache, 900, 'file'); // 15 mins cache
        
    } catch (PDOException $e) {
        // On error, set default values to prevent page crash
        $latest_transactions = [];
        $population_stats = ['total_population' => 0, 'male_count' => 0, 'female_count' => 0];
        $age_labels = '[]';
        $age_counts = '[]';
        $business_count = 0;
        $pending_requests = 0;
        $active_incidents = 0;
        $upcoming_events = 0;
        $expiring_soon = 0;
        $expired_permits = 0;
        $next_expiry = null;
        // error_log("Dashboard DB Error: " . $e->getMessage());
    }
}

// Function to get sunset time
function getSunsetTime() {
    $cache_key = 'sunset_time_' . date('Y-m-d');
    $cached = cache_get($cache_key, 'file');
    if ($cached) return $cached;

    // Get sunset time for Philippines (default to Manila)
    $lat = 14.5995; // Manila latitude
    $lon = 120.9842; // Manila longitude
    
    // Get sunset time
    $sun_info = date_sun_info(time(), $lat, $lon);
    $sunset_timestamp = $sun_info['sunset'];
    
    // Calculate time until sunset
    $time_until_sunset = $sunset_timestamp - time();
    
    if ($time_until_sunset < 0) {
        $tomorrow = time() + 86400; // 86400 seconds = 1 day
        $sun_info = date_sun_info($tomorrow, $lat, $lon);
        $sunset_timestamp = $sun_info['sunset'];
        $time_until_sunset = $sunset_timestamp - time();
    }
    
    // Format the time until sunset
    $hours = floor($time_until_sunset / 3600);
    $minutes = floor(($time_until_sunset % 3600) / 60);
    
    $result = [
        'sunset_time' => date('h:i A', $sunset_timestamp),
        'hours_until' => $hours,
        'minutes_until' => $minutes
    ];
    
    cache_set($cache_key, $result, 3600, 'file'); // Cache for 1 hour
    return $result;
}

// Get sunset information
$sunset_info = getSunsetTime();

// Array of inspirational quotes
$quotes = [
    "Leadership is not about being in charge. It is about taking care of those in your charge. - Simon Sinek",
    "The greatest glory in living lies not in never falling, but in rising every time we fall. - Nelson Mandela",
    "The way to get started is to quit talking and begin doing. - Walt Disney",
    "If you look at what you have in life, you'll always have more. If you look at what you don't have in life, you'll never have enough. - Oprah Winfrey",
    "Spread love everywhere you go. Let no one ever come to you without leaving happier. - Mother Teresa",
    "Service to others is the rent you pay for your room here on earth. - Muhammad Ali",
    "The best way to find yourself is to lose yourself in the service of others. - Mahatma Gandhi",
    "The purpose of our lives is to be happy. - Dalai Lama",
    "Life is what happens when you're busy making other plans. - John Lennon"
];

// Get a random quote
$today_quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.min.css?v=<?= filemtime('../assets/css/admin-dashboard.min.css') ?>">
    <script>
        function adminShowToast(message, type = 'info') {
            const existing = document.getElementById('admin-toast-container');
            const container = existing || (() => {
                const el = document.createElement('div');
                el.id = 'admin-toast-container';
                el.style.position = 'fixed';
                el.style.top = '16px';
                el.style.right = '16px';
                el.style.zIndex = '9999';
                el.style.display = 'flex';
                el.style.flexDirection = 'column';
                el.style.gap = '10px';
                document.addEventListener('DOMContentLoaded', () => document.body.appendChild(el), { once: true });
                return el;
            })();

            const toast = document.createElement('div');
            const palette = {
                success: { bg: '#ecfdf3', border: '#16a34a', text: '#166534' },
                error: { bg: '#fef2f2', border: '#dc2626', text: '#991b1b' },
                info: { bg: '#eff6ff', border: '#2563eb', text: '#1d4ed8' }
            };
            const colors = palette[type] || palette.info;
            toast.style.background = colors.bg;
            toast.style.borderLeft = `4px solid ${colors.border}`;
            toast.style.color = colors.text;
            toast.style.padding = '10px 12px';
            toast.style.borderRadius = '8px';
            toast.style.boxShadow = '0 8px 20px rgba(15, 23, 42, 0.12)';
            toast.style.minWidth = '240px';
            toast.style.maxWidth = '360px';
            toast.style.fontSize = '13px';
            toast.style.fontWeight = '600';
            toast.textContent = message;

            container.appendChild(toast);
            setTimeout(() => {
                toast.style.transition = 'opacity 0.25s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 260);
            }, 3200);
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include 'partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center">
                            <h1 class="text-3xl font-bold text-gray-800 mr-10">Dashboard</h1>
                            <form onsubmit="if (typeof adminShowToast === 'function') { adminShowToast('Global Search feature coming soon!', 'info'); } return false;" class="hidden md:flex relative">
                                <input type="text" name="q" placeholder="Search Resident or Transaction ID..." class="bg-gray-100 text-sm rounded-full pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white w-80 lg:w-[400px] transition-all border border-transparent focus:border-blue-300">
                                <i class="fas fa-search absolute left-4 top-2.5 text-gray-400"></i>
                            </form>
                        </div>
                        <div class="flex items-center space-x-6">
                            <!-- Digital Clock inline -->
                            <div class="hidden lg:flex items-center text-gray-500 font-medium text-sm">
                                <i class="far fa-clock mr-2 text-blue-500"></i>
                                <span id="header-time"><?= date('h:i A') ?></span>
                                <span class="mx-2">|</span>
                                <span><?= date('M d, Y') ?></span>
                            </div>
                            
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
                                
                                <a href="pages/account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <!-- Quick Stats Cards Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    <!-- Pending Requests Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl border-l-4 border-yellow-500">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Pending Requests</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-yellow-100 flex items-center justify-center -translate-x-1 border border-yellow-200">
                                <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-yellow-700" id="pending-requests-count"><?php echo $pending_requests; ?></h2>
                                <p class="text-xs text-gray-500 font-medium">Action Required</p>
                            </div>
                        </div>
                    </div>
                    <!-- Active Incidents Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl border-l-4 border-red-500">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Active Incidents</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-red-100 flex items-center justify-center -translate-x-1 border border-red-200">
                                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-red-700" id="active-incidents-count"><?php echo $active_incidents; ?></h2>
                                <p class="text-xs text-gray-500 font-medium">Unresolved Reports</p>
                            </div>
                        </div>
                    </div>
                    <!-- Total Residents Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Residents</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-blue-700" id="population-count"><?php echo $population_stats['total_population'] ?? '0'; ?></h2>
                                <p class="text-xs text-gray-500">Total Registered</p>
                            </div>
                        </div>
                    </div>
                    <!-- Total Businesses Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Businesses</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-store text-green-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-green-700" id="business-count"><?php echo $business_count; ?></h2>
                                <p class="text-xs text-gray-500">Registered Operations</p>
                            </div>
                        </div>
                    </div>
                    <!-- Upcoming Events Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Events</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-purple-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-purple-700" id="upcoming-events-count"><?php echo $upcoming_events; ?></h2>
                                <p class="text-xs text-gray-500">Scheduled Events</p>
                            </div>
                        </div>
                    </div>
                    <!-- Permit Expiry Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl border-l-4 <?php echo ($expired_permits > 0) ? 'border-red-500' : 'border-orange-500'; ?>">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Permit Status</h3>
                            <button id="check-expiry-btn" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded-md text-xs transition" title="Run expiry check now">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full <?php echo ($expired_permits > 0) ? 'bg-red-100' : 'bg-orange-100'; ?> flex items-center justify-center border <?php echo ($expired_permits > 0) ? 'border-red-200' : 'border-orange-200'; ?>">
                                <i class="fas fa-certificate <?php echo ($expired_permits > 0) ? 'text-red-600' : 'text-orange-600'; ?> text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold <?php echo ($expired_permits > 0) ? 'text-red-700' : 'text-orange-700'; ?>" id="expiring-soon-count"><?php echo $expiring_soon + $expired_permits; ?></h2>
                                <p class="text-xs text-gray-500"><?php echo ($expired_permits > 0) ? "⚠️ $expired_permits Expired!" : 'Expiring Soon'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column (2/3 width on large screens) -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Transactions Section -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-lg font-medium text-gray-800">Transactions</h2>
                                <a href="pages/business-application-form.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create
                                </a>
                            </div>
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200" id="transactions-table-body">
                                            <?php if (empty($latest_transactions)): ?>
                                                <tr>
                                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                                        No recent transactions found.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($latest_transactions as $trans): ?>
                                                    <tr class="hover:bg-gray-50 cursor-pointer transition duration-150" onclick="window.location.href='pages/monitoring-of-request.php?type=business'">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($trans['id']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($trans['transaction_type']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($trans['owner_name']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($trans['application_date'])); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                <?php echo $trans['status'] === 'Approved' ? 'bg-green-100 text-green-800' : ($trans['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                                <?php echo htmlspecialchars($trans['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Population Chart -->
                        <div class="grid grid-cols-2 gap-6">
                        <div class="bg-white rounded-lg shadow">
                                <div class="px-4 py-2 border-b border-gray-200">
                                    <h2 class="text-base font-semibold text-gray-800">Population by Age Group</h2>
                                </div>
                                <div class="p-4" style="height: 420px;">
                                    <canvas id="ageGroupChart" style="width:100%; height:400px;"></canvas>
                            </div>
                            </div>
                        <!-- Residents & Businesses Added per Month Charts -->
                            <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-800">Residents & Businesses Added per Month</h2>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="w-full" style="height:300px;">
                                    <h3 class="text-sm font-semibold text-blue-700 mb-2 flex items-center"><i class="fas fa-users mr-2"></i>Residents Added</h3>
                                    <canvas id="residentsPerMonthChart" class="w-full h-full"></canvas>
                                </div>
                                <div class="w-full" style="height:300px;">
                                    <h3 class="text-sm font-semibold text-green-700 mb-2 flex items-center"><i class="fas fa-store mr-2"></i>Businesses Added</h3>
                                    <canvas id="businessesPerMonthChart" class="w-full h-full"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column (1/3 width on large screens) -->
                    <div class="space-y-6">
                        <!-- Quick Actions -->
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-bolt text-yellow-500 text-lg"></i>
                                <h3 class="ml-2 text-base font-medium text-gray-800">Quick Actions</h3>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="pages/add-resident.php" class="flex items-center p-2 bg-blue-50 hover:bg-blue-100 rounded transition group">
                                    <i class="fas fa-user-plus text-blue-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-blue-700">Add Resident</span>
                                </a>
                                <a href="pages/business-application-form.php" class="flex items-center p-2 bg-green-50 hover:bg-green-100 rounded transition group">
                                    <i class="fas fa-store text-green-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-green-700">Add Business</span>
                                </a>
                                <a href="pages/announcements.php" class="flex items-center p-2 bg-purple-50 hover:bg-purple-100 rounded transition group">
                                    <i class="fas fa-bullhorn text-purple-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-purple-700">Announcement</span>
                                </a>
                                <a href="pages/incident-reports.php" class="flex items-center p-2 bg-red-50 hover:bg-red-100 rounded transition group">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-red-700">Report Incident</span>
                                </a>
                                <a href="pages/monitoring-of-request.php?tab=business" class="flex items-center p-2 bg-yellow-50 hover:bg-yellow-100 rounded transition group">
                                    <i class="fas fa-certificate text-yellow-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-yellow-700">Permits</span>
                                </a>
                                <a href="pages/events.php" class="flex items-center p-2 bg-indigo-50 hover:bg-indigo-100 rounded transition group">
                                    <i class="fas fa-calendar-plus text-indigo-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-indigo-700">Create Event</span>
                                </a>
                            </div>
                        </div>

                        <!-- Today's Quote -->
                        <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg shadow p-6 text-white">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-quote-left text-white text-opacity-50 text-2xl"></i>
                                <h3 class="ml-2 text-lg font-medium">Today's Quote</h3>
                            </div>
                            <p class="text-white text-opacity-90 italic mb-4">"<?php echo $today_quote; ?>"</p>
                        </div>
                        
                        <!-- Sunset Info -->
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex justify-between items-center text-sm mb-2 opacity-80">
                                <span><i class="fas fa-sun text-yellow-400 mr-2"></i>Sunset in Oton:</span>
                                <span class="font-bold"><?= $sunset_info['sunset_time'] ?></span>
                            </div>
                            <div class="w-full bg-gray-600 rounded-full h-2">
                                <div class="bg-gradient-to-r from-yellow-400 to-orange-500 h-2 rounded-full" style="width: <?= min(100, max(0, 100 - ($sunset_info['hours_until'] * 8))) ?>%"></div>
                            </div>
                            <p class="text-xs text-right mt-2 text-gray-400"><?= $sunset_info['hours_until'] ?> hr <?= $sunset_info['minutes_until'] ?> min left of daylight</p>
                        </div>

                        <!-- Upcoming Events & Reminders -->
                        <?php
                        // Fetch next 5 upcoming events
                        try {
                            $stmt = $pdo->prepare("SELECT event_date, event_name, event_description FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
                            $stmt->execute();
                            $upcoming_events_list = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            $upcoming_events_list = [];
                        }
                        ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-calendar-alt text-purple-500 text-2xl"></i>
                                <h3 class="ml-2 text-lg font-medium text-gray-800">Upcoming Events & Reminders</h3>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                <?php if (empty($upcoming_events_list)): ?>
                                    <li class="py-3 text-gray-500 text-sm">No upcoming events or reminders.</li>
                                <?php else: ?>
                                    <?php foreach ($upcoming_events_list as $event): ?>
                                        <li class="py-3 flex items-start space-x-3">
                                            <span class="flex-shrink-0 mt-1"><i class="fas fa-calendar-day text-purple-400"></i></span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                                <div class="text-xs text-gray-500 mt-1 flex items-center">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                                </div>
                                                <?php if (!empty($event['event_description'])): ?>
                                                    <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($event['event_description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
    window.dashboardData = {
        ageLabels: <?= $age_labels ?: '[]' ?>,
        ageCounts: <?= $age_counts ?: '[]' ?>,
        residentsMonths: <?= $resident_months ?: '[]' ?>,
        residentsCounts: <?= $resident_counts ?: '[]' ?>,
        businessMonths: <?= $business_months ?: '[]' ?>,
        businessCounts: <?= $business_counts ?: '[]' ?>
    };
    window.permitCheckCsrfToken = <?= json_encode($permit_check_csrf_token) ?>;
    </script>
    <script>
    // Permit Expiry Checker
    async function checkExpiringPermits() {
        try {
            const formData = new FormData();
            formData.append('csrf_token', window.permitCheckCsrfToken || '');

            const response = await fetch('../api/check-expiring-permits.php', {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                throw new Error(`Permit check failed with status ${response.status}`);
            }
            const data = await response.json();
            
            if (data.status === 'success') {
                // Update card with results
                const expiringCount = (data.checks.expiring_30_days?.count || 0) + 
                                    (data.checks.expiring_7_days?.count || 0) + 
                                    (data.checks.expiring_1_day?.count || 0) +
                                    (data.checks.marked_expired?.count || 0);
                                    
                document.getElementById('expiring-soon-count').textContent = expiringCount;
                
                // Show notification badge if there are expiry issues
                if (data.checks.marked_expired?.count > 0 || data.checks.expiring_1_day?.count > 0) {
                    const card = document.querySelector('[id="expiring-soon-count"]').closest('div').closest('div').closest('div');
                    if (!card.classList.contains('border-red-500')) {
                        card.classList.remove('border-orange-500', 'bg-white');
                        card.classList.add('border-red-500', 'bg-red-50');
                    }
                }
            }
        } catch (error) {
            console.error('Error checking expiring permits:', error);
        }
    }
    
    // Run permit check on page load and add button listener
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-check on load
        checkExpiringPermits();
        
        // Add click handler to manual check button
        const checkBtn = document.getElementById('check-expiry-btn');
        if (checkBtn) {
            checkBtn.addEventListener('click', function() {
                const icon = checkBtn.querySelector('i');
                icon.classList.add('animate-spin');
                
                checkExpiringPermits().then(() => {
                    icon.classList.remove('animate-spin');
                }).catch(() => {
                    icon.classList.remove('animate-spin');
                });
            });
        }
        
        // Auto-check every hour
        setInterval(checkExpiringPermits, 3600000);
    });
    </script>
    <script>
    setInterval(() => {
        let el = document.getElementById('header-time');
        if(el) {
            let d = new Date();
            let h = d.getHours();
            let m = d.getMinutes().toString().padStart(2, '0');
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12; 
            el.innerText = h + ':' + m + ' ' + ampm;
        }
    }, 60000); // 1 minute interval update
    </script>
    <script src="../assets/js/admin-dashboard.min.js?v=<?= filemtime('../assets/js/admin-dashboard.min.js') ?>" defer></script>
</body>
</html> 