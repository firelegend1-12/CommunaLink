<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';

$page_title = "Manage Events";

try {
    $stmt = $pdo->query("SELECT e.*, u.fullname as author_name FROM events e JOIN users u ON e.created_by = u.id ORDER BY e.event_date DESC, e.event_time DESC");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
    $_SESSION['error_message'] = "Database error fetching events: " . $e->getMessage();
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
</head>
<body class="bg-gray-100 min-h-screen" x-data="eventPage()">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center">
                            <a href="announcements.php" class="text-gray-500 hover:text-gray-700">Announcements</a>
                            <i class="fas fa-chevron-right mx-2 text-gray-400 text-xs"></i>
                            <h1 class="text-2xl font-semibold text-gray-800"><?= $page_title ?></h1>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['event_success_message'])) {
                    echo '<div id="events-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                    echo '<p>' . htmlspecialchars($_SESSION['event_success_message']) . '</p>';
                    echo '</div>';
                    unset($_SESSION['event_success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo display_error($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                }
                ?>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-end items-center">
                        <button @click="openAddModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                            <i class="fas fa-plus mr-2"></i>
                            New Event
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Author</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($events)): ?>
                                        <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No events found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($event['type']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $event['event_date'] ? date('M d, Y', strtotime($event['event_date'])) : 'N/A' ?>
                                                    <?= $event['event_time'] ? date('h:i A', strtotime($event['event_time'])) : '' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($event['location']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($event['author_name']) ?></td>
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
                                                                    <button type="button" @click="openEditModal({id: <?= $event['id'] ?>, title: '<?= htmlspecialchars(addslashes($event['title'])) ?>', type: '<?= htmlspecialchars(addslashes($event['type'])) ?>', event_date: '<?= $event['event_date'] ?>', event_time: '<?= $event['event_time'] ?>', location: '<?= htmlspecialchars(addslashes($event['location'])) ?>', description: '<?= htmlspecialchars(addslashes($event['description'])) ?>'})" class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">Edit</button>
                                                                    <button type="button" @click="deleteModal = true; eventIdToDelete = <?= $event['id'] ?>; open = false" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">Delete</button>
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

    <!-- Add/Edit Modal -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <form :action="modalMode === 'add' ? '../partials/event-handler.php' : '../partials/event-handler.php'" method="POST" class="bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.away="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="modalMode === 'add' ? 'New Event' : 'Edit Event'"></h3>
                <template x-if="modalMode === 'edit'"><input type="hidden" name="event_id" :value="form.id"></template>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" name="title" id="title" x-model="form.title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Event Type</label>
                        <select name="type" id="type" x-model="form.type" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="Upcoming Event">Upcoming Event</option>
                            <option value="Regular Activity">Regular Activity</option>
                        </select>
                    </div>
                    <div>
                        <label for="event_date" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="event_date" id="event_date" x-model="form.event_date" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="event_time" class="block text-sm font-medium text-gray-700">Time</label>
                        <input type="time" name="event_time" id="event_time" x-model="form.event_time" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input type="text" name="location" id="location" x-model="form.location" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="4" x-model="form.description" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-2">
                <button type="button" @click="closeModal" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" :name="modalMode === 'add' ? 'add_event' : 'update_event'" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md" x-text="modalMode === 'add' ? 'Post Event' : 'Update Event'"></button>
            </div>
        </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="deleteModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md" @click.away="deleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Confirm Deletion</h3>
                <p class="mt-2 text-sm text-gray-600">Are you sure you want to delete this event? This action cannot be undone.</p>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-2">
                <button type="button" @click="deleteModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <form action="../partials/event-handler.php" method="POST">
                    <input type="hidden" name="event_id" x-bind:value="eventIdToDelete">
                    <button type="submit" name="delete_event" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('events-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });

    function eventPage() {
        return {
            modalOpen: false,
            modalMode: 'add', // 'add' or 'edit'
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
            },
            closeModal() {
                this.modalOpen = false;
            },
            viewEvent(id) {
                alert('View event with ID: ' + id);
            }
        }
    }
    </script>
</body>
</html> 