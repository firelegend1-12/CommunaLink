<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'Alerts & Announcements';
require_once 'partials/header.php';

$resident_id = $_SESSION['resident_id'] ?? null;
require_once '../config/database.php';

// Mark all as read when visiting this page
if ($resident_id) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE resident_id = ? AND is_read = 0');
    $stmt->execute([$resident_id]);
}

// Fetch all notifications (Alerts)
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE resident_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$resident_id]);
$notifications = $stmt->fetchAll();

// Fetch Announcements
$stmt_a = $pdo->query("SELECT a.*, u.fullname as author_name FROM announcements a JOIN users u ON a.user_id = u.id WHERE a.status = 'active' ORDER BY a.created_at DESC LIMIT 20");
$announcements = $stmt_a->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-4xl mx-auto mt-8">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="flex" aria-label="Tabs">
                <button onclick="switchTab('alerts')" id="btn-alerts" class="w-1/2 py-4 px-1 text-center border-b-2 border-blue-500 font-bold text-blue-600 text-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-bell mr-2"></i> My Alerts
                </button>
                <button onclick="switchTab('announcements')" id="btn-announcements" class="w-1/2 py-4 px-1 text-center border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 text-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-bullhorn mr-2"></i> Announcements
                </button>
            </nav>
        </div>

        <!-- ALERTS TAB -->
        <div id="content-alerts" class="divide-y divide-gray-100">
            <?php if (empty($notifications)): ?>
                <div class="p-16 text-center text-gray-400">
                    <i class="fas fa-bell-slash text-5xl mb-4 block opacity-30"></i>
                    <p class="text-lg">No alerts yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?= htmlspecialchars($notif['link'] ?? '#') ?>" class="block p-5 hover:bg-gray-50 transition-colors <?= !$notif['is_read'] ? 'bg-blue-50/50' : '' ?>">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <?php if (!$notif['is_read']): ?>
                                    <span class="block h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                <?php else: ?>
                                    <span class="block h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-gray-900 leading-snug"><?= htmlspecialchars($notif['message']) ?></p>
                                <span class="text-xs text-gray-400 mt-2 block"><?= date('F j, Y, h:i A', strtotime($notif['created_at'])) ?></span>
                            </div>
                            <?php if ($notif['link']): ?>
                                <div class="ml-4 flex-shrink-0 text-gray-300">
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ANNOUNCEMENTS TAB -->
        <div id="content-announcements" class="divide-y divide-gray-100 hidden pb-4">
            <?php if (empty($announcements)): ?>
                <div class="p-16 text-center text-gray-400">
                    <i class="fas fa-bullhorn text-5xl mb-4 block opacity-30"></i>
                    <p class="text-lg">No announcements at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1 text-blue-500">
                                <i class="fas fa-bullhorn text-xl"></i>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-bold text-gray-900 mb-1">
                                    <?= htmlspecialchars($ann['title']) ?>
                                    <?php if (($ann['priority'] ?? '') === 'urgent'): ?>
                                        <span class="ml-2 text-xs font-bold bg-red-100 text-red-600 px-2 py-1 rounded-full uppercase">Urgent</span>
                                    <?php endif; ?>
                                </h3>
                                <span class="text-sm text-gray-500 mb-3 block">
                                    <i class="far fa-clock mr-1"></i><?= date('F j, Y, h:i A', strtotime($ann['created_at'])) ?>
                                    &bull; by <?= htmlspecialchars($ann['author_name']) ?>
                                </span>
                                <?php if (!empty($ann['image_path'])): ?>
                                    <img src="../admin/<?= htmlspecialchars($ann['image_path']) ?>" class="w-full max-h-64 object-cover rounded-lg mb-4" alt="Announcement Image">
                                <?php endif; ?>
                                <div class="text-gray-700 leading-relaxed text-sm whitespace-pre-line"><?= htmlspecialchars($ann['content']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    const alertsBtn = document.getElementById('btn-alerts');
    const annBtn = document.getElementById('btn-announcements');
    const alertsContent = document.getElementById('content-alerts');
    const annContent = document.getElementById('content-announcements');

    if (tab === 'alerts') {
        alertsBtn.className = "w-1/2 py-4 px-1 text-center border-b-2 border-blue-500 font-bold text-blue-600 text-lg flex items-center justify-center transition-colors";
        annBtn.className = "w-1/2 py-4 px-1 text-center border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 text-lg flex items-center justify-center transition-colors";
        alertsContent.classList.remove('hidden');
        annContent.classList.add('hidden');
    } else {
        annBtn.className = "w-1/2 py-4 px-1 text-center border-b-2 border-blue-500 font-bold text-blue-600 text-lg flex items-center justify-center transition-colors";
        alertsBtn.className = "w-1/2 py-4 px-1 text-center border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 text-lg flex items-center justify-center transition-colors";
        annContent.classList.remove('hidden');
        alertsContent.classList.add('hidden');
    }
}
</script>

<?php require_once 'partials/footer.php'; ?>
