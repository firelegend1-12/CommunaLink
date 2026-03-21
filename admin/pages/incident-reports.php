<?php
/**
 * Incident Reports Management - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$page_title = "Incident Reports";

// Fetch initial count for header
$stmt = $pdo->query("SELECT COUNT(*) FROM incidents");
$total_incidents = $stmt->fetchColumn();
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

                <!-- Main Grid -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Table Actions -->
                    <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap justify-between items-center bg-slate-50/50">
                        <div class="flex-grow w-full sm:w-auto mb-2 sm:mb-0 sm:mr-6">
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 group-focus-within:text-indigo-500 transition">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" x-model="search" 
                                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition shadow-sm"
                                    placeholder="Search by reporter, type or location...">
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200">
                                <button @click="statusFilter = 'All'" :class="statusFilter === 'All' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">ALL</button>
                                <button @click="statusFilter = 'Pending'" :class="statusFilter === 'Pending' ? 'bg-white text-amber-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">PENDING</button>
                                <button @click="statusFilter = 'Resolved'" :class="statusFilter === 'Resolved' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">RESOLVED</button>
                            </div>
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
                                    <th class="px-6 py-4 text-left text-xs font-black text-slate-500 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-widest">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <template x-for="report in filteredReports" :key="report.id">
                                    <tr class="hover:bg-indigo-50/30 transition group">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div :class="{
                                                    'h-10 w-10 rounded-xl flex items-center justify-center text-sm shadow-sm': true,
                                                    'bg-amber-100 text-amber-700': report.type === 'Fire' || report.type === 'Emergency',
                                                    'bg-indigo-100 text-indigo-700': report.type === 'Traffic' || report.type === 'Crime',
                                                    'bg-slate-100 text-slate-700': !['Fire', 'Emergency', 'Traffic', 'Crime'].includes(report.type)
                                                }">
                                                    <i :class="{
                                                        'fas': true,
                                                        'fa-fire': report.type === 'Fire',
                                                        'fa-ambulance': report.type === 'Emergency',
                                                        'fa-car': report.type === 'Traffic',
                                                        'fa-exclamation-triangle': !['Fire', 'Emergency', 'Traffic'].includes(report.type)
                                                    }"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-slate-900 group-hover:text-indigo-700 transition" x-text="report.type"></div>
                                                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-tighter" x-text="'ID: #' + report.id"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-slate-700" x-text="report.resident_name || 'System User'"></div>
                                            <div class="text-xs text-slate-400 italic">Resident</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-600 max-w-[200px] truncate" x-text="report.location"></div>
                                            <template x-if="report.latitude && report.longitude">
                                                <div class="flex items-center text-[10px] text-indigo-500 font-bold mt-0.5">
                                                    <i class="fas fa-map-marker-alt mr-1 text-[8px]"></i> GPS PINNED
                                                </div>
                                            </template>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-700 font-medium" x-text="formatDate(report.reported_at)"></div>
                                            <div class="text-[10px] text-slate-400 uppercase tracking-tighter" x-text="formatTime(report.reported_at)"></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="{
                                                'px-3 py-1 text-[10px] font-black uppercase rounded-full shadow-sm': true,
                                                'bg-amber-100 text-amber-700': report.status === 'Pending',
                                                'bg-indigo-100 text-indigo-700': report.status === 'Under Review' || report.status === 'In Progress',
                                                'bg-emerald-100 text-emerald-700': report.status === 'Resolved',
                                                'bg-rose-100 text-rose-700': report.status === 'Rejected'
                                            }" x-text="report.status"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end space-x-2">
                                                <button @click="openQuickView(report.id)" class="bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white p-2 rounded-xl transition shadow-sm group/btn">
                                                    <span class="sr-only">Quick View</span>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <div x-data="{ open: false }" class="relative">
                                                     <button @click="open = !open" class="text-slate-400 hover:text-slate-600 p-2 transition">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div x-show="open" @click.away="open = false" x-cloak
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
    <div x-show="showView" 
         class="fixed inset-0 overflow-hidden z-50" 
         x-cloak
         aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <div class="absolute inset-0 overflow-hidden">
            <!-- Background backdrop -->
            <div x-show="showView" 
                 x-transition:enter="ease-in-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in-out duration-500" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
                 class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" @click="showView = false" aria-hidden="true"></div>

            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div x-show="showView" 
                     x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700" 
                     x-transition:enter-start="translate-x-full" 
                     x-transition:enter-end="translate-x-0" 
                     x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700" 
                     x-transition:leave-start="translate-x-0" 
                     x-transition:leave-end="translate-x-full" 
                     class="pointer-events-auto w-screen max-w-2xl">
                    <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl">
                        <!-- Head -->
                        <div class="bg-indigo-700 px-6 py-8 sm:px-8 relative overflow-hidden">
                            <div class="absolute top-0 right-0 -mt-10 -mr-10 h-40 w-40 rounded-full bg-white/10 blur-3xl"></div>
                            <div class="flex items-start justify-between relative z-10">
                                <div class="flex items-center gap-4">
                                    <template x-if="viewData">
                                        <div :class="{
                                            'h-16 w-16 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-2xl shadow-lg': true,
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
                                    <!-- Status Banner -->
                                    <div :class="{
                                        'p-4 rounded-2xl border flex items-center justify-between': true,
                                        'bg-amber-50 border-amber-100 text-amber-800': viewData.status === 'Pending',
                                        'bg-indigo-50 border-indigo-100 text-indigo-800': viewData.status === 'In Progress' || viewData.status === 'Under Review',
                                        'bg-emerald-50 border-emerald-100 text-emerald-800': viewData.status === 'Resolved',
                                        'bg-rose-50 border-rose-100 text-rose-800': viewData.status === 'Rejected'
                                    }">
                                        <div class="flex items-center">
                                            <div class="h-2 w-2 rounded-full mr-3 animate-pulse" :class="{
                                                'bg-amber-500': viewData.status === 'Pending',
                                                'bg-indigo-500': viewData.status === 'In Progress',
                                                'bg-emerald-500': viewData.status === 'Resolved',
                                                'bg-rose-500': viewData.status === 'Rejected'
                                            }"></div>
                                            <span class="text-xs font-black uppercase tracking-widest" x-text="'STATUS: ' + viewData.status"></span>
                                        </div>
                                        <a :href="'update-incident.php?id=' + viewData.id" class="text-[10px] font-bold underline hover:no-underline uppercase tracking-wider">Update Status</a>
                                    </div>

                                    <!-- Reporter Info -->
                                    <div>
                                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-3 mb-5 flex items-center">
                                            <i class="fas fa-user-shield mr-3 text-indigo-500"></i> REPORTER INFORMATION
                                        </h3>
                                        <div class="grid grid-cols-2 gap-4 bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                            <div>
                                                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Full Name</p>
                                                <p class="text-sm font-bold text-slate-800" x-text="viewData.reporter_name"></p>
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Contact No.</p>
                                                <p class="text-sm font-bold text-slate-800" x-text="viewData.reporter_contact || 'N/A'"></p>
                                            </div>
                                            <div class="col-span-2">
                                                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Email Address</p>
                                                <p class="text-sm font-bold text-indigo-600 truncate" x-text="viewData.reporter_email"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Incident Details -->
                                    <div>
                                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 pb-3 mb-5 flex items-center">
                                            <i class="fas fa-info-circle mr-3 text-indigo-500"></i> INCIDENT DETAILS
                                        </h3>
                                        <div class="space-y-6">
                                            <div>
                                                <p class="text-[10px] font-black text-slate-400 uppercase mb-2">Location & Landmark</p>
                                                <div class="bg-indigo-50/50 p-4 rounded-xl text-sm font-medium text-slate-700 border border-indigo-100" x-text="viewData.location"></div>
                                            </div>
                                            
                                            <template x-if="viewData.description">
                                                <div>
                                                    <p class="text-[10px] font-black text-slate-400 uppercase mb-2">Description / Remarks</p>
                                                    <div class="text-sm leading-relaxed text-slate-600 bg-white p-4 rounded-xl border border-slate-100" x-text="viewData.description"></div>
                                                </div>
                                            </template>

                                            <!-- Photos if any -->
                                            <template x-if="viewData.image_path">
                                                <div>
                                                    <p class="text-[10px] font-black text-slate-400 uppercase mb-3 text-center">Attached Media</p>
                                                    <div class="rounded-2xl overflow-hidden border-2 border-slate-100 shadow-lg">
                                                        <img :src="'../../' + viewData.image_path" class="w-full h-auto object-cover max-h-[300px]">
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Map Link Card -->
                                            <template x-if="viewData.latitude">
                                                <div class="bg-emerald-50 p-5 rounded-2xl border border-emerald-100 flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <div class="h-12 w-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 mr-4 shadow-sm">
                                                            <i class="fas fa-map-marked-alt text-xl"></i>
                                                        </div>
                                                        <div>
                                                            <p class="text-sm font-bold text-emerald-900">GPS Coordinates Attached</p>
                                                            <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider" x-text="viewData.latitude + ', ' + viewData.longitude"></p>
                                                        </div>
                                                    </div>
                                                    <a :href="'https://www.google.com/maps?q=' + viewData.latitude + ',' + viewData.longitude" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-black uppercase transition-all shadow-md shadow-emerald-600/20">Open Map</a>
                                                </div>
                                            </template>
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
    </div>

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
                        
                        return matchesSearch && matchesStatus;
                    });
                },

                async openQuickView(id) {
                    this.showView = true;
                    this.loadingView = true;
                    this.viewData = null;
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