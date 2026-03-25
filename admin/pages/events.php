<?php
/**
 * Events Management - Modernized
 */
require_once '../partials/admin_auth.php';
require_once '../../includes/functions.php';

$page_title = "Manage Events";

try {
    // Basic Query
    $stmt = $pdo->query("SELECT e.*, u.fullname as author_name FROM events e JOIN users u ON e.created_by = u.id ORDER BY e.event_date DESC, e.event_time DESC");
    $events = $stmt->fetchAll();
    
    // Calculate Stats
    $total_events = count($events);
    $upcoming_events = 0;
    $past_events = 0;
    $today = date('Y-m-d');
    
    foreach ($events as $event) {
        if ($event['event_date'] >= $today) {
            $upcoming_events++;
        } else {
            $past_events++;
        }
    }
    
} catch (PDOException $e) {
    $events = [];
    $total_events = 0; $upcoming_events = 0; $past_events = 0;
    $_SESSION['error_message'] = "Database error fetching events: " . $e->getMessage();
}
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
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        modalOpen: false, 
        modalMode: 'add',
        showDeleteModal: false,
        eventIdToDelete: null,
        form: { id: '', title: '', type: 'Upcoming Event', event_date: '', event_time: '', location: '', description: '' },
        openAddModal() {
            this.modalMode = 'add';
            this.form = { id: '', title: '', type: 'Upcoming Event', event_date: '', event_time: '', location: '', description: '' };
            this.modalOpen = true;
        },
        openEditModal(event) {
            this.modalMode = 'edit';
            this.form = { ...event };
            this.modalOpen = true;
        }
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
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Community Events</h1>
                            <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-indigo-200">Live Calendar</span>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <button @click="openAddModal" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center transition shadow-md shadow-indigo-500/20">
                                <i class="fas fa-calendar-plus mr-2"></i> NEW EVENT
                            </button>
                            
                            <div class="h-8 w-px bg-slate-200 mx-2"></div>
                            
                            <?php include '../partials/user-dropdown.php'; ?>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-8">
                <?php if (isset($_SESSION['event_success_message'])): ?>
                    <div id="events-success-alert" class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-r-xl shadow-sm animate-fade-in" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                            <p class="font-bold text-sm"><?php echo htmlspecialchars($_SESSION['event_success_message']); ?></p>
                        </div>
                    </div>
                <?php unset($_SESSION['event_success_message']); endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div id="events-error-alert" class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-4 mb-6 rounded-r-xl shadow-sm" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-3 text-rose-500"></i>
                            <p class="font-bold text-sm"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                        </div>
                    </div>
                <?php unset($_SESSION['error_message']); endif; ?>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-indigo-200 transition">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Events</p>
                            <h3 class="text-3xl font-black text-slate-900"><?php echo $total_events; ?></h3>
                        </div>
                        <div class="h-12 w-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shadow-inner group-hover:scale-110 transition transition-duration-500">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-emerald-200 transition">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Upcoming</p>
                            <h3 class="text-3xl font-black text-emerald-600"><?php echo $upcoming_events; ?></h3>
                        </div>
                        <div class="h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shadow-inner group-hover:scale-110 transition">
                            <i class="fas fa-forward"></i>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between group hover:border-slate-300 transition">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Past Events</p>
                            <h3 class="text-3xl font-black text-slate-400"><?php echo $past_events; ?></h3>
                        </div>
                        <div class="h-12 w-12 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center text-xl shadow-inner group-hover:scale-110 transition">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                </div>

                <!-- Events Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Event Records</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50/30">
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Event Title</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Type</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Schedule</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Location</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Organizer</th>
                                    <th class="px-6 py-4 text-right text-[10px] font-black text-slate-500 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (empty($events)): ?>
                                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">No events scheduled yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($events as $event): 
                                        $is_past = $event['event_date'] < $today;
                                    ?>
                                        <tr class="hover:bg-slate-50/80 transition group <?php echo $is_past ? 'opacity-75' : ''; ?>">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($event['title']) ?></div>
                                                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-0.5">
                                                    <?php if ($is_past): ?><span class="text-rose-400 mr-2"><i class="fas fa-clock"></i> PASSED</span><?php endif; ?>
                                                    ID: EVT-<?= str_pad($event['id'], 4, '0', STR_PAD_LEFT) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg border <?= $event['type'] === 'Upcoming Event' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-amber-50 text-amber-700 border-amber-100' ?>">
                                                    <?= htmlspecialchars($event['type']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center text-sm font-bold text-slate-700">
                                                    <i class="far fa-calendar-alt mr-2 text-indigo-400"></i>
                                                    <?= date('M d, Y', strtotime($event['event_date'])) ?>
                                                </div>
                                                <div class="text-[10px] font-bold text-slate-400 uppercase mt-0.5 ml-6 italic">
                                                    <?= date('h:i A', strtotime($event['event_time'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-xs font-bold text-slate-600 flex items-center">
                                                    <i class="fas fa-map-marker-alt mr-2 text-rose-400"></i>
                                                    <?= htmlspecialchars($event['location']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="text-sm font-bold text-slate-700"><?= htmlspecialchars($event['author_name']) ?></div>
                                                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter mt-0.5">Admin Profile</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end space-x-2 sm:opacity-0 sm:group-hover:opacity-100 transition duration-300">
                                                    <button @click="openEditModal(<?= htmlspecialchars(json_encode([
                                                        'id' => $event['id'],
                                                        'title' => $event['title'],
                                                        'type' => $event['type'],
                                                        'event_date' => $event['event_date'],
                                                        'event_time' => $event['event_time'],
                                                        'location' => $event['location'],
                                                        'description' => $event['description']
                                                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)" class="text-indigo-600 hover:bg-indigo-50 p-2 rounded-xl transition shadow-sm border border-transparent hover:border-indigo-100">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <button @click="showDeleteModal = true; eventIdToDelete = <?= $event['id'] ?>" class="text-rose-500 hover:bg-rose-50 p-2 rounded-xl transition shadow-sm border border-transparent hover:border-rose-100">
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

    <!-- Modals -->
    <!-- Add/Edit Modal -->
    <div x-show="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="modalOpen" x-transition:enter="duration-300 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="modalOpen = false"></div>
            
            <div x-show="modalOpen" x-transition:enter="duration-300 ease-out" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form action="../partials/event-handler.php" method="POST">
                    <div class="px-6 pt-8 pb-4 sm:px-10">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-lg font-black text-slate-900 uppercase tracking-widest" x-text="modalMode === 'add' ? 'Schedule New Event' : 'Modify Event Detail'"></h3>
                            <button type="button" @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 p-2 bg-slate-100 rounded-xl transition"><i class="fas fa-times"></i></button>
                        </div>
                        
                        <input type="hidden" name="event_id" x-model="form.id">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Event Title</label>
                                <input type="text" name="title" x-model="form.title" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" placeholder="e.g., Barangay Assembly Meeting">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Category</label>
                                <select name="type" x-model="form.type" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 transition shadow-sm appearance-none">
                                    <option value="Upcoming Event">Upcoming Event</option>
                                    <option value="Regular Activity">Regular Activity</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Location</label>
                                <input type="text" name="location" x-model="form.location" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" placeholder="e.g., Barangay Hall">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Event Date</label>
                                <input type="date" name="event_date" x-model="form.event_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Start Time</label>
                                <input type="time" name="event_time" x-model="form.event_time" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Event Overview</label>
                                <textarea name="description" rows="5" x-model="form.description" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" placeholder="Tell the community what this event is about..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="px-10 py-8 bg-slate-50/80 border-t border-slate-100 flex justify-end gap-3 rounded-b-3xl">
                        <button type="button" @click="modalOpen = false" class="px-8 py-3.5 rounded-2xl text-xs font-black uppercase text-slate-500 hover:bg-white transition">Keep Draft</button>
                        <button type="submit" :name="modalMode === 'add' ? 'add_event' : 'update_event'" class="px-8 py-3.5 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-600/20 transition transform active:scale-95" x-text="modalMode === 'add' ? 'Publish Event' : 'Update Record'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div x-show="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 text-center">
            <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showDeleteModal = false"></div>
            
            <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="inline-block bg-white rounded-3xl p-10 text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full relative z-10">
                <div class="flex items-center gap-5 mb-8">
                    <div class="h-16 w-16 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 text-2xl shadow-inner"><i class="fas fa-calendar-times"></i></div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900 uppercase tracking-widest leading-tight">Cancel Event?</h3>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1 italic">This action is permanent</p>
                    </div>
                </div>
                
                <p class="text-sm text-slate-600 leading-relaxed mb-10">Are you sure you want to remove this event record? This will permanently delete the schedule from the public community board.</p>
                
                <div class="flex gap-4">
                    <button @click="showDeleteModal = false" class="flex-1 px-6 py-4 rounded-2xl text-xs font-black uppercase text-slate-500 bg-slate-50 hover:bg-slate-100 transition">Back to List</button>
                    <form action="../partials/event-handler.php" method="POST" class="flex-1">
                        <input type="hidden" name="event_id" :value="eventIdToDelete">
                        <button type="submit" name="delete_event" class="w-full px-6 py-4 rounded-2xl text-xs font-black uppercase text-white bg-rose-500 hover:bg-rose-600 shadow-xl shadow-rose-500/20 transition transform active:scale-95">Confirm Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        ['events-success-alert', 'events-error-alert'].forEach(function(id) {
            const alert = document.getElementById(id);
            if (alert) {
                setTimeout(() => {
                    alert.classList.add('opacity-0', 'transition-all', 'duration-1000', '-translate-y-2');
                    setTimeout(() => alert.remove(), 1000);
                }, 4000);
            }
        });
    });
    </script>
</body>
</html>