<?php
require_once '../partials/admin_auth.php';
/**
 * Incident Reports Management - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/permission_checker.php';

require_login();

// Check manage_incidents permission (admin, barangay-officials, barangay-kagawad, barangay-tanod)
if (!require_permission('manage_incidents')) {
    $redirect_prefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'pages') ? '../../' : '../';
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        header('Location: ' . $redirect_prefix . 'resident/dashboard.php');
    } else {
        header('Location: ' . $redirect_prefix . 'index.php');
    }
    exit;
}

$page_title = "Incident Reports";
$incident_csrf_token = csrf_token();
$current_user_id = $_SESSION['user_id'] ?? 0;

// Fetch counts for cards
// 1. Total Reports
$stmt = $pdo->query("SELECT COUNT(*) FROM incidents");
$total_incidents = $stmt->fetchColumn();

// 2. Active Cases (Pending + In Progress)
$stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')");
$active_cases = $stmt->fetchColumn();

// 3. Trending Today (Last 24 hours)
$stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE reported_at >= NOW() - INTERVAL 1 DAY");
$trending_today = $stmt->fetchColumn();

// 4. Resolution Rate (All Time)
$stmt = $pdo->query("SELECT 
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved,
    COUNT(*) as total
    FROM incidents");
$all_time_stats = $stmt->fetch();
$resolution_rate = ($all_time_stats['total'] > 0) 
    ? round(($all_time_stats['resolved'] / $all_time_stats['total']) * 100) 
    : 0;

// 5. Critical Alerts (Most frequent type this month)
$stmt = $pdo->query("SELECT type, COUNT(*) as count 
    FROM incidents 
    WHERE MONTH(reported_at) = MONTH(CURRENT_DATE()) AND YEAR(reported_at) = YEAR(CURRENT_DATE())
    GROUP BY type 
    ORDER BY count DESC 
    LIMIT 1");
$most_frequent = $stmt->fetch();
$critical_type = $most_frequent ? $most_frequent['type'] : 'None';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="pageData()">
        <!-- Sidebar Navigation -->
        <?php
include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white/80 backdrop-blur-md shadow-sm z-10 border-b border-slate-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center gap-4">
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Incidents</h1>
                            <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase"><?php
echo $total_incidents; ?> Reports</span>
                        </div>
                        
                        <!-- Refresh Button & User -->
                        <div class="flex items-center gap-4">
                             <button @click="fetchReports" class="text-slate-500 hover:text-indigo-600 p-2 transition" title="Refresh Data">
                                <i class="fas fa-sync-alt" :class="{ 'animate-spin': isRefreshing }"></i>
                            </button>
                            
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" class="flex items-center space-x-3 text-sm text-slate-700 hover:text-slate-900 focus:outline-none transition group">
                                    <span class="font-medium group-hover:text-indigo-600"><?php
echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-sm font-bold shadow-sm group-hover:shadow-md transition">
                                        <?php
echo substr($_SESSION['fullname'], 0, 1); ?>
                                    </div>
                                </button>
                                <div x-show="open" @click.away="open = false" x-cloak
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="origin-top-right absolute right-0 mt-2 w-48 rounded-xl shadow-xl py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                    <a href="account.php" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"><i class="fas fa-user-circle mr-2 text-slate-400"></i> My Account</a>
                                    <div class="border-t border-slate-100 mt-1"></div>
                                    <a href="../../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt mr-2"></i> Sign Out</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-8">
                <?php
if (isset($_SESSION['success_message'])): ?>
                    <div id="incident-success-alert" class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-r-xl shadow-sm animate-fade-in" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>
                            <p class="font-bold text-sm"><?php
echo htmlspecialchars($_SESSION['success_message']); ?></p>
                        </div>
                    </div>
                <?php
unset($_SESSION['success_message']); endif; ?>

                <!-- Summary Stats Bar -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Stat Card: Active Cases -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition group overflow-hidden relative">
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 bg-rose-50 rounded-full blur-2xl group-hover:bg-rose-100/50 transition duration-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Active Cases</p>
                                <h3 class="text-3xl font-black text-slate-900 leading-none" x-text="stats.active_cases"></h3>
                                <p class="text-[10px] font-bold text-rose-500 mt-2 flex items-center">
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5 animate-pulse"></span>
                                    Requires Action
                                </p>
                            </div>
                            <div class="h-12 w-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-inner group-hover:scale-110 transition duration-300">
                                <i class="fas fa-bolt"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Card: Trending Today -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition group overflow-hidden relative">
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 bg-amber-50 rounded-full blur-2xl group-hover:bg-amber-100/50 transition duration-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Trending Today</p>
                                <h3 class="text-3xl font-black text-slate-900 leading-none" x-text="stats.trending_today"></h3>
                                <div class="mt-2 text-[10px] font-bold text-amber-600 flex items-center bg-amber-50 px-2 py-0.5 rounded-lg w-fit">
                                    <i class="fas fa-chart-line mr-1.5"></i> LAST 24 HOURS
                                </div>
                            </div>
                            <div class="h-12 w-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl shadow-inner group-hover:rotate-12 transition duration-300">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Card: Resolution Rate -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition group overflow-hidden relative">
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 bg-emerald-50 rounded-full blur-2xl group-hover:bg-emerald-100/50 transition duration-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Resolution Rate</p>
                                <div class="flex items-end gap-1">
                                    <h3 class="text-3xl font-black text-slate-900 leading-none" x-text="stats.resolution_rate + '%'"></h3>
                                    <span class="text-[10px] font-bold text-slate-400 mb-0.5">all time</span>
                                </div>
                                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden border border-slate-50">
                                    <div class="bg-emerald-500 h-full rounded-full transition-all duration-500" :style="'width: ' + stats.resolution_rate + '%'"></div>
                                </div>
                            </div>
                            <div class="h-12 w-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-inner group-hover:scale-95 transition duration-300">
                                <i class="fas fa-check-double"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Stat Card: Critical Type -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition group overflow-hidden relative">
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 bg-indigo-50 rounded-full blur-2xl group-hover:bg-indigo-100/50 transition duration-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Most Frequent</p>
                                <h3 class="text-xl font-black text-slate-900 leading-tight truncate max-w-[140px]"><?php
echo $critical_type; ?></h3>
                                <p class="text-[10px] font-medium text-slate-500 mt-1 uppercase tracking-tighter">Frequent this month</p>
                            </div>
                            <div class="h-12 w-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-inner group-hover:translate-x-1 transition duration-300">
                                <i class="fas fa-biohazard"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Table Actions -->
                    <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap justify-between items-center bg-slate-50/50 gap-4">
                        <div class="flex-grow max-w-md">
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 group-focus-within:text-indigo-500 transition">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" x-model="search" 
                                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition shadow-sm text-sm"
                                    placeholder="Search by reporter, type or location...">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Date Range Filter -->
                            <div class="flex items-center bg-white border border-slate-200 rounded-xl p-1 gap-1 shadow-sm">
                                <div class="flex items-center px-2 text-slate-400">
                                    <i class="fas fa-calendar-alt text-xs"></i>
                                </div>
                                <input type="date" x-model="dateFrom" class="text-xs font-bold text-slate-600 focus:outline-none border-none p-1 w-28 bg-transparent" title="Start Date">
                                <span class="text-slate-300 text-xs">/</span>
                                <input type="date" x-model="dateTo" class="text-xs font-bold text-slate-600 focus:outline-none border-none p-1 w-28 bg-transparent" title="End Date">
                                <button x-show="dateFrom || dateTo" @click="dateFrom = ''; dateTo = ''" class="text-slate-400 hover:text-rose-500 p-1 transition" title="Clear Date Filter">
                                    <i class="fas fa-times-circle text-xs"></i>
                                </button>
                            </div>

                            <!-- Status Filter -->
                            <div class="flex flex-wrap bg-slate-200/50 p-1 rounded-xl border border-slate-200 gap-1">
                                <button @click="statusFilter = 'All'" :class="statusFilter === 'All' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">ALL</button>
                                <button @click="statusFilter = 'Pending'" :class="statusFilter === 'Pending' ? 'bg-white text-rose-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">PENDING</button>
                                <button @click="statusFilter = 'Resolved'" :class="statusFilter === 'Resolved' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">RESOLVED</button>
                                <button @click="statusFilter = 'Rejected'" :class="statusFilter === 'Rejected' ? 'bg-white text-rose-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">REJECTED</button>
                            </div>

                            <!-- Export Button -->
                            <button @click="exportCSV" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase transition-all shadow-md shadow-indigo-600/20 active:shadow-none flex items-center">
                                <i class="fas fa-file-export mr-2"></i> EXPORT
                            </button>
                        </div>
                    </div>

                    <!-- Modern Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50/30">
                                    <th class="w-[28%] px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Incident</th>
                                    <th class="w-[14%] px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Reporter</th>
                                    <th class="w-[22%] px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Location</th>
                                     <th class="w-[14%] px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Date Reported</th>
                                    <th class="w-[12%] px-6 py-4 text-center text-xs font-black text-slate-500 uppercase tracking-widest">Status</th>
                                    <th class="w-[10%] px-6 py-4 text-center text-xs font-black text-slate-500 uppercase tracking-widest">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <template x-for="report in filteredReports" :key="report.id">
                                    <tr class="hover:bg-indigo-50/50 transition-all group cursor-pointer border-l-4 border-l-transparent hover:border-l-indigo-500" title="Click to view case details">
                                        <td @click="openQuickView(report.id)" class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="relative">
                                                    <div :class="{
                                                        'h-12 w-12 rounded-2xl flex items-center justify-center text-lg shadow-sm overflow-hidden border-2 transition-all duration-500 group-hover:rotate-3 group-hover:scale-105': true,
                                                        'bg-rose-100/50 border-rose-200 text-rose-600': report.type === 'Fire' || report.type === 'Emergency',
                                                        'bg-indigo-100/50 border-indigo-200 text-indigo-600': report.type === 'Traffic' || report.type === 'Crime',
                                                        'bg-slate-100/50 border-slate-200 text-slate-600': !['Fire', 'Emergency', 'Traffic', 'Crime'].includes(report.type)
                                                    }">
                                                        <template x-if="report.image_path">
                                                            <img :src="report.image_url || ('../../' + report.image_path)" class="h-full w-full object-cover">
                                                        </template>
                                                        <template x-if="!report.image_path">
                                                            <i :class="{
                                                                'fas': true,
                                                                'fa-fire': report.type === 'Fire',
                                                                'fa-ambulance': report.type === 'Emergency',
                                                                'fa-car': report.type === 'Traffic',
                                                                'fa-exclamation-triangle': !['Fire', 'Emergency', 'Traffic'].includes(report.type)
                                                            }"></i>
                                                        </template>
                                                    </div>
                                                    <!-- Urgency Pulse -->
                                                    <template x-if="report.status === 'Pending'">
                                                        <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                                          <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500 border border-white"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-slate-900 group-hover:text-indigo-700 transition" x-text="report.type"></div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-tighter" x-text="'ID: #' + report.id"></div>
                                                        <template x-if="report.image_path">
                                                            <span class="text-[8px] font-black bg-indigo-50 text-indigo-500 px-1 rounded flex items-center">
                                                                <i class="fas fa-camera mr-0.5"></i> ATTACHED
                                                            </span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td @click="openQuickView(report.id)" class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-700" x-text="report.resident_name || 'System User'"></div>
                                            <div class="text-xs text-slate-400 italic">Resident</div>
                                        </td>
                                        <td @click="openQuickView(report.id)" class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-600 max-w-[200px] truncate" x-text="report.location"></div>
                                            <template x-if="report.latitude && report.longitude">
                                                <div class="flex items-center text-[10px] text-indigo-500 font-bold mt-0.5">
                                                    <i class="fas fa-map-marker-alt mr-1 text-[8px]"></i> GPS PINNED
                                                </div>
                                            </template>
                                        </td>
                                        <td @click="openQuickView(report.id)" class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-700 font-medium" x-text="formatDate(report.reported_at)"></div>
                                            <div class="text-[10px] text-slate-400 uppercase tracking-tighter" x-text="formatTime(report.reported_at)"></div>
                                        </td>
                                        <td @click="openQuickView(report.id)" class="px-6 py-4 whitespace-nowrap text-center">
                                        <span :class="{
                                            'inline-flex items-center justify-center px-3 py-1 text-[10px] font-black uppercase rounded-full shadow-sm transition-all duration-300': true,
                                            'bg-rose-100 text-rose-700 border border-rose-200': report.status === 'Pending',
                                            'bg-indigo-100 text-indigo-700 border border-indigo-200': report.status === 'Under Review' || report.status === 'In Progress',
                                            'bg-emerald-100 text-emerald-700 border border-emerald-200': report.status === 'Resolved',
                                            'bg-slate-100 text-slate-700 border border-slate-200': report.status === 'Rejected'
                                        }" x-text="report.status"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button @click.stop="openQuickView(report.id)" class="text-indigo-600 hover:bg-indigo-50 p-2 rounded-lg transition" title="Quick View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                </template>
                            </tbody>
                        </table>
                        
                        <!-- Empty State -->
                        <template x-if="filteredReports.length === 0 && !isLoading">
                            <div class="py-20 flex flex-col items-center justify-center bg-slate-50/50">
                                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mb-4">
                                    <i class="fas fa-bullhorn text-4xl text-slate-200"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900">No incident reports found</h3>
                                <p class="text-slate-500 text-sm mt-1">Try adjusting your filters or search query.</p>
                            </div>
                        </template>

                        <!-- Loading State -->
                        <template x-if="isLoading">
                             <div class="py-20 flex flex-col items-center justify-center">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                                <p class="text-slate-500 font-medium tracking-wide italic text-sm">Synchronizing reports...</p>
                            </div>
                        </template>
                    </div>
                </div>
            </main>
        </div>

        <!-- Quick View Slide-over Panel (must be inside x-data scope for Alpine.js) -->
        <template x-if="showView">
        <div class="fixed inset-0 overflow-hidden z-50 shadow-2xl" aria-labelledby="slide-over-title" role="dialog" aria-modal="true" @keydown.window.escape="showView = false">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Background backdrop with blur -->
                <div x-transition:enter="ease-out duration-500" 
                     x-transition:enter-start="opacity-0" 
                     x-transition:enter-end="opacity-100" 
                     x-transition:leave="ease-in duration-500" 
                     x-transition:leave-start="opacity-100" 
                     x-transition:leave-end="opacity-0" 
                     class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showView = false" aria-hidden="true"></div>

                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div x-transition:enter="transform transition ease-out duration-500 sm:duration-700" 
                         x-transition:enter-start="translate-x-full" 
                         x-transition:enter-end="translate-x-0" 
                         x-transition:leave="transform transition ease-in duration-500 sm:duration-700" 
                         x-transition:leave-start="translate-x-0" 
                         x-transition:leave-end="translate-x-full" 
                         class="pointer-events-auto w-screen max-w-4xl">
                        <div class="flex h-full flex-col bg-white shadow-2xl">
                             <!-- Premium Header: flex-shrink-0 prevents shrinking when content loads -->
                             <!-- Premium Header: Maximized Visibility -->
                             <div class="flex-shrink-0 min-h-[140px] bg-indigo-700 px-8 py-8 sm:py-12 sm:px-10 relative overflow-hidden transition-all duration-500">
                                 <!-- Abstract Background Pattern -->
                                 <div class="absolute inset-0 opacity-10">
                                     <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                         <path d="M0 100 C 20 0 50 0 100 100 Z" fill="white"></path>
                                     </svg>
                                 </div>
                                 <div class="absolute top-0 right-0 -mt-10 -mr-10 h-48 w-48 rounded-full bg-white/10 blur-3xl"></div>
                                 
                                 <div class="relative flex items-center justify-between z-10">
                                     <!-- Skeleton Header -->
                                     <template x-if="loadingView">
                                         <div class="flex items-center gap-8 animate-pulse w-full">
                                             <div class="h-24 w-24 rounded-[32px] bg-white/10 backdrop-blur-md"></div>
                                             <div class="flex-1 space-y-4">
                                                 <div class="h-8 bg-white/10 rounded-lg w-1/3"></div>
                                                 <div class="flex gap-4">
                                                     <div class="h-5 bg-white/10 rounded-lg w-20"></div>
                                                     <div class="h-5 bg-white/10 rounded-lg w-32"></div>
                                                 </div>
                                             </div>
                                         </div>
                                     </template>

                                     <!-- Real Header Content -->
                                     <template x-if="!loadingView && viewData">
                                         <div class="flex items-center gap-8">
                                             <div :class="{
                                                 'h-24 w-24 rounded-[32px] bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-4xl shadow-2xl transition-transform duration-500 hover:rotate-3 hover:scale-105': true,
                                                 'text-amber-300': viewData.type === 'Fire' || viewData.type === 'Emergency',
                                                 'text-indigo-200': viewData.type !== 'Fire' && viewData.type !== 'Emergency'
                                             }">
                                                 <i :class="{
                                                     'fas': true,
                                                     'fa-fire': viewData.type === 'Fire',
                                                     'fa-ambulance': viewData.type === 'Emergency',
                                                     'fa-car': viewData.type === 'Traffic',
                                                     'fa-exclamation-triangle': !['Fire', 'Emergency', 'Traffic'].includes(viewData.type)
                                                 }"></i>
                                             </div>
                                             <div>
                                                 <h2 class="text-3xl font-black text-white leading-none uppercase tracking-tighter" id="slide-over-title" x-text="viewData ? viewData.type : ''"></h2>
                                                 <div class="flex items-center mt-3 gap-4 text-indigo-50">
                                                     <span class="text-[11px] font-black uppercase bg-white/20 px-3 py-1 rounded-xl tracking-[0.2em] shadow-sm" x-text="'ID: #' + (viewData ? viewData.id : '')"></span>
                                                     <span class="text-sm font-bold opacity-80" x-text="viewData ? formatDate(viewData.reported_at) + ' @ ' + formatTime(viewData.reported_at) : ''"></span>
                                                 </div>
                                             </div>
                                         </div>
                                     </template>

                                     <div class="ml-4 flex items-center">
                                         <button @click="showView = false" class="h-12 w-12 rounded-2xl bg-white/10 text-white hover:bg-rose-500 hover:scale-110 transition-all duration-300 flex items-center justify-center border border-white/20 shadow-xl group">
                                             <i class="fas fa-times text-2xl group-hover:rotate-90 transition-transform duration-300"></i>
                                         </button>
                                     </div>
                                 </div>
                             </div>

                            <!-- Content: scrollable area only -->
                        <div class="relative flex-1 overflow-y-auto min-h-0 px-6 py-6 sm:px-8">
                            <template x-if="loadingView">
                                <div class="flex flex-col items-center justify-center h-64">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-700 mb-4"></div>
                                    <p class="text-slate-500 font-medium tracking-wide italic">Fetching report details...</p>
                                </div>
                            </template>

                            <template x-if="!loadingView && viewData">
                                <div class="space-y-6">
                                    <div class="flex items-start gap-6">
                                        <!-- Left Column: Details -->
                                        <div class="flex-1 space-y-6">
                                            <!-- Status Control Panel: Sleeker -->
                                            <div :class="{
                                                'p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-4 transition-all duration-500 shadow-sm': true,
                                                'bg-amber-50/50 border-amber-100': viewData.status === 'Pending',
                                                'bg-indigo-50/50 border-indigo-100': viewData.status === 'In Progress' || viewData.status === 'Under Review',
                                                'bg-emerald-50/50 border-emerald-100': viewData.status === 'Resolved',
                                                'bg-rose-50/50 border-rose-100': viewData.status === 'Rejected'
                                            }">
                                                <div class="flex items-center">
                                                    <div class="h-2.5 w-2.5 rounded-full mr-3 animate-pulse" :class="{
                                                        'bg-amber-500': viewData.status === 'Pending',
                                                        'bg-indigo-500': viewData.status === 'In Progress',
                                                        'bg-emerald-500': viewData.status === 'Resolved',
                                                        'bg-rose-500': viewData.status === 'Rejected'
                                                    }"></div>
                                                    <div>
                                                        <p class="text-[8px] font-black uppercase tracking-[0.2em] text-slate-400 mb-0.5">CURRENT STATE</p>
                                                        <span class="text-[11px] font-black uppercase tracking-widest block" :class="{
                                                            'text-amber-800': viewData.status === 'Pending',
                                                            'text-indigo-800': viewData.status === 'In Progress',
                                                            'text-emerald-800': viewData.status === 'Resolved',
                                                            'text-rose-800': viewData.status === 'Rejected'
                                                        }" x-text="viewData.status"></span>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 bg-white p-1 rounded-xl border border-slate-100 self-stretch sm:self-auto">
                                                    <select 
                                                        x-model="viewData.status" 
                                                        @change="handleStatusChange"
                                                        :disabled="isSavingStatus || viewData.status === 'Resolved' || viewData.status === 'Rejected'"
                                                        class="bg-transparent border-none text-[9px] font-black uppercase tracking-wider focus:ring-0 cursor-pointer disabled:opacity-50 h-8"
                                                    >
                                                        <option value="Pending">Pending</option>
                                                        <option value="Resolved">Resolved</option>
                                                        <option value="Rejected">Rejected</option>
                                                    </select>
                                                    <template x-if="isSavingStatus">
                                                        <i class="fas fa-spinner fa-spin text-indigo-600 text-xs mr-2"></i>
                                                    </template>
                                                </div>
                                            </div>

                                             <!-- Reporter Info: More Compact 3-Column Layout -->
                                             <div class="group/section">
                                                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-2 mb-4 flex items-center group-hover/section:text-indigo-500 transition-colors">
                                                     <i class="fas fa-user-shield mr-2"></i> REPORTER INFORMATION
                                                 </h3>
                                                 <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm relative overflow-hidden group/reporter text-sm">
                                                     <div class="absolute top-0 right-0 p-3 opacity-0 group-hover/reporter:opacity-100 transition-opacity">
                                                         <a :href="'residents.php?search=' + encodeURIComponent(viewData.reporter_name)" class="text-[9px] font-black uppercase bg-indigo-50 text-indigo-600 px-2 py-1 rounded-lg hover:bg-indigo-600 hover:text-white transition-all">View History</a>
                                                     </div>
                                                     <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                         <div>
                                                             <p class="text-[9px] font-black text-slate-400 uppercase mb-0.5 tracking-tighter">Full Name</p>
                                                             <p class="font-bold text-slate-800" x-text="viewData.reporter_name"></p>
                                                         </div>
                                                         <div>
                                                             <p class="text-[9px] font-black text-slate-400 uppercase mb-0.5 tracking-tighter">Contact No.</p>
                                                             <p class="font-bold text-slate-800" x-text="viewData.reporter_contact || 'N/A'"></p>
                                                         </div>
                                                         <div>
                                                             <p class="text-[9px] font-black text-slate-400 uppercase mb-0.5 tracking-tighter">Email Address</p>
                                                             <p class="font-bold text-indigo-600 truncate" x-text="viewData.reporter_email"></p>
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>

                                            <!-- Incident Details -->
                                            <div class="group/section">
                                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-3 mb-5 flex items-center group-hover/section:text-indigo-500 transition-colors">
                                                    <i class="fas fa-info-circle mr-3"></i> INCIDENT DETAILS
                                                </h3>
                                                <div class="space-y-6">
                                                    <div>
                                                        <p class="text-[10px] font-black text-slate-400 uppercase mb-2 tracking-tighter">Location & Landmark</p>
                                                        <div class="bg-slate-50 p-4 rounded-xl text-xs font-bold text-slate-600 border border-slate-100" x-text="viewData.location"></div>
                                                    </div>
                                                    
                                                    <div>
                                                         <div class="flex items-center justify-between mb-2">
                                                             <p class="text-[10px] font-black text-slate-400 uppercase tracking-tighter text-indigo-500">Investigation Map</p>
                                                             <template x-if="viewData.latitude">
                                                                 <a :href="'https://www.google.com/maps/search/?api=1&query=' + viewData.latitude + ',' + viewData.longitude" target="_blank" class="text-[9px] font-bold text-slate-400 hover:text-indigo-500 flex items-center transition-colors">
                                                                     <i class="fas fa-external-link-alt mr-1"></i> Report Error
                                                                 </a>
                                                             </template>
                                                         </div>
                                                        <div class="rounded-2xl overflow-hidden border border-slate-200 shadow-sm h-64 relative bg-slate-100 group/map transition-all duration-500 hover:shadow-md">
                                                            <template x-if="viewData.latitude">
                                                                 <iframe 
                                                                     class="w-full h-full grayscale-[0.3] contrast-[1.1] hover:grayscale-0 transition-all duration-700"
                                                                     frameborder="0" 
                                                                     scrolling="no" 
                                                                     marginheight="0" 
                                                                     marginwidth="0" 
                                                                     loading="lazy"
                                                                     :src="'https://maps.google.com/maps?q=' + viewData.latitude + ',' + viewData.longitude + '&hl=en&z=14&output=embed'"
                                                                 ></iframe>
                                                            </template>
                                                            <template x-if="!viewData.latitude">
                                                                <div class="flex flex-col items-center justify-center h-full text-slate-400 p-8 text-center">
                                                                    <i class="fas fa-map-marked-alt text-3xl mb-3 opacity-20"></i>
                                                                    <p class="text-[10px] font-black uppercase tracking-widest">No GPS Data Available</p>
                                                                    <p class="text-[9px] font-bold mt-1">Review landmarks in reporter description.</p>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    
                                                    <div>
                                                        <p class="text-[10px] font-black text-slate-400 uppercase mb-2 tracking-tighter">Detailed Narrative</p>
                                                        <div class="text-xs font-semibold leading-relaxed text-slate-600 bg-white p-4 rounded-xl border border-slate-100 shadow-sm transition-all hover:border-slate-200" x-text="viewData.description || 'No description provided.'"></div>
                                                    </div>

                                                    <!-- Photos -->
                                                    <template x-if="viewData.image_path">
                                                        <div>
                                                            <p class="text-[10px] font-black text-slate-400 uppercase mb-3 text-center tracking-[0.3em]">ATTACHED EVIDENCE</p>
                                                            <div class="rounded-3xl overflow-hidden border border-slate-200 shadow-lg group/media cursor-zoom-in">
                                                                <img :src="viewData.image_url || ('../../' + viewData.image_path)" class="w-full h-auto object-cover max-h-[400px]">
                                                                <div class="bg-indigo-600 p-3 flex items-center justify-between">
                                                                    <span class="text-[10px] font-black text-white uppercase tracking-widest"><i class="fas fa-camera mr-2"></i> Field Photograph</span>
                                                                    <a :href="viewData.image_url || ('../../' + viewData.image_path)" target="_blank" class="text-[10px] font-black uppercase text-white hover:underline bg-white/20 px-3 py-1 rounded-lg">Source</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column: Timeline & Notes -->
                                        <div class="w-64 space-y-10">
                                             <!-- Resolution Stepper: Cleaner & More Modern -->
                                             <div class="group/section">
                                                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-2 mb-4 group-hover/section:text-indigo-500 transition-colors">TIMELINE</h3>
                                                 <div class="relative pl-5 space-y-6 before:absolute before:left-[9px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                                                     <!-- Step: Reported -->
                                                     <div class="relative">
                                                         <div class="absolute -left-[20px] h-3 w-3 rounded-full border-2 border-white bg-indigo-500 shadow-sm"></div>
                                                         <p class="text-[9px] font-black text-indigo-600 uppercase tracking-widest">Reported</p>
                                                         <p class="text-xs font-bold text-slate-800" x-text="formatDate(viewData.reported_at)"></p>
                                                         <p class="text-[9px] text-slate-400" x-text="formatTime(viewData.reported_at)"></p>
                                                     </div>
 
                                                     <!-- Step: Processing -->
                                                     <div class="relative">
                                                         <div :class="{
                                                             'absolute -left-[20px] h-3 w-3 rounded-full border-2 border-white shadow-sm': true,
                                                             'bg-indigo-500': viewData.status !== 'Pending',
                                                             'bg-slate-300': viewData.status === 'Pending'
                                                         }"></div>
                                                         <p :class="viewData.status !== 'Pending' ? 'text-[9px] font-black text-indigo-600 uppercase tracking-widest' : 'text-[9px] font-black text-slate-400 uppercase tracking-widest'">Acknowledge</p>
                                                         <p class="text-xs font-bold" :class="viewData.status !== 'Pending' ? 'text-slate-800' : 'text-slate-400'" x-text="viewData.status !== 'Pending' ? 'Confirmed' : 'Pending Review'"></p>
                                                     </div>
 
                                                     <!-- Step: Resolved -->
                                                     <div class="relative">
                                                         <div :class="{
                                                             'absolute -left-[20px] h-3 w-3 rounded-full border-2 border-white shadow-sm': true,
                                                             'bg-emerald-500': viewData.status === 'Resolved',
                                                             'bg-rose-500': viewData.status === 'Rejected',
                                                             'bg-slate-300': viewData.status !== 'Resolved' && viewData.status !== 'Rejected'
                                                         }"></div>
                                                         <p :class="{
                                                             'text-[9px] font-black text-emerald-600 uppercase tracking-widest': viewData.status === 'Resolved',
                                                             'text-[9px] font-black text-rose-600 uppercase tracking-widest': viewData.status === 'Rejected',
                                                             'text-[9px] font-black text-slate-400 uppercase tracking-widest': viewData.status !== 'Resolved' && viewData.status !== 'Rejected'
                                                         }">Resolution</p>
                                                         <p class="text-xs font-bold" :class="{
                                                             'text-slate-800': viewData.status === 'Resolved' || viewData.status === 'Rejected',
                                                             'text-slate-400': viewData.status !== 'Resolved' && viewData.status !== 'Rejected'
                                                         }" x-text="viewData.status === 'Resolved' ? 'Resolved' : (viewData.status === 'Rejected' ? 'Rejected' : 'In Progress')"></p>
                                                     </div>
                                                 </div>
                                             </div>
 
                                             <!-- Admin Notes Thread -->
                                            <div class="group/section">
                                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-2 mb-4 group-hover/section:text-indigo-500 transition-colors">ADMIN NOTES</h3>
                                                <div class="space-y-3">
                                                    <!-- Notes list -->
                                                    <template x-if="notes.length === 0">
                                                        <div class="text-center py-4">
                                                            <p class="text-[11px] text-slate-400 font-medium">No admin notes yet.</p>
                                                        </div>
                                                    </template>
                                                    <template x-for="note in notes" :key="note.id">
                                                        <div class="bg-indigo-50/50 p-3 rounded-xl border border-indigo-100/50">
                                                            <div class="flex items-start justify-between mb-1">
                                                                <div class="flex items-center gap-2">
                                                                    <div class="w-6 h-6 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center" x-text="note.user_name ? note.user_name.charAt(0).toUpperCase() : 'U'"></div>
                                                                    <div>
                                                                        <p class="text-[11px] font-bold text-slate-700" x-text="note.user_name || 'Unknown'"></p>
                                                                        <p class="text-[9px] text-slate-400">
                                                                            <span x-text="note.user_role || 'Staff'"></span>
                                                                            <span class="mx-1">&middot;</span>
                                                                            <span x-text="new Date(note.created_at).toLocaleString()"></span>
                                                                            <span x-show="note.updated_at !== note.created_at" class="text-amber-500 font-medium"> (edited)</span>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex gap-1" x-show="note.is_owner || note.is_admin">
                                                                    <button 
                                                                        x-show="note.is_owner"
                                                                        @click="startEditNote(note)"
                                                                        class="text-[10px] text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1 rounded hover:bg-indigo-100 transition"
                                                                    >
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button 
                                                                        @click="deleteNote(note.id)"
                                                                        class="text-[10px] text-red-600 hover:text-red-800 font-medium px-2 py-1 rounded hover:bg-red-50 transition"
                                                                    >
                                                                        <i class="fas fa-trash-alt"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <!-- View mode -->
                                                            <p x-show="editingNoteId !== note.id" class="text-[11px] text-slate-700 whitespace-pre-wrap" x-text="note.note"></p>
                                                            <!-- Edit mode -->
                                                            <div x-show="editingNoteId === note.id" class="mt-1">
                                                                <textarea 
                                                                    x-model="editingNoteText" 
                                                                    class="w-full bg-white border border-indigo-200 rounded-lg focus:ring-1 focus:ring-indigo-500 text-[11px] font-medium text-slate-700 min-h-[60px] resize-none p-2"
                                                                ></textarea>
                                                                <div class="flex gap-2 mt-2">
                                                                    <button 
                                                                        @click="saveEditNote(note.id)" 
                                                                        class="px-3 py-1 bg-indigo-600 text-white text-[10px] font-bold rounded hover:bg-indigo-700 transition"
                                                                    >Save</button>
                                                                    <button 
                                                                        @click="cancelEditNote()" 
                                                                        class="px-3 py-1 bg-slate-200 text-slate-700 text-[10px] font-bold rounded hover:bg-slate-300 transition"
                                                                    >Cancel</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <!-- Add new note -->
                                                    <div class="bg-white p-3 rounded-xl border border-slate-200">
                                                        <textarea 
                                                            x-model="noteText" 
                                                            placeholder="Add a note..."
                                                            class="w-full bg-transparent border-none focus:ring-0 text-[11px] font-medium text-slate-700 min-h-[60px] resize-none p-0"
                                                        ></textarea>
                                                        <div class="flex justify-end mt-2">
                                                            <button 
                                                                @click="addNote" 
                                                                class="px-4 py-1.5 bg-indigo-600 text-white text-[10px] font-black uppercase rounded-lg shadow-md hover:bg-indigo-700 transition"
                                                                :disabled="isAddingNote || noteText.trim() === ''"
                                                            >
                                                                <span x-show="!isAddingNote">Post Note</span>
                                                                <span x-show="isAddingNote"><i class="fas fa-spinner fa-spin mr-1"></i>Posting...</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer: flex-shrink-0 keeps it fixed at bottom -->
                        <div class="flex-shrink-0 border-t border-slate-100 px-6 py-6 sm:px-8 bg-slate-50/50 flex justify-end gap-4">
                            <button @click="showView = false" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-4 rounded-2xl text-sm font-bold text-center transition shadow-lg border-b-4 border-slate-700 active:border-b-0 active:translate-y-1 uppercase tracking-widest">
                                CLOSE
                            </button>
                        </div>

                        <!-- Rejection Reason Modal -->
                        <template x-if="showRejectionModal">
                            <div class="fixed inset-0 z-50 flex items-center justify-center" x-data="{ localReason: rejectionReason }" @click.self="showRejectionModal = false; viewData.status = previousStatus">
                                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showRejectionModal = false; viewData.status = previousStatus"></div>
                                <div class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4 z-60" @click.stop>
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="h-12 w-12 rounded-full bg-amber-100 flex items-center justify-center">
                                            <i class="fas fa-exclamation text-xl text-amber-600"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-slate-900">Input Required</h3>
                                    </div>
                                    <p class="text-sm text-slate-600 mb-4">Provide a cancellation reason:</p>
                                    <textarea 
                                        x-model.debounce.120ms="localReason" 
                                        placeholder="Enter reason..."
                                        spellcheck="false"
                                        autocapitalize="off"
                                        autocorrect="off"
                                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-none min-h-[120px]"
                                    ></textarea>
                                    <div class="flex gap-3 mt-6">
                                        <button 
                                            @click="showRejectionModal = false; viewData.status = previousStatus" 
                                            class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2.5 rounded-lg font-bold transition"
                                        >
                                            Cancel
                                        </button>
                                        <button 
                                            @click="submitRejection(localReason)" 
                                            :disabled="!localReason.trim() || isSavingStatus"
                                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2.5 rounded-lg font-bold transition"
                                        >
                                            <template x-if="!isSavingStatus"><span>Submit</span></template>
                                            <template x-if="isSavingStatus"><span><i class="fas fa-spinner fa-spin mr-2"></i>Saving...</span></template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
    </div>

    <script>
        const INCIDENT_CSRF_TOKEN = '<?php echo htmlspecialchars($incident_csrf_token); ?>';
        const CURRENT_USER_ID = <?php echo (int)$current_user_id; ?>;
    </script>

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
                document.body.appendChild(el);
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

        function pageData() {
            return {
                reports: [],
                isLoading: false,
                isRefreshing: false,
                search: '',
                statusFilter: 'All',
                showView: false,
                loadingView: false,
                viewData: null,
                pollingInterval: null,
                currentUserId: CURRENT_USER_ID,
                notes: [],
                isAddingNote: false,
                isSavingRemarks: false,
                isSavingStatus: false,
                noteText: '',
                editingNoteId: null,
                editingNoteText: '',
                dateFrom: '',
                dateTo: '',
                showRejectionModal: false,
                rejectionReason: '',
                previousStatus: '',

                // Initialize stats from PHP
                stats: {
                    active_cases: <?php
echo $active_cases; ?>,
                    trending_today: <?php
echo $trending_today; ?>,
                    resolution_rate: <?php
echo $resolution_rate; ?>
                },

                init() {
                    this.fetchReports();
                    // Start polling but only if not interacting with view/dropdown
                    this.pollingInterval = setInterval(() => {
                        if (!this.showView && !this.isLoading) {
                            this.fetchReports(true);
                        }
                    }, 10000); // Poll every 10s for stability
                },

                async fetchReports(silent = false) {
                    if (!silent) this.isLoading = true;
                    this.isRefreshing = true;
                    try {
                        const response = await fetch('../partials/fetch-live-incidents.php');
                        const data = await response.json();
                        if (data.incidents) {
                            this.reports = data.incidents;
                        }
                        if (data.stats) {
                            this.stats = data.stats;
                        }
                    } catch (error) {
                        console.error('Error fetching reports:', error);
                    } finally {
                        this.isLoading = false;
                        this.isRefreshing = false;
                    }
                },

                get filteredReports() {
                    return this.reports.filter(r => {
                        const matchesSearch = this.search === '' || 
                            (r.resident_name && r.resident_name.toLowerCase().includes(this.search.toLowerCase())) ||
                            r.type.toLowerCase().includes(this.search.toLowerCase()) ||
                            r.location.toLowerCase().includes(this.search.toLowerCase());
                        
                        const matchesStatus = this.statusFilter === 'All' || r.status === this.statusFilter;
                        
                        // Date Filtering
                        let matchesDate = true;
                        if (r.reported_at) {
                            try {
                                const reportDate = new Date(r.reported_at).toISOString().split('T')[0];
                                if (this.dateFrom && reportDate < this.dateFrom) matchesDate = false;
                                if (this.dateTo && reportDate > this.dateTo) matchesDate = false;
                            } catch (e) {
                                console.warn('Invalid date:', r.reported_at);
                            }
                        } else if (this.dateFrom || this.dateTo) {
                            matchesDate = false; // Hide if status has dates but report doesn't
                        }
                        
                        return matchesSearch && matchesStatus && matchesDate;
                    });
                },

                exportCSV() {
                    const params = new URLSearchParams({
                        search: this.search,
                        status: this.statusFilter,
                        from: this.dateFrom,
                        to: this.dateTo
                    });
                    window.location.href = `../partials/export-incidents-csv.php?${params.toString()}`;
                },

                async openQuickView(id) {
                    console.log('Opening Quick View for ID:', id);
                    this.showView = true;
                    this.loadingView = true;
                    this.viewData = null;
                    this.remarksChanged = false;
                    this.isSavingRemarks = false;
                    try {
                        const response = await fetch(`../partials/get-incident-details.php?id=${id}`);
                        const result = await response.json();
                        if (result.success) {
                            this.viewData = result.data;
                        } else {
                            adminShowToast(result.error || 'Failed to load details.', 'error');
                            this.showView = false;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        adminShowToast('Failed to load details.', 'error');
                        this.showView = false;
                    } finally {
                        this.loadingView = false;
                    }
                },

                handleStatusChange() {
                    if (!this.viewData) return;
                    
                    // If changing to Rejected, show modal for reason
                    if (this.viewData.status === 'Rejected') {
                        this.previousStatus = this.reports.find(r => r.id === this.viewData.id)?.status || 'Pending';
                        this.rejectionReason = '';
                        this.showRejectionModal = true;
                        return;
                    }
                    
                    // Otherwise save status normally
                    this.saveStatus();
                },

                async submitRejection(reasonInput = '') {
                    const reason = String(reasonInput || '').trim();
                    if (!reason) {
                        adminShowToast('Please provide a rejection reason.', 'error');
                        return;
                    }

                    this.rejectionReason = reason;
                    this.isSavingStatus = true;
                    try {
                        const formData = new FormData();
                        formData.append('id', this.viewData.id);
                        formData.append('status', this.viewData.status);
                        formData.append('rejection_reason', reason);
                        formData.append('csrf_token', INCIDENT_CSRF_TOKEN);

                        const response = await fetch('../partials/update-incident-status-ajax.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showRejectionModal = false;
                            const report = this.reports.find(r => r.id === this.viewData.id);
                            if (report) report.status = this.viewData.status;
                            
                            if (result.stats) {
                                this.stats = result.stats;
                            }
                            adminShowToast('Report rejected successfully.', 'success');
                        } else {
                            adminShowToast(result.error || 'Failed to reject report', 'error');
                            this.viewData.status = this.previousStatus;
                        }
                    } catch (error) {
                        console.error('Error rejecting report:', error);
                        adminShowToast('An error occurred while rejecting report.', 'error');
                        this.viewData.status = this.previousStatus;
                    } finally {
                        this.isSavingStatus = false;
                    }
                },

                async saveStatus() {
                    if (!this.viewData) return;
                    this.isSavingStatus = true;
                    try {
                        const formData = new FormData();
                        formData.append('id', this.viewData.id);
                        formData.append('status', this.viewData.status);
                        formData.append('csrf_token', INCIDENT_CSRF_TOKEN);

                        const response = await fetch('../partials/update-incident-status-ajax.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            // Update local list for immediate visual sync
                            const report = this.reports.find(r => r.id === this.viewData.id);
                            if (report) report.status = this.viewData.status;
                            
                            // Update global stats
                            if (result.stats) {
                                this.stats = result.stats;
                            }
                            adminShowToast('Status updated successfully.', 'success');
                        } else {
                            adminShowToast(result.error || 'Failed to update status', 'error');
                        }
                    } catch (error) {
                        console.error('Error saving status:', error);
                        adminShowToast('An error occurred while updating status.', 'error');
                    } finally {
                        this.isSavingStatus = false;
                    }
                },

                async loadNotes(incidentId) {
                    try {
                        const response = await fetch(`../partials/get-incident-notes.php?id=${incidentId}`);
                        const result = await response.json();
                        if (result.success) {
                            this.notes = result.notes;
                        }
                    } catch (error) {
                        console.error('Error loading notes:', error);
                    }
                },

                async addNote() {
                    if (!this.viewData || !this.noteText.trim()) return;
                    this.isAddingNote = true;
                    try {
                        const formData = new FormData();
                        formData.append('incident_id', this.viewData.id);
                        formData.append('note', this.noteText.trim());
                        formData.append('csrf_token', INCIDENT_CSRF_TOKEN);

                        const response = await fetch('../partials/add-incident-note.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.noteText = '';
                            await this.loadNotes(this.viewData.id);
                            adminShowToast('Note added successfully.', 'success');
                        } else {
                            adminShowToast(result.error || 'Failed to add note', 'error');
                        }
                    } catch (error) {
                        console.error('Error adding note:', error);
                        adminShowToast('An error occurred while adding note.', 'error');
                    } finally {
                        this.isAddingNote = false;
                    }
                },

                startEditNote(note) {
                    this.editingNoteId = note.id;
                    this.editingNoteText = note.note;
                },

                cancelEditNote() {
                    this.editingNoteId = null;
                    this.editingNoteText = '';
                },

                async saveEditNote(noteId) {
                    if (!this.editingNoteText.trim()) return;
                    try {
                        const formData = new FormData();
                        formData.append('note_id', noteId);
                        formData.append('note', this.editingNoteText.trim());
                        formData.append('csrf_token', INCIDENT_CSRF_TOKEN);

                        const response = await fetch('../partials/edit-incident-note.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.editingNoteId = null;
                            this.editingNoteText = '';
                            await this.loadNotes(this.viewData.id);
                            adminShowToast('Note updated successfully.', 'success');
                        } else {
                            adminShowToast(result.error || 'Failed to update note', 'error');
                        }
                    } catch (error) {
                        console.error('Error updating note:', error);
                        adminShowToast('An error occurred while updating note.', 'error');
                    }
                },

                async deleteNote(noteId) {
                    if (!confirm('Delete this note?')) return;
                    try {
                        const formData = new FormData();
                        formData.append('note_id', noteId);
                        formData.append('csrf_token', INCIDENT_CSRF_TOKEN);

                        const response = await fetch('../partials/delete-incident-note.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            await this.loadNotes(this.viewData.id);
                            adminShowToast('Note deleted successfully.', 'success');
                        } else {
                            adminShowToast(result.error || 'Failed to delete note', 'error');
                        }
                    } catch (error) {
                        console.error('Error deleting note:', error);
                        adminShowToast('An error occurred while deleting note.', 'error');
                    }
                },

                formatDate(dateString) {
                    return new Date(dateString).toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric' 
                    });
                },

                formatTime(dateString) {
                    return new Date(dateString).toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: true 
                    });
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('incident-success-alert');
            if (alert) {
                setTimeout(() => {
                    alert.classList.add('opacity-0', 'transition-opacity', 'duration-1000');
                    setTimeout(() => alert.remove(), 1000);
                }, 4000);
            }
        });
    </script>
</body>
</html>


