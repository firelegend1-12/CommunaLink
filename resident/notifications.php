<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'Notifications';
require_once 'partials/header.php';

$resident_id = $_SESSION['resident_id'] ?? null;
require_once '../config/database.php';

// Mark all as read when visiting this page
if ($resident_id) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE resident_id = ? AND is_read = 0');
    $stmt->execute([$resident_id]);
}

// Fetch all notifications
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE resident_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$resident_id]);
$notifications = $stmt->fetchAll();
?>

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow mt-8 overflow-hidden">
    <div class="p-6 border-b bg-gray-50 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-blue-700">Notifications</h1>
    </div>
    
    <div class="divide-y divide-gray-100">
        <?php if (empty($notifications)): ?>
            <div class="p-10 text-center text-gray-500">
                <i class="fas fa-bell-slash text-4xl mb-4 block opacity-20"></i>
                <p>No notifications yet.</p>
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
                            <span class="text-xs text-gray-400 mt-2 block"><?= date('F j, Y, g:i a', strtotime($notif['created_at'])) ?></span>
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
</div>

<?php require_once 'partials/footer.php'; ?>
