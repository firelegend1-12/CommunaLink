<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/business_announcement_functions.php';

$page_title = "Manage Announcements";

// Get filters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

try {
    // Build query with filters
    $sql = "SELECT a.*, u.fullname as author_name FROM announcements a JOIN users u ON a.user_id = u.id WHERE 1=1";
    $params = [];
    
    if ($category_filter) {
        $sql .= " AND a.category = ?";
        $params[] = $category_filter;
    }
    

    
    if ($status_filter) {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll();
    
    // Get statistics
    $stats = getAnnouncementStats();
    
} catch (PDOException $e) {
    $announcements = [];
    $stats = [];
    $_SESSION['error_message'] = "Database error fetching announcements: " . $e->getMessage();
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
<body class="bg-gray-100 min-h-screen" x-data="{ showModal: false, deleteModal: false, announcementIdToDelete: null, editModal: false, editingAnnouncement: null }">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?= $page_title ?></h1>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['announcement_success_message'])) {
                    echo '<div id="announcement-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                    echo '<p>' . htmlspecialchars($_SESSION['announcement_success_message']) . '</p>';
                    echo '</div>';
                    unset($_SESSION['announcement_success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo display_error($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                }
                ?>
                
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-bullhorn text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_announcements'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Active</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_announcements'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Scheduled</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['scheduled_announcements'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-robot text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Auto-Generated</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['auto_generated'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                <i class="fas fa-store text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Business</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['business_announcements'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Urgent</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['urgent_announcements'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <label for="category-filter" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="category-filter" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                                <option value="">All Categories</option>
                                <option value="general" <?php echo $category_filter === 'general' ? 'selected' : ''; ?>>General</option>
                                <option value="business" <?php echo $category_filter === 'business' ? 'selected' : ''; ?>>Business</option>
                                <option value="emergency" <?php echo $category_filter === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                <option value="event" <?php echo $category_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                            </select>
                        </div>
                        

                        
                        <div>
                            <label for="status-filter" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status-filter" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button onclick="applyFilters()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                                Apply Filters
                            </button>
                        </div>
                        
                        <div class="ml-auto">
                            <button @click="showModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-plus mr-2"></i>
                                New Announcement
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Announcements</h3>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>

                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Author</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($announcements)): ?>
                                        <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No announcements found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $ann): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($ann['title']) ?>
                                                        <?php if ($ann['is_auto_generated']): ?>
                                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                                <i class="fas fa-robot mr-1"></i>Auto
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?= ucfirst($ann['category'] ?? 'general') ?>
                                                    </span>
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    $status_colors = [
                                                        'draft' => 'bg-gray-100 text-gray-800',
                                                        'scheduled' => 'bg-yellow-100 text-yellow-800',
                                                        'active' => 'bg-green-100 text-green-800',
                                                        'expired' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $status = $ann['status'] ?? 'active';
                                                    $color_class = $status_colors[$status];
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $color_class ?>">
                                                        <?= ucfirst($status) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($ann['author_name']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?></td>
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
                                                                    <button @click="editModal = true; editingAnnouncement = {id: <?= $ann['id'] ?>, title: '<?= htmlspecialchars(addslashes($ann['title'])) ?>', content: '<?= htmlspecialchars(addslashes($ann['content'])) ?>'}; open = false;" class="block w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-gray-100">Edit</button>
                                                                    <button @click="deleteModal = true; announcementIdToDelete = <?= $ann['id'] ?>; open = false;" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
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
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <form action="../partials/announcement-handler.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.away="showModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">New Announcement</h3>
                <div class="space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" name="title" id="title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                        <textarea name="content" id="content" rows="6" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700">Image (Optional)</label>
                        <input type="file" name="image" id="image" accept="image/png, image/jpeg, image/gif" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-2">
                <button type="button" @click="showModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" name="add_announcement" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Post Announcement</button>
            </div>
        </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="deleteModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md" @click.away="deleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Confirm Deletion</h3>
                <p class="mt-2 text-sm text-gray-600">Are you sure you want to delete this announcement? This action cannot be undone.</p>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-2">
                <button type="button" @click="deleteModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <form action="../partials/announcement-handler.php" method="POST">
                    <input type="hidden" name="announcement_id" x-bind:value="announcementIdToDelete">
                    <button type="submit" name="delete_announcement" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="editModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <form action="../partials/announcement-handler.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.away="editModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Announcement</h3>
                <input type="hidden" name="announcement_id" x-bind:value="editingAnnouncement?.id">
                <div class="space-y-4">
                    <div>
                        <label for="edit_title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" name="title" id="edit_title" x-bind:value="editingAnnouncement?.title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="edit_content" class="block text-sm font-medium text-gray-700">Content</label>
                        <textarea name="content" id="edit_content" rows="6" x-bind:value="editingAnnouncement?.content" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="edit_image" class="block text-sm font-medium text-gray-700">New Image (Optional)</label>
                        <input type="file" name="image" id="edit_image" accept="image/png, image/jpeg, image/gif" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="mt-1 text-xs text-gray-500">Leave empty to keep the current image</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-2">
                <button type="button" @click="editModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" name="update_announcement" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Update Announcement</button>
            </div>
        </form>
        </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('announcement-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });
    
    function applyFilters() {
        const category = document.getElementById('category-filter').value;
        const status = document.getElementById('status-filter').value;
        
        const params = new URLSearchParams();
        if (category) params.append('category', category);
        if (status) params.append('status', status);
        
        window.location.href = 'announcements.php?' + params.toString();
    }
    </script>
</body>
</html> 