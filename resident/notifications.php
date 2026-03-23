<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_role('resident');
$page_title = 'Alerts & Announcements';
require_once 'partials/header.php';

$resident_id = $_SESSION['resident_id'] ?? null;
$mark_read_csrf_token = csrf_token();
require_once '../config/database.php';

function sanitize_notification_link($raw_link) {
    $link = trim((string) $raw_link);
    if ($link === '') {
        return '';
    }

    $lower_link = strtolower($link);
    if (strpos($lower_link, 'javascript:') === 0 || strpos($lower_link, 'data:') === 0) {
        return '';
    }

    if (filter_var($link, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $link : '';
    }

    if (strpos($link, '/') === 0) {
        return $link;
    }

    return preg_match('/^[A-Za-z0-9_\-\/\.]+(?:\?[A-Za-z0-9_\-\.=&%]*)?$/', $link) ? $link : '';
}

// Fetch all notifications (Alerts)
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} else {
    $notifications = [];
}

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
                    <?php
                        $notif_link = sanitize_notification_link($notif['link'] ?? '');
                        $has_link = $notif_link !== '';
                    ?>
                    <<?= $has_link ? 'a' : 'button' ?>
                        <?= $has_link ? 'href="' . htmlspecialchars($notif_link) . '"' : 'type="button"' ?>
                        data-notification-id="<?= (int) $notif['id'] ?>"
                        class="js-mark-read block w-full text-left p-5 hover:bg-gray-50 transition-colors <?= !$notif['is_read'] ? 'bg-blue-50/50' : '' ?>"
                    >
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <?php if (!$notif['is_read']): ?>
                                    <span class="block h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                <?php else: ?>
                                    <span class="block h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4 flex-1">
                                <?php if (!empty($notif['title'])): ?>
                                    <h4 class="text-sm font-bold text-gray-900 mb-1"><?= htmlspecialchars($notif['title']) ?></h4>
                                <?php endif; ?>
                                <p class="text-gray-700 leading-snug"><?= htmlspecialchars($notif['message']) ?></p>
                                <span class="text-xs text-gray-400 mt-2 block"><?= date('F j, Y, h:i A', strtotime($notif['created_at'])) ?></span>
                            </div>
                            <?php if ($has_link): ?>
                                <div class="ml-4 flex-shrink-0 text-gray-300">
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </<?= $has_link ? 'a' : 'button' ?>>
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

function markNotificationRead(notificationId) {
    if (!notificationId) {
        return Promise.resolve();
    }

    const formData = new URLSearchParams();
    formData.append('notification_id', String(notificationId));
    formData.append('csrf_token', <?php echo json_encode($mark_read_csrf_token); ?>);

    return fetch('../api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: formData.toString(),
        credentials: 'same-origin',
        keepalive: true
    }).catch(function () {
        // Do not interrupt navigation/interaction when mark-read fails.
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var clickableRows = document.querySelectorAll('.js-mark-read');
    clickableRows.forEach(function (row) {
        row.addEventListener('click', function (event) {
            var targetHref = this.tagName.toLowerCase() === 'a' ? this.getAttribute('href') : '';
            if (targetHref) {
                event.preventDefault();
            }

            var notificationId = this.getAttribute('data-notification-id');
            var request = markNotificationRead(notificationId);

            // Optimistic UI update
            this.classList.remove('bg-blue-50/50');
            var dot = this.querySelector('span');
            if (dot) {
                dot.classList.remove('bg-blue-600');
                dot.classList.add('bg-gray-300');
            }

            if (targetHref) {
                Promise.resolve(request).finally(function () {
                    window.location.href = targetHref;
                });
            }
        });
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
