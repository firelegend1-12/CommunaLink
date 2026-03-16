<?php
/**
 * Admin Dashboard
 * Main landing page after successful login
 */

// Include authentication system
require_once '../config/init.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in
require_login();

// Page title
$page_title = "Dashboard - CommuniLink";

try {
    // Fetch latest transactions
    $stmt = $pdo->query("SELECT * FROM business_transactions WHERE status IS NOT NULL AND status != '' AND status != 'DELETED' ORDER BY application_date DESC LIMIT 4");
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

    // Fetch quick stats for dashboard cards
    // Total Businesses
    $business_count = $pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
    // Pending Requests (documents)
    $pending_doc_requests = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending'")->fetchColumn();
    // Pending Requests (business transactions)
    $pending_biz_requests = $pdo->query("SELECT COUNT(*) FROM business_transactions WHERE status = 'PENDING'")->fetchColumn();
    $pending_requests = $pending_doc_requests + $pending_biz_requests;
    // Active Incidents
    $active_incidents = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn();
    // Upcoming Events
    $upcoming_events = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()") ? $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn() : 0;
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
    // You might want to log the error message
    // error_log("Dashboard DB Error: " . $e->getMessage());
}

// Function to get sunset time
function getSunsetTime() {
    // Get sunset time for Philippines (default to Manila)
    $lat = 14.5995; // Manila latitude
    $lon = 120.9842; // Manila longitude
    
    // Get sunset time
    $sun_info = date_sun_info(time(), $lat, $lon);
    $sunset_timestamp = $sun_info['sunset'];
    
    // Calculate time until sunset
    $time_until_sunset = $sunset_timestamp - time();
    
    if ($time_until_sunset < 0) {
        // Sunset already happened today, get tomorrow's sunset
        $tomorrow = time() + 86400; // 86400 seconds = 1 day
        $sun_info = date_sun_info($tomorrow, $lat, $lon);
        $sunset_timestamp = $sun_info['sunset'];
        $time_until_sunset = $sunset_timestamp - time();
    }
    
    // Format the time until sunset
    $hours = floor($time_until_sunset / 3600);
    $minutes = floor(($time_until_sunset % 3600) / 60);
    
    return [
        'sunset_time' => date('h:i A', $sunset_timestamp),
        'hours_until' => $hours,
        'minutes_until' => $minutes
    ];
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

    <style>
    * {
      box-sizing: border-box;
    }

    .countdown {
      margin: 0 auto;
      width: 100%;
      display: flex;
      gap: 15px;
      font-family: sans-serif;
      justify-content: center;
    }

    .time-section {
      text-align: center;
      font-size: 14px;
      color: #e5e7eb;
    }

    .time-group {
      display: flex;
      gap: 8px;
    }

    .time-segment {
      display: block;
      font-size: 36px;
      font-weight: 900;
      width: 40px;
    }

    .segment-display {
      position: relative;
      height: 100%;
    }

    .segment-display__top,
    .segment-display__bottom {
      overflow: hidden;
      text-align: center;
      width: 100%;
      height: 50%;
      position: relative;
    }

    .segment-display__top {
      line-height: 1.5;
      color: #e5e7eb;
      background-color: #374151;
    }

    .segment-display__bottom {
      line-height: 0;
      color: #f9fafb;
      background-color: #4b5563;
    }

    .segment-overlay {
      position: absolute;
      top: 0;
      perspective: 400px;
      height: 100%;
      width: 40px;
    }

    .segment-overlay__top,
    .segment-overlay__bottom {
      position: absolute;
      overflow: hidden;
      text-align: center;
      width: 100%;
      height: 50%;
    }

    .segment-overlay__top {
      top: 0;
      line-height: 1.5;
      color: #f9fafb;
      background-color: #374151;
      transform-origin: bottom;
    }

    .segment-overlay__bottom {
      bottom: 0;
      line-height: 0;
      color: #e5e7eb;
      background-color: #4b5563;
      border-top: 1px solid #1f2937;
      transform-origin: top;
    }

    .segment-overlay.flip .segment-overlay__top {
      animation: flip-top 0.8s linear;
    }

    .segment-overlay.flip .segment-overlay__bottom {
      animation: flip-bottom 0.8s linear;
    }

    @keyframes flip-top {
      0% {
        transform: rotateX(0deg);
      }
      50%,
      100% {
        transform: rotateX(-90deg);
      }
    }

    @keyframes flip-bottom {
      0%,
      50% {
        transform: rotateX(90deg);
      }
      100% {
        transform: rotateX(0deg);
      }
    }
    </style>
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
                        <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
                        <div class="flex items-center space-x-4">
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
                                <p class="text-xs text-gray-500">Registered residents</p>
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
                                <p class="text-xs text-gray-500">Registered businesses</p>
                            </div>
                        </div>
                    </div>
                    <!-- Pending Requests Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Pending Requests</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-yellow-700" id="pending-requests-count"><?php echo $pending_requests; ?></h2>
                                <p class="text-xs text-gray-500">Requests pending</p>
                            </div>
                        </div>
                    </div>
                    <!-- Active Incidents Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Active Incidents</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-red-700" id="active-incidents-count"><?php echo $active_incidents; ?></h2>
                                <p class="text-xs text-gray-500">Open incidents</p>
                            </div>
                        </div>
                    </div>
                    <!-- Upcoming Events Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 transition hover:shadow-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-gray-500 text-base font-semibold">Upcoming Events</h3>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-purple-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold text-purple-700" id="upcoming-events-count"><?php echo $upcoming_events; ?></h2>
                                <p class="text-xs text-gray-500">Events scheduled</p>
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
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($trans['id']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($trans['transaction_type']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($trans['owner_name']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($trans['application_date'])); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                <?php echo $trans['status'] === 'APPROVED' ? 'bg-green-100 text-green-800' : ($trans['status'] === 'REJECTED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                                <?php echo htmlspecialchars($trans['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <a href="pages/business-transactions.php" class="text-indigo-600 hover:text-indigo-900">View</a>
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
                        <!-- Today's Quote -->
                        <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg shadow p-6 text-white">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-quote-left text-white text-opacity-50 text-2xl"></i>
                                <h3 class="ml-2 text-lg font-medium">Today's Quote</h3>
                            </div>
                            <p class="text-white text-opacity-90 italic mb-4">"<?php echo $today_quote; ?>"</p>
                        </div>
                        
                        <!-- Time & Calendar Card -->
                        <div class="bg-gradient-to-br from-gray-700 to-gray-900 rounded-lg shadow p-6 text-white">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-clock text-gray-400 text-2xl"></i>
                                <h3 class="ml-2 text-lg font-medium">Time & Calendar</h3>
                            </div>
                                                          <div class="flex flex-col items-center justify-center">
                                  <div class="countdown">
                                    <div class="time-section" id="hours">
                                      <div class="time-group">
                                        <div class="time-segment">
                                          <div class="segment-display">
                                            <div class="segment-display__top"></div>
                                            <div class="segment-display__bottom"></div>
                                            <div class="segment-overlay">
                                              <div class="segment-overlay__top"></div>
                                              <div class="segment-overlay__bottom"></div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="time-segment">
                                          <div class="segment-display">
                                            <div class="segment-display__top"></div>
                                            <div class="segment-display__bottom"></div>
                                            <div class="segment-overlay">
                                              <div class="segment-overlay__top"></div>
                                              <div class="segment-overlay__bottom"></div>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                      <p>Hours</p>
                                    </div>

                                    <div class="time-section" id="minutes">
                                      <div class="time-group">
                                        <div class="time-segment">
                                          <div class="segment-display">
                                            <div class="segment-display__top"></div>
                                            <div class="segment-display__bottom"></div>
                                            <div class="segment-overlay">
                                              <div class="segment-overlay__top"></div>
                                              <div class="segment-overlay__bottom"></div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="time-segment">
                                          <div class="segment-display">
                                            <div class="segment-display__top"></div>
                                            <div class="segment-display__bottom"></div>
                                            <div class="segment-overlay">
                                              <div class="segment-overlay__top"></div>
                                              <div class="segment-overlay__bottom"></div>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                      <p>Minutes</p>
                                    </div>

                                    <div class="time-section" id="seconds">
                                      <div class="time-group">
                                        <div class="time-segment">
                                          <div class="segment-display">
                                            <div class="segment-display__top"></div>
                                            <div class="segment-display__bottom"></div>
                                            <div class="segment-overlay">
                                              <div class="segment-overlay__top"></div>
                                              <div class="segment-overlay__bottom"></div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="time-segment">
                                          <div class="segment-display">
                                            <div class="segment-display__top"></div>
                                            <div class="segment-display__bottom"></div>
                                            <div class="segment-overlay">
                                              <div class="segment-overlay__top"></div>
                                              <div class="segment-overlay__bottom"></div>
                                            </div>
                                          </div>
                                        </div>
                                </div>
                                      <p>Seconds</p>
                                </div>
                            </div>
                                  <div id="current-date" class="text-lg text-gray-200 mt-4"></div>
                            </div>
                        </div>
                        
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
                                <a href="pages/events.php" class="flex items-center p-2 bg-indigo-50 hover:bg-indigo-100 rounded transition group col-span-2">
                                    <i class="fas fa-calendar-plus text-indigo-600 text-sm mr-2"></i>
                                    <span class="text-xs font-medium text-indigo-700">Create Event</span>
                                </a>
                            </div>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Age group data from PHP
        const ageLabels = <?php echo $age_labels; ?>;
        const ageCounts = <?php echo $age_counts; ?>;
        console.log('ageLabels:', ageLabels);
        console.log('ageCounts:', ageCounts);
        const ageCanvas = document.getElementById('ageGroupChart');
        console.log('ageGroupChart canvas:', ageCanvas);

        // Initialize age group chart
        if (ageCanvas && ageLabels && ageLabels.length && ageCounts && ageCounts.length) {
            const ctx = ageCanvas.getContext('2d');
        const ageGroupChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: 'Population by Age Group',
                    data: ageCounts,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 99, 132, 0.8)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        } else {
            console.warn('Population by Age Group chart not rendered: missing data or canvas.');
        }

        // Residents per month data
        const residentsMonths = <?php echo $resident_months; ?>;
        const residentsCounts = <?php echo $resident_counts; ?>;
        console.log('residentsMonths:', residentsMonths);
        console.log('residentsCounts:', residentsCounts);
        const residentsPerMonthCanvas = document.getElementById('residentsPerMonthChart');
        console.log('residentsPerMonthChart canvas:', residentsPerMonthCanvas);
        if (residentsPerMonthCanvas && residentsMonths && residentsMonths.length && residentsCounts && residentsCounts.length) {
            const residentsPerMonthCtx = residentsPerMonthCanvas.getContext('2d');
        new Chart(residentsPerMonthCtx, {
            type: 'bar',
            data: {
                labels: residentsMonths,
                datasets: [{
                    label: 'Residents Added',
                    data: residentsCounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
        } else {
            console.warn('Residents per Month chart not rendered: missing data or canvas.');
        }

        // Businesses per month data
        const businessMonths = <?php echo $business_months; ?>;
        const businessCounts = <?php echo $business_counts; ?>;
        console.log('businessMonths:', businessMonths);
        console.log('businessCounts:', businessCounts);
        const businessesPerMonthCanvas = document.getElementById('businessesPerMonthChart');
        console.log('businessesPerMonthChart canvas:', businessesPerMonthCanvas);
        if (businessesPerMonthCanvas && businessMonths && businessMonths.length && businessCounts && businessCounts.length) {
            const businessesPerMonthCtx = businessesPerMonthCanvas.getContext('2d');
        new Chart(businessesPerMonthCtx, {
            type: 'bar',
            data: {
                labels: businessMonths,
                datasets: [{
                    label: 'Businesses Added',
                    data: businessCounts,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
        } else {
            console.warn('Businesses per Month chart not rendered: missing data or canvas.');
        }
    });

    function updateDashboardStats() {
        fetch('partials/dashboard-stats.php')
            .then(res => res.json())
            .then(data => {
                document.getElementById('pending-requests-count').textContent = data.pending_requests;
                document.getElementById('business-count').textContent = data.business_count;
                document.getElementById('population-count').textContent = data.resident_count;
                document.getElementById('active-incidents-count').textContent = data.active_incidents;
                document.getElementById('upcoming-events-count').textContent = data.upcoming_events;
            });
    }
    setInterval(updateDashboardStats, 5000);
    updateDashboardStats();

    function getTimeSegmentElements(segmentElement) {
      const segmentDisplay = segmentElement.querySelector(
        '.segment-display'
      );
      const segmentDisplayTop = segmentDisplay.querySelector(
        '.segment-display__top'
      );
      const segmentDisplayBottom = segmentDisplay.querySelector(
        '.segment-display__bottom'
      );

      const segmentOverlay = segmentDisplay.querySelector(
        '.segment-overlay'
      );
      const segmentOverlayTop = segmentOverlay.querySelector(
        '.segment-overlay__top'
      );
      const segmentOverlayBottom = segmentOverlay.querySelector(
        '.segment-overlay__bottom'
      );

      return {
        segmentDisplayTop,
        segmentDisplayBottom,
        segmentOverlay,
        segmentOverlayTop,
        segmentOverlayBottom,
      };
    }

    function updateSegmentValues(
      displayElement,
      overlayElement,
      value
    ) {
      displayElement.textContent = value;
      overlayElement.textContent = value;
    }

    function updateTimeSegment(segmentElement, timeValue) {
      const segmentElements =
        getTimeSegmentElements(segmentElement);

      if (
        parseInt(
          segmentElements.segmentDisplayTop.textContent,
          10
        ) === timeValue
      ) {
        return;
      }

      segmentElements.segmentOverlay.classList.add('flip');

      updateSegmentValues(
        segmentElements.segmentDisplayTop,
        segmentElements.segmentOverlayBottom,
        timeValue
      );

      function finishAnimation() {
        segmentElements.segmentOverlay.classList.remove('flip');
        updateSegmentValues(
          segmentElements.segmentDisplayBottom,
          segmentElements.segmentOverlayTop,
          timeValue
        );

        this.removeEventListener(
          'animationend',
          finishAnimation
        );
      }

      segmentElements.segmentOverlay.addEventListener(
        'animationend',
        finishAnimation
      );
    }

    function updateTimeSection(sectionID, timeValue) {
      const firstNumber = Math.floor(timeValue / 10) || 0;
      const secondNumber = timeValue % 10 || 0;
      const sectionElement = document.getElementById(sectionID);
      const timeSegments =
        sectionElement.querySelectorAll('.time-segment');

      updateTimeSegment(timeSegments[0], firstNumber);
      updateTimeSegment(timeSegments[1], secondNumber);
    }

    function updateCurrentTime() {
      const now = new Date();
      let hours = now.getHours();
      const minutes = now.getMinutes();
      const seconds = now.getSeconds();

      // Convert to 12-hour format
      const ampm = hours >= 12 ? 'PM' : 'AM';
      hours = hours % 12;
      hours = hours ? hours : 12; // the hour '0' should be '12'

      updateTimeSection('hours', hours);
      updateTimeSection('minutes', minutes);
      updateTimeSection('seconds', seconds);

      // Update date
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
    }

    document.addEventListener('DOMContentLoaded', function() {
      updateCurrentTime();
      setInterval(updateCurrentTime, 1000);
      

    });
    </script>
</body>
</html> 