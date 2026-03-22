<?php
/**
 * Incident Reports Management - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/permission_checker.php';

require_login();

// Check manage_incidents permission (admin, barangay-captain, kagawad, barangay-tanod)
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

// 4. Resolution Rate (Current Month)
$stmt = $pdo->query("SELECT 
    COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved,
    COUNT(*) as total
    FROM incidents 
    WHERE MONTH(reported_at) = MONTH(CURRENT_DATE()) AND YEAR(reported_at) = YEAR(CURRENT_DATE())");
$current_month_stats = $stmt->fetch();
$resolution_rate = ($current_month_stats['total'] > 0) 
    ? round(($current_month_stats['resolved'] / $current_month_stats['total']) * 100) 
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
    <title><?php echo $page_title; ?> - CommuniLink</title>
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
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="pageData()">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white/80 backdrop-blur-md shadow-sm z-10 border-b border-slate-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center gap-4">
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Incidents</h1>
                            <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase"><?php echo $total_incidents; ?> Reports</span>
                        </div>
                        
                        <!-- Refresh Button & User -->
                        <div class="flex items-center gap-4">
                             <button @click="fetchReports" class="text-slate-500 hover:text-indigo-600 p-2 transition" title="Refresh Data">
                                <i class="fas fa-sync-alt" :class="{ 'animate-spin': isRefreshing }"></i>
                            </button>
                            
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" class="flex items-center space-x-3 text-sm text-slate-700 hover:text-slate-900 focus:outline-none transition group">
                                    <span class="font-medium group-hover:text-indigo-600"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-sm font-bold shadow-sm group-hover:shadow-md transition">
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
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div id="incident-success-alert" class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-r-xl shadow-sm animate-fade-in" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>
                            <p class="font-bold text-sm"><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                        </div>
                    </div>
                <?php unset($_SESSION['success_message']); endif; ?>

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
                                    <span class="text-[10px] font-bold text-slate-400 mb-0.5">mtd</span>
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
                                <h3 class="text-xl font-black text-slate-900 leading-tight truncate max-w-[140px]"><?php echo $critical_type; ?></h3>
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
                                <button @click="statusFilter = 'In Progress'" :class="statusFilter === 'In Progress' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">IN PROGRESS</button>
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
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50/30">
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Incident</th>
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Reporter</th>
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Location</th>
                                     <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Date Reported</th>
                                    <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-widest">Action</th>
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
                                                            <img :src="'../../' + report.image_path" class="h-full w-full object-cover">
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
                                        <td @click="openQuickView(report.id)" class="px-6 py-4 whitespace-nowrap text-left">
                                        <span :class="{
                                            'px-3 py-1 text-[10px] font-black uppercase rounded-full shadow-sm transition-all duration-300': true,
                                            'bg-rose-100 text-rose-700 border border-rose-200': report.status === 'Pending',
                                            'bg-indigo-100 text-indigo-700 border border-indigo-200': report.status === 'Under Review' || report.status === 'In Progress',
                                            'bg-emerald-100 text-emerald-700 border border-emerald-200': report.status === 'Resolved',
                                            'bg-slate-100 text-slate-700 border border-slate-200': report.status === 'Rejected'
                                        }" x-text="report.status"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button @click.stop="openQuickView(report.id)" class="text-indigo-600 hover:bg-indigo-50 p-2 rounded-lg transition" title="Quick View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div x-data="{ openMenu: false }" class="relative" @click.stop>
                                                <button @click="openMenu = !openMenu" class="text-slate-400 hover:text-slate-600 p-2 transition">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div x-show="openMenu" @click.away="openMenu = false" x-cloak
                                                     class="absolute right-0 mt-2 w-48 rounded-xl shadow-xl bg-white ring-1 ring-black ring-opacity-5 z-20 overflow-hidden divide-y divide-slate-50">
                                                    <a :href="'update-incident.php?id=' + report.id" class="flex items-center px-4 py-3 text-sm text-slate-700 hover:bg-indigo-50">
                                                        <i class="fas fa-edit mr-3 text-indigo-500 w-4"></i> Update Status
                                                    </a>
                                                    <template x-if="report.latitude">
                                                        <a :href="'https://www.google.com/maps?q=' + report.latitude + ',' + report.longitude" target="_blank" class="flex items-center px-4 py-3 text-sm text-slate-700 hover:bg-indigo-50">
                                                            <i class="fas fa-map-marked-alt mr-3 text-emerald-500 w-4"></i> View on Map
                                                        </a>
                                                    </template>
                                                </div>
                                            </div>
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
    </div>

    <!-- Quick View Slide-over Panel -->
    <template x-if="showView">
        <div class="fixed inset-0 overflow-hidden z-50 shadow-2xl" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
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
                         class="pointer-events-auto w-screen max-w-2xl">
                        <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl">
                            <!-- Premium Header -->
                            <div class="bg-indigo-700 px-6 py-10 sm:px-8 relative overflow-hidden">
                                <!-- Abstract Background Pattern -->
                                <div class="absolute inset-0 opacity-10">
                                    <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                        <path d="M0 100 C 20 0 50 0 100 100 Z" fill="white"></path>
                                    </svg>
                                </div>
                                <div class="absolute top-0 right-0 -mt-10 -mr-10 h-40 w-40 rounded-full bg-white/10 blur-3xl"></div>
                                
                                <div class="relative flex items-center justify-between z-10">
                                    <div class="flex items-center gap-5">
                                        <template x-if="viewData">
                                            <div :class="{
                                                'h-20 w-20 rounded-3xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-3xl shadow-xl transition-transform duration-500 hover:rotate-3 hover:scale-105': true,
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
                                        </template>
                                        <div>
                                            <h2 class="text-2xl font-black text-white leading-tight uppercase tracking-tight" id="slide-over-title" x-text="viewData ? viewData.type : 'Loading...'"></h2>
                                            <div class="flex items-center mt-2 gap-3 text-indigo-100">
                                                <span class="text-[10px] font-black uppercase bg-white/20 px-2 py-0.5 rounded-lg tracking-widest" x-text="'ID: #' + (viewData ? viewData.id : '')"></span>
                                                <span class="text-xs font-bold" x-text="viewData ? formatDate(viewData.reported_at) + ' @ ' + formatTime(viewData.reported_at) : ''"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ml-3 flex h-7 items-center">
                                        <button @click="showView = false" class="rounded-lg bg-white/10 text-white hover:bg-white/20 transition p-2 focus:outline-none ring-1 ring-white/30">
                                            <i class="fas fa-times text-xl"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Content -->
                        <div class="relative flex-1 px-6 py-8 sm:px-8">
                            <template x-if="loadingView">
                                <div class="flex flex-col items-center justify-center h-64">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-700 mb-4"></div>
                                    <p class="text-slate-500 font-medium tracking-wide italic">Fetching report details...</p>
                                </div>
                            </template>

                            <template x-if="!loadingView && viewData">
                                <div class="space-y-10">
                                    <div class="flex items-start gap-8">
                                        <!-- Left Column: Details -->
                                        <div class="flex-1 space-y-10">
                                            <!-- Status Control Panel -->
                                            <div :class="{
                                                'p-5 rounded-3xl border flex flex-col sm:flex-row items-center justify-between gap-4 transition-all duration-500': true,
                                                'bg-amber-50 border-amber-100': viewData.status === 'Pending',
                                                'bg-indigo-50 border-indigo-100': viewData.status === 'In Progress' || viewData.status === 'Under Review',
                                                'bg-emerald-50 border-emerald-100': viewData.status === 'Resolved',
                                                'bg-rose-50 border-rose-100': viewData.status === 'Rejected'
                                            }">
                                                <div class="flex items-center">
                                                    <div class="h-3 w-3 rounded-full mr-4 animate-pulse shadow-sm" :class="{
                                                        'bg-amber-500': viewData.status === 'Pending',
                                                        'bg-indigo-500': viewData.status === 'In Progress',
                                                        'bg-emerald-500': viewData.status === 'Resolved',
                                                        'bg-rose-500': viewData.status === 'Rejected'
                                                    }"></div>
                                                    <div>
                                                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mb-0.5">CURRENT STATE</p>
                                                        <span class="text-xs font-black uppercase tracking-widest block" :class="{
                                                            'text-amber-800': viewData.status === 'Pending',
                                                            'text-indigo-800': viewData.status === 'In Progress',
                                                            'text-emerald-800': viewData.status === 'Resolved',
                                                            'text-rose-800': viewData.status === 'Rejected'
                                                        }" x-text="viewData.status"></span>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 bg-white/50 p-1.5 rounded-2xl border border-white/50 backdrop-blur-sm self-stretch sm:self-auto">
                                                    <select 
                                                        x-model="viewData.status" 
                                                        @change="saveStatus"
                                                        :disabled="isSavingStatus"
                                                        class="bg-transparent border-none text-[10px] font-black uppercase tracking-wider focus:ring-0 cursor-pointer disabled:opacity-50"
                                                    >
                                                        <option value="Pending">Pending</option>
                                                        <option value="In Progress">In Progress</option>
                                                        <option value="Resolved">Resolved</option>
                                                        <option value="Rejected">Rejected</option>
                                                    </select>
                                                    <template x-if="isSavingStatus">
                                                        <i class="fas fa-spinner fa-spin text-indigo-600 text-xs mr-2"></i>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Reporter Info -->
                                            <div class="group/section">
                                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-3 mb-5 flex items-center group-hover/section:text-indigo-500 transition-colors">
                                                    <i class="fas fa-user-shield mr-3"></i> REPORTER INFORMATION
                                                </h3>
                                                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group/reporter text-sm">
                                                    <div class="absolute top-0 right-0 p-4 opacity-0 group-hover/reporter:opacity-100 transition-opacity">
                                                        <a :href="'residents.php?search=' + encodeURIComponent(viewData.reporter_name)" class="text-[10px] font-black uppercase bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-xl hover:bg-indigo-600 hover:text-white transition-all">View History</a>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <p class="text-[10px] font-black text-slate-400 uppercase mb-1 tracking-tighter">Full Name</p>
                                                            <p class="font-bold text-slate-800" x-text="viewData.reporter_name"></p>
                                                        </div>
                                                        <div>
                                                            <p class="text-[10px] font-black text-slate-400 uppercase mb-1 tracking-tighter">Contact No.</p>
                                                            <p class="font-bold text-slate-800" x-text="viewData.reporter_contact || 'N/A'"></p>
                                                        </div>
                                                        <div class="col-span-2">
                                                            <p class="text-[10px] font-black text-slate-400 uppercase mb-1 tracking-tighter">Email Address</p>
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
                                                        <p class="text-[10px] font-black text-slate-400 uppercase mb-2 tracking-tighter text-indigo-500">Investigation Map</p>
                                                        <div class="rounded-2xl overflow-hidden border border-slate-200 shadow-sm h-48 relative bg-slate-100 group/map">
                                                            <template x-if="viewData.latitude">
                                                                <iframe 
                                                                    class="w-full h-full grayscale-[0.3] contrast-[1.1] hover:grayscale-0 transition-all duration-700"
                                                                    frameborder="0" 
                                                                    scrolling="no" 
                                                                    marginheight="0" 
                                                                    marginwidth="0" 
                                                                    :src="'https://maps.google.com/maps?q=' + viewData.latitude + ',' + viewData.longitude + '&hl=es&z=14&output=embed'"
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
                                                        <div class="text-xs font-semibold leading-relaxed text-slate-600 bg-white p-4 rounded-xl border border-slate-100" x-text="viewData.description || 'No description provided.'"></div>
                                                    </div>

                                                    <!-- Photos -->
                                                    <template x-if="viewData.image_path">
                                                        <div>
                                                            <p class="text-[10px] font-black text-slate-400 uppercase mb-3 text-center tracking-[0.3em]">ATTACHED EVIDENCE</p>
                                                            <div class="rounded-3xl overflow-hidden border border-slate-200 shadow-lg group/media cursor-zoom-in">
                                                                <img :src="'../../' + viewData.image_path" class="w-full h-auto object-cover max-h-[400px]">
                                                                <div class="bg-indigo-600 p-3 flex items-center justify-between">
                                                                    <span class="text-[10px] font-black text-white uppercase tracking-widest"><i class="fas fa-camera mr-2"></i> Field Photograph</span>
                                                                    <a :href="'../../' + viewData.image_path" target="_blank" class="text-[10px] font-black uppercase text-white hover:underline bg-white/20 px-3 py-1 rounded-lg">Source</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column: Timeline & Notes -->
                                        <div class="w-64 space-y-10">
                                            <!-- Resolution Stepper -->
                                            <div class="group/section">
                                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-3 mb-5 group-hover/section:text-indigo-500 transition-colors">TIMELINE</h3>
                                                <div class="relative pl-6 space-y-8 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                                                    <!-- Step: Reported -->
                                                    <div class="relative">
                                                        <div class="absolute -left-[23px] h-4 w-4 rounded-full border-4 border-white bg-indigo-500 shadow-sm"></div>
                                                        <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Reported</p>
                                                        <p class="text-xs font-bold text-slate-800" x-text="formatDate(viewData.reported_at)"></p>
                                                        <p class="text-[10px] text-slate-400" x-text="formatTime(viewData.reported_at)"></p>
                                                    </div>

                                                    <!-- Step: Processing -->
                                                    <div class="relative">
                                                        <div :class="{
                                                            'absolute -left-[23px] h-4 w-4 rounded-full border-4 border-white shadow-sm': true,
                                                            'bg-indigo-500': viewData.status !== 'Pending',
                                                            'bg-slate-300': viewData.status === 'Pending'
                                                        }"></div>
                                                        <p :class="viewData.status !== 'Pending' ? 'text-[10px] font-black text-indigo-600 uppercase tracking-widest' : 'text-[10px] font-black text-slate-400 uppercase tracking-widest'">Acknowledge</p>
                                                        <p class="text-xs font-bold" :class="viewData.status !== 'Pending' ? 'text-slate-800' : 'text-slate-400'" x-text="viewData.status !== 'Pending' ? 'Confirmed by Admin' : 'Awaiting Review'"></p>
                                                    </div>

                                                    <!-- Step: Resolved -->
                                                    <div class="relative">
                                                        <div :class="{
                                                            'absolute -left-[23px] h-4 w-4 rounded-full border-4 border-white shadow-sm': true,
                                                            'bg-emerald-500': viewData.status === 'Resolved',
                                                            'bg-slate-300': viewData.status !== 'Resolved'
                                                        }"></div>
                                                        <p :class="viewData.status === 'Resolved' ? 'text-[10px] font-black text-emerald-600 uppercase tracking-widest' : 'text-[10px] font-black text-slate-400 uppercase tracking-widest'">Resolution</p>
                                                        <p class="text-xs font-bold" :class="viewData.status === 'Resolved' ? 'text-slate-800' : 'text-slate-400'" x-text="viewData.status === 'Resolved' ? 'Case Closed' : 'In Progress'"></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Admin Remarks Editor -->
                                            <div class="group/section">
                                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-3 mb-5 group-hover/section:text-indigo-500 transition-colors">ADMIN NOTES</h3>
                                                <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 shadow-inner relative group/remarks">
                                                    <textarea 
                                                        x-model="viewData.admin_remarks" 
                                                        placeholder="Enter investigation notes..."
                                                        class="w-full bg-transparent border-none focus:ring-0 text-xs font-medium text-slate-700 min-h-[150px] resize-none"
                                                        @input="remarksChanged = true"
                                                    ></textarea>
                                                    <div class="mt-4 flex flex-col gap-2">
                                                        <button 
                                                            x-show="remarksChanged" 
                                                            @click="saveRemarks" 
                                                            class="w-full bg-indigo-600 text-white text-[10px] font-black uppercase py-2 rounded-xl shadow-md hover:bg-indigo-700 transition"
                                                            :disabled="isSavingRemarks"
                                                        >
                                                            <template x-if="!isSavingRemarks"><span><i class="fas fa-save mr-1.5"></i> Save Notes</span></template>
                                                            <template x-if="isSavingRemarks"><span><i class="fas fa-spinner fa-spin mr-1.5"></i> Saving...</span></template>
                                                        </button>
                                                        <p class="text-[9px] text-slate-400 text-center font-bold">Confidential internal remarks only visible to Barangay officials.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-slate-100 px-6 py-6 sm:px-8 bg-slate-50/50 flex justify-between gap-4">
                             <template x-if="viewData">
                                <a :href="'update-incident.php?id=' + viewData.id" class="flex-1 bg-white hover:bg-slate-50 text-slate-700 px-4 py-4 rounded-2xl text-sm font-bold border border-slate-200 text-center transition shadow-sm border-b-4 active:border-b-0 active:translate-y-1">
                                    <i class="fas fa-edit mr-2 text-indigo-500"></i> Update Report
                                </a>
                             </template>
                            <button @click="showView = false" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white px-4 py-4 rounded-2xl text-sm font-bold text-center transition shadow-lg border-b-4 border-slate-700 active:border-b-0 active:translate-y-1 uppercase tracking-widest">
                                CLOSE
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
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
                remarksChanged: false,
                isSavingRemarks: false,
                isSavingStatus: false,
                dateFrom: '',
                dateTo: '',

                // Initialize stats from PHP
                stats: {
                    active_cases: <?php echo $active_cases; ?>,
                    trending_today: <?php echo $trending_today; ?>,
                    resolution_rate: <?php echo $resolution_rate; ?>
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
                            alert(result.error);
                            this.showView = false;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert("Failed to load details.");
                        this.showView = false;
                    } finally {
                        this.loadingView = false;
                    }
                },

                async saveStatus() {
                    if (!this.viewData) return;
                    this.isSavingStatus = true;
                    try {
                        const formData = new FormData();
                        formData.append('id', this.viewData.id);
                        formData.append('status', this.viewData.status);

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
                        } else {
                            alert(result.error || 'Failed to update status');
                        }
                    } catch (error) {
                        console.error('Error saving status:', error);
                        alert('An error occurred.');
                    } finally {
                        this.isSavingStatus = false;
                    }
                },

                async saveRemarks() {
                    if (!this.viewData || !this.remarksChanged) return;
                    this.isSavingRemarks = true;
                    try {
                        const formData = new FormData();
                        formData.append('id', this.viewData.id);
                        formData.append('remarks', this.viewData.admin_remarks || '');

                        const response = await fetch('../partials/update-incident-remarks.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.remarksChanged = false;
                            // Optionally refresh reports to sync the data
                            this.fetchReports(true);
                        } else {
                            alert(result.error || 'Failed to save remarks');
                        }
                    } catch (error) {
                        console.error('Error saving remarks:', error);
                        alert('An error occurred while saving.');
                    } finally {
                        this.isSavingRemarks = false;
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