<?php
/**
 * Announcements Management - Modernized
 */
require_once '../partials/admin_auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/business_announcement_functions.php';
require_once '../../includes/csrf.php';

$page_title = "Manage Announcements";

// Get filters
$status_filter   = isset($_GET['status'])   ? sanitize_input($_GET['status'])   : '';
$priority_filter = isset($_GET['priority']) ? sanitize_input($_GET['priority']) : '';

// Fetch unique resident addresses for targeting
$stmt_addr = $pdo->query("SELECT DISTINCT address FROM residents WHERE user_id IS NOT NULL AND address != '' ORDER BY address ASC");
$resident_addresses = $stmt_addr->fetchAll(PDO::FETCH_COLUMN);

try {
    // Build query with filters
    $sql = "SELECT a.*, u.fullname as author_name FROM announcements a JOIN users u ON a.user_id = u.id WHERE 1=1";
    $params = [];

    if ($status_filter) {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
    }

    if ($priority_filter) {
        $sql .= " AND a.priority = ?";
        $params[] = $priority_filter;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll();
    
    // Get statistics
    $stats = getAnnouncementStats();
    
} catch (PDOException $e) {
    $announcements = [];
    $stats = ['total_announcements' => 0, 'active_announcements' => 0, 'urgent_announcements' => 0];
    $_SESSION['error_message'] = "Database error fetching announcements: " . $e->getMessage();
}
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
    <div class="flex h-screen overflow-hidden" x-data="{ 
        showAddModal: false, 
        showEditModal: false, 
        showDeleteModal: false,
        isEvent: false,
        isScheduled: false,
        editingPost: null,
        postToDelete: null
    }">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white/80 backdrop-blur-md shadow-sm z-10 border-b border-slate-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center gap-4">
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Community Board</h1>
                            <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-indigo-200"><?php echo $stats['total_announcements']; ?> Updates</span>
                        </div>
                        
                        <!-- User Dropdown & Action -->
                        <div class="flex items-center gap-4">
                            <button @click="showAddModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center transition shadow-md shadow-indigo-500/20">
                                <i class="fas fa-plus mr-2"></i> NEW POST
                            </button>
                            
                            <div class="h-8 w-px bg-slate-200 mx-2"></div>
                            
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" class="flex items-center space-x-3 text-sm text-slate-700 hover:text-slate-900 focus:outline-none group">
                                    <span class="font-medium group-hover:text-indigo-600 transition tracking-tight"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-sm font-bold shadow-sm group-hover:shadow-md transition">
                                        <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                    </div>
                                </button>
                                <div x-show="open" @click.away="open = false" x-cloak
                                     class="origin-top-right absolute right-0 mt-2 w-48 rounded-xl shadow-xl py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20 overflow-hidden divide-y divide-slate-50">
                                    <a href="account.php" class="flex items-center px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition"><i class="fas fa-user-circle mr-3 text-slate-400"></i> Profile Setting</a>
                                    <a href="../../includes/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition"><i class="fas fa-sign-out-alt mr-3"></i> Sign Out</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-8">
                <?php if (isset($_SESSION['announcement_success_message'])): ?>
                    <div id="announcement-success-alert" class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-r-xl shadow-sm animate-fade-in" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>
                            <p class="font-bold text-sm"><?php echo htmlspecialchars($_SESSION['announcement_success_message']); ?></p>
                        </div>
                    </div>
                <?php unset($_SESSION['announcement_success_message']); endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-4 mb-6 rounded-r-xl shadow-sm" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <p class="font-bold text-sm"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                        </div>
                    </div>
                <?php unset($_SESSION['error_message']); endif; ?>

                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition duration-500">
                            <i class="fas fa-bullhorn text-8xl"></i>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Posts</p>
                                <h3 class="text-3xl font-black text-slate-900"><?php echo $stats['total_announcements'] ?? 0; ?></h3>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 shadow-sm shadow-indigo-100">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-[10px] font-bold text-emerald-600">
                            <i class="fas fa-arrow-up mr-1 text-[8px]"></i> ALL TIME STORAGE
                        </div>
                    </div>
                    
                    <!-- Active -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition duration-500">
                            <i class="fas fa-globe text-8xl"></i>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Currently Live</p>
                                <h3 class="text-3xl font-black text-emerald-600"><?php echo $stats['active_announcements'] ?? 0; ?></h3>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shadow-sm shadow-emerald-100">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-[10px] font-bold text-emerald-600 animate-pulse">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-2"></span> SYNCED WITH PORTAL
                        </div>
                    </div>
                    
                    <!-- Urgent -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition duration-500 text-amber-600">
                            <i class="fas fa-exclamation-triangle text-8xl"></i>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Urgent Alerts</p>
                                <h3 class="text-3xl font-black text-amber-600"><?php echo $stats['urgent_announcements'] ?? 0; ?></h3>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 shadow-sm shadow-amber-100">
                                <i class="fas fa-bolt"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-[10px] font-bold text-amber-600">
                            <i class="fas fa-priority-high mr-1 text-[8px]"></i> PRIORITY NOTIFICATION
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap justify-between items-center bg-slate-50/50">
                        <div class="flex items-center gap-4">
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Active Announcements</h3>
                        </div>
                        
                        <div class="flex items-center gap-3">
                             <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200">
                                <a href="announcements.php" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?php echo empty($status_filter) ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-indigo-600'; ?>">ALL</a>
                                <a href="announcements.php?status=active" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?php echo $status_filter === 'active' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-emerald-600'; ?>">ACTIVE</a>
                                <a href="announcements.php?status=draft" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?php echo $status_filter === 'draft' ? 'bg-white text-amber-600 shadow-sm' : 'text-slate-500 hover:text-amber-600'; ?>">DRAFTS</a>
                            </div>
                            
                            <select onchange="location = this.value;" class="bg-white border border-slate-200 text-xs font-bold py-2 px-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                <option value="announcements.php">ALL PRIORITIES</option>
                                <option value="announcements.php?priority=urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>URGENT ONLY</option>
                                <option value="announcements.php?priority=normal" <?php echo $priority_filter === 'normal' ? 'selected' : ''; ?>>NORMAL ONLY</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-slate-100">
                            <colgroup>
                                <col class="w-[30%]">
                                <col class="w-[14%]">
                                <col class="w-[14%]">
                                <col class="w-[14%]">
                                <col class="w-[14%]">
                                <col class="w-[14%]">
                            </colgroup>
                            <thead>
                                <tr class="bg-slate-50/30">
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Post Info</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Priority</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Engagement</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Created By</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (empty($announcements)): ?>
                                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500 italic">No announcements found in this category.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($announcements as $ann): ?>
                                        <?php
                                            $edit_payload = [
                                                'id' => (int) $ann['id'],
                                                'title' => (string) ($ann['title'] ?? ''),
                                                'content' => (string) ($ann['content'] ?? ''),
                                                'status' => (string) ($ann['status'] ?? 'active'),
                                                'priority' => (string) ($ann['priority'] ?? 'normal'),
                                                'is_event' => (bool) ($ann['is_event'] ?? false),
                                                'event_date' => (string) ($ann['event_date'] ?? ''),
                                                'event_time' => (string) ($ann['event_time'] ?? ''),
                                                'event_location' => (string) ($ann['event_location'] ?? ''),
                                                'event_type' => (string) ($ann['event_type'] ?? ''),
                                                'target_audience' => (string) ($ann['target_audience'] ?? 'all'),
                                                'publish_date_only' => $ann['publish_date'] ? date('Y-m-d', strtotime($ann['publish_date'])) : '',
                                                'publish_time_only' => $ann['publish_date'] ? date('H:i', strtotime($ann['publish_date'])) : '',
                                                'expiry_date_only' => $ann['expiry_date'] ? date('Y-m-d', strtotime($ann['expiry_date'])) : '',
                                                'is_scheduled' => $ann['publish_date'] && strtotime($ann['publish_date']) > strtotime($ann['created_at'])
                                            ];
                                            $edit_payload_json = json_encode(
                                                $edit_payload,
                                                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                                            );
                                        ?>
                                        <tr class="hover:bg-indigo-50/30 transition group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="h-10 w-10 flex-shrink-0 bg-slate-100 rounded-xl overflow-hidden border border-slate-200 flex items-center justify-center text-indigo-500">
                                                        <?php if ($ann['is_event']): ?>
                                                            <i class="fas fa-calendar-star text-lg"></i>
                                                        <?php elseif ($ann['image_path']): ?>
                                                            <img src="../../<?= $ann['image_path'] ?>" class="h-full w-full object-cover">
                                                        <?php else: ?>
                                                            <i class="fas fa-bullhorn text-lg text-slate-400"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ml-4 max-w-xs">
                                                        <div class="text-sm font-bold text-slate-900 truncate"><?= htmlspecialchars($ann['title']) ?></div>
                                                        <div class="text-[10px] text-slate-400 font-bold uppercase mt-0.5 tracking-tighter">
                                                            <?php if ($ann['is_event']): ?>
                                                                <span class="text-indigo-600"><i class="far fa-calendar-alt mr-1"></i> <?= date('M d, Y', strtotime($ann['event_date'])) ?></span>
                                                                <span class="mx-1">•</span>
                                                                <span><?= htmlspecialchars($ann['event_location']) ?></span>
                                                            <?php else: ?>
                                                                <?= date('M d, Y @ h:i A', strtotime($ann['created_at'])) ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex flex-col gap-1.5">
                                                    <?php 
                                                        $now = time();
                                                        $publish_time = $ann['publish_date'] ? strtotime($ann['publish_date']) : 0;
                                                        $expiry_time = $ann['expiry_date'] ? strtotime($ann['expiry_date']) : 0;
                                                        $is_expired = $expiry_time > 0 && $expiry_time < $now;
                                                        $is_scheduled = $publish_time > $now;
                                                        
                                                        if ($ann['status'] === 'draft'): 
                                                    ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-slate-100 text-slate-500 border border-slate-200 uppercase tracking-tighter">Draft</span>
                                                    <?php elseif ($is_expired): ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-rose-50 text-rose-600 border border-rose-100 uppercase tracking-tighter">Expired</span>
                                                    <?php elseif ($is_scheduled): ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-amber-50 text-amber-600 border border-amber-100 uppercase tracking-tighter">Scheduled</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-tighter">Active</span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black bg-indigo-50 text-indigo-500 border border-indigo-100 uppercase tracking-tighter self-start">
                                                        <?= $ann['is_event'] ? 'Event' : 'Announcement' ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center text-[10px] font-black uppercase tracking-widest <?= $ann['priority'] === 'urgent' ? 'text-amber-600' : 'text-indigo-400' ?>">
                                                    <i class="fas <?= $ann['priority'] === 'urgent' ? 'fa-bolt' : 'fa-check' ?> mr-2"></i>
                                                    <?= $ann['priority'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center text-xs text-slate-600 font-bold">
                                                    <i class="fas fa-eye mr-2 text-indigo-400"></i>
                                                    <?= $ann['read_count'] ?? 0 ?> Views
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-slate-700"><?= htmlspecialchars($ann['author_name']) ?></div>
                                                <div class="flex items-center gap-1.5 mt-0.5 text-slate-400">
                                                    <i class="fas fa-users text-[8px]"></i>
                                                    <span class="text-[9px] font-bold uppercase tracking-tighter">
                                                        <?php
                                                            $aud = $ann['target_audience'] ?? 'all';
                                                            if ($aud === 'all') echo 'Everyone';
                                                            elseif ($aud === 'residents') echo 'Residents';
                                                            elseif ($aud === 'business') echo 'Business Owners';
                                                            elseif (str_starts_with($aud, 'purok_')) echo 'Purok ' . str_replace('purok_', '', $aud);
                                                            else echo htmlspecialchars(strlen($aud) > 20 ? substr($aud, 0, 17) . '...' : $aud);
                                                        ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <button @click='showEditModal = true; editingPost = <?= $edit_payload_json ?>; isEvent = editingPost.is_event; isScheduled = editingPost.is_scheduled' class="text-indigo-600 hover:bg-indigo-100 p-2 rounded-xl transition shadow-sm group/btn" title="Edit Post">
                                                        <i class="fas fa-pen-nib"></i>
                                                    </button>
                                                    
                                                    <button @click="showDeleteModal = true; postToDelete = <?= $ann['id'] ?>" class="text-rose-500 hover:bg-rose-100 p-2 rounded-xl transition shadow-sm" title="Delete Post">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
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

    <!-- Modals -->
    <!-- Add Modal -->
    <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm shadow-2xl transition-opacity" @click="showAddModal = false"></div>
            
            <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-white/20">
                <form action="../partials/post-handler.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="px-6 pt-6 pb-4 sm:px-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-black text-slate-900 uppercase tracking-widest" x-text="isEvent ? 'Schedule New Event' : 'New Announcement'"></h3>
                            <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 transition p-2 bg-slate-100 rounded-xl"><i class="fas fa-times"></i></button>
                        </div>
                        
                        <div class="space-y-6">
                            <!-- Post Type Toggle -->
                            <div class="flex bg-slate-100 p-1 rounded-2xl border border-slate-200">
                                <button type="button" @click="isEvent = false" :class="!isEvent ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-xl text-xs font-black uppercase transition">
                                    <i class="fas fa-bullhorn mr-2"></i> Announcement
                                </button>
                                <button type="button" @click="isEvent = true" :class="isEvent ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-xl text-xs font-black uppercase transition">
                                    <i class="fas fa-calendar-star mr-2"></i> Event
                                </button>
                                <input type="hidden" name="is_event" :value="isEvent ? 1 : 0">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Title</label>
                                <input type="text" name="title" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" :placeholder="isEvent ? 'Event Title...' : 'Announcement Title...'">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1" x-text="isEvent ? 'Event Description' : 'Content Body'"></label>
                                <textarea name="content" rows="4" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" :placeholder="isEvent ? 'Describe the event details...' : 'Write your announcement details here...'"></textarea>
                            </div>
                            
                            <!-- Event Specific Fields (Conditional) -->
                            <div x-show="isEvent" x-transition class="grid grid-cols-2 gap-4 bg-indigo-50/50 p-5 rounded-3xl border border-indigo-100/50">
                                <div class="col-span-2">
                                    <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Location</label>
                                    <input type="text" name="event_location" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. Barangay Hall">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Date</label>
                                    <input type="date" name="event_date" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Time</label>
                                    <input type="time" name="event_time" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Event Category</label>
                                    <select name="event_type" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm font-bold text-slate-700">
                                        <option value="Upcoming Event">Upcoming Event</option>
                                        <option value="Regular Activity">Regular Activity</option>
                                        <option value="Special Meeting">Special Meeting</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Feature Image (Optional)</label>
                                <div class="relative group">
                                    <input type="file" name="image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-2xl file:border-0 file:text-xs file:font-black file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Target Audience</label>
                                    <div class="relative">
                                        <select name="target_audience" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                            <option value="all">Everyone (Public)</option>
                                            <option value="residents">Registered Residents</option>
                                            <option value="business">Business Owners Only</option>
                                            <optgroup label="Specific Addresses">
                                                <?php foreach ($resident_addresses as $addr): ?>
                                                    <option value="<?= htmlspecialchars($addr) ?>"><?= htmlspecialchars($addr) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Post Expiry (Optional)</label>
                                    <input type="date" name="expiry_date" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                </div>
                            </div>

                            <div class="p-6 rounded-3xl bg-indigo-50/30 border border-indigo-100/50 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">Scheduled Publishing</label>
                                        <p class="text-[10px] text-slate-400 font-medium italic">Post will go live at the selected time</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_scheduled" x-model="isScheduled" class="sr-only peer">
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>
                                
                                <div x-show="isScheduled" x-transition class="grid grid-cols-2 gap-4 pt-2">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Publish Date</label>
                                        <input type="date" name="publish_date" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Publish Time</label>
                                        <input type="time" name="publish_time" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-600">Urgent Alert</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_urgent" value="1" class="sr-only peer">
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                    </label>
                                </div>
                                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col">
                                    <span class="text-[8px] font-black text-slate-400 uppercase mb-1">Visibility</span>
                                    <select name="status" class="bg-transparent text-xs font-bold text-slate-700 outline-none">
                                        <option value="active">Active (Published)</option>
                                        <option value="draft">Draft (Hidden)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="px-8 py-6 bg-slate-50/80 border-t border-slate-100 flex justify-end gap-3 rounded-b-3xl">
                        <button type="button" @click="showAddModal = false" class="px-6 py-3 rounded-2xl text-xs font-black uppercase text-slate-500 hover:bg-white transition">Cancel</button>
                        <button type="submit" name="add_post" class="px-6 py-3 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-600/20 transition transform active:scale-95" x-text="isEvent ? 'Publish Event' : 'Post Announcement'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <template x-if="editingPost">
        <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm shadow-2xl transition-opacity" @click="showEditModal = false"></div>
                
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-white/20">
                    <form action="../partials/post-handler.php" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="post_id" :value="editingPost.id">
                        <input type="hidden" name="is_event" :value="isEvent ? 1 : 0">

                        <div class="px-6 pt-6 pb-4 sm:px-8">
                            <div class="flex items-center justify-between mb-6 text-indigo-700">
                                <h3 class="text-lg font-black uppercase tracking-widest" x-text="isEvent ? 'Edit Event' : 'Edit Announcement'"></h3>
                                <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 transition p-2 bg-slate-100 rounded-xl"><i class="fas fa-times"></i></button>
                            </div>
                            
                            <div class="space-y-6">
                                <!-- Post Type Toggle (Read-only or Switchable?) -->
                                <div class="flex bg-slate-100 p-1 rounded-2xl border border-slate-200">
                                    <button type="button" @click="isEvent = false" :class="!isEvent ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-xl text-xs font-black uppercase transition">
                                        Announcement
                                    </button>
                                    <button type="button" @click="isEvent = true" :class="isEvent ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2.5 rounded-xl text-xs font-black uppercase transition">
                                        Event
                                    </button>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Title</label>
                                    <input type="text" name="title" x-model="editingPost.title" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition shadow-sm">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1" x-text="isEvent ? 'Event Description' : 'Content Body'"></label>
                                    <textarea name="content" rows="4" x-model="editingPost.content" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition shadow-sm"></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Target Audience</label>
                                        <select name="target_audience" x-model="editingPost.target_audience" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                            <option value="all">Everyone (Public)</option>
                                            <option value="residents">Registered Residents</option>
                                            <option value="business">Business Owners Only</option>
                                            <optgroup label="Specific Addresses">
                                                <?php foreach ($resident_addresses as $addr): ?>
                                                    <option value="<?= htmlspecialchars($addr) ?>"><?= htmlspecialchars($addr) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Post Expiry (Optional)</label>
                                        <input type="date" name="expiry_date" x-model="editingPost.expiry_date_only" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                    </div>
                                </div>

                                <div class="p-6 rounded-3xl bg-indigo-50/30 border border-indigo-100/50 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">Scheduled Publishing</label>
                                            <p class="text-[10px] text-slate-400 font-medium italic">Post will go live at the selected time</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="is_scheduled" x-model="isScheduled" class="sr-only peer">
                                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                        </label>
                                    </div>
                                    
                                    <div x-show="isScheduled" x-transition class="grid grid-cols-2 gap-4 pt-2">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Publish Date</label>
                                            <input type="date" name="publish_date" x-model="editingPost.publish_date_only" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Publish Time</label>
                                            <input type="time" name="publish_time" x-model="editingPost.publish_time_only" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                        </div>
                                    </div>
                                </div>

                                <!-- Event Specific Fields (Conditional) -->
                                <div x-show="isEvent" x-transition class="grid grid-cols-2 gap-4 bg-indigo-50/50 p-5 rounded-3xl border border-indigo-100/50">
                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Location</label>
                                        <input type="text" name="event_location" x-model="editingPost.event_location" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Date</label>
                                        <input type="date" name="event_date" x-model="editingPost.event_date" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Time</label>
                                        <input type="time" name="event_time" x-model="editingPost.event_time" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 ml-1">Event Category</label>
                                        <select name="event_type" x-model="editingPost.event_type" class="w-full bg-white border border-indigo-100 rounded-xl px-4 py-3 text-sm font-bold text-slate-700">
                                            <option value="Upcoming Event">Upcoming Event</option>
                                            <option value="Regular Activity">Regular Activity</option>
                                            <option value="Special Meeting">Special Meeting</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-600">Urgent Alert</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="is_urgent" value="1" :checked="editingPost.priority === 'urgent'" class="sr-only peer">
                                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 transition-all"></div>
                                        </label>
                                    </div>
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col">
                                        <span class="text-[8px] font-black text-slate-400 uppercase mb-1">Status</span>
                                        <select name="status" x-model="editingPost.status" class="bg-transparent text-xs font-bold text-slate-700 outline-none">
                                            <option value="active">Active (Published)</option>
                                            <option value="draft">Draft (Hidden)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div x-show="!isEvent">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">New Image (Optional)</label>
                                    <input type="file" name="image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-2xl file:border-0 file:text-xs file:font-black file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition duration-300">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-2 ml-2 italic text-center">leave empty to keep current image</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-8 py-6 bg-slate-50/80 border-t border-slate-100 flex justify-end gap-3 rounded-b-3xl mt-4">
                            <button type="button" @click="showEditModal = false" class="px-6 py-3 rounded-2xl text-xs font-black uppercase text-slate-500 hover:bg-white transition">Cancel</button>
                            <button type="submit" name="update_post" class="px-6 py-3 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-800 shadow-lg shadow-indigo-600/20 transition transform active:scale-95">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>

    <!-- Delete Modal -->
    <div x-show="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 text-center">
            <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showDeleteModal = false"></div>
            
            <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="inline-block bg-white rounded-3xl p-8 text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full relative z-10 border border-slate-100">
                <div class="flex items-center gap-4 mb-6">
                    <div class="h-14 w-14 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 text-xl shadow-inner"><i class="fas fa-trash-alt"></i></div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 uppercase tracking-widest">Delete Post?</h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-tighter">This action cannot be undone.</p>
                    </div>
                </div>
                
                <p class="text-sm text-slate-600 leading-relaxed mb-8">Are you sure you want to permanently remove this post? It will be removed from all resident dashboards instantly.</p>
                
                <div class="flex gap-3">
                    <button @click="showDeleteModal = false" class="flex-1 px-6 py-4 rounded-2xl text-xs font-black uppercase text-slate-500 bg-slate-100 hover:bg-slate-200 transition">No, Cancel</button>
                    <form action="../partials/post-handler.php" method="POST" class="flex-1">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="post_id" :value="postToDelete">
                        <button type="submit" name="delete_post" class="w-full px-6 py-4 rounded-2xl text-xs font-black uppercase text-white bg-rose-500 hover:bg-rose-600 shadow-xl shadow-rose-500/20 transition transform active:scale-95">Yes, Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('announcement-success-alert');
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
