<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || ($_SESSION['role'] ?? '') !== $role) {
            redirect_to('../index.php');
        }
    }
}

require_role('resident');

require_once '../config/database.php';

$page_title = 'Notifications';
$notification_csrf_token = csrf_token();
$user_id = (int)($_SESSION['user_id'] ?? 0);
$resident_id = (int)($_SESSION['resident_id'] ?? 0);
if ($resident_id <= 0 && $user_id > 0) {
    $resident_id = (int)(get_resident_id($pdo, $user_id) ?? 0);
    if ($resident_id > 0) {
        $_SESSION['resident_id'] = $resident_id;
    }
}

$notifications = $user_id > 0 ? get_resident_combined_notifications($pdo, $user_id, $resident_id, 50) : [];
$unread_count = 0;
foreach ($notifications as $notification_row) {
    if ((int)($notification_row['is_read'] ?? 0) === 0) {
        $unread_count++;
    }
}

require_once 'partials/header.php';
?>
<style>
    .notifications-shell {
        max-width: 960px;
        margin: 0 auto;
    }
    .notifications-hero {
        background: linear-gradient(135deg, #1d4ed8, #4338ca);
        color: #fff;
        border-radius: 24px;
        padding: 28px;
        box-shadow: 0 20px 45px rgba(37, 99, 235, 0.18);
        margin-bottom: 24px;
    }
    .notifications-hero h1 {
        margin: 0 0 8px;
        font-size: 2rem;
        font-weight: 800;
    }
    .notifications-hero p {
        margin: 0;
        opacity: 0.88;
        max-width: 42rem;
        line-height: 1.5;
    }
    .notifications-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .notifications-summary {
        color: #475569;
        font-size: 0.95rem;
        font-weight: 600;
    }
    .notifications-summary strong {
        color: #1e293b;
    }
    .notifications-btns {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .notifications-btn {
        border: 0;
        border-radius: 9999px;
        padding: 10px 16px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .notifications-btn:hover {
        transform: translateY(-1px);
    }
    .notifications-btn-primary {
        background: #1d4ed8;
        color: #fff;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.22);
    }
    .notifications-btn-secondary {
        background: #fff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
    }
    .notifications-btn[disabled] {
        opacity: 0.55;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .notifications-list {
        display: grid;
        gap: 14px;
    }
    .notification-card {
        display: block;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 18px 20px;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .notification-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 30px rgba(15, 23, 42, 0.1);
        border-color: #bfdbfe;
    }
    .notification-card.is-unread {
        border-color: #93c5fd;
        background: linear-gradient(180deg, #eff6ff, #ffffff);
    }
    .notification-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 10px;
    }
    .notification-card-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.35;
    }
    .notification-card-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .notification-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 4px 10px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }
    .notification-pill.status {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .notification-pill.board {
        background: #ede9fe;
        color: #6d28d9;
    }
    .notification-pill.payment {
        background: #dcfce7;
        color: #15803d;
    }
    .notification-pill.general {
        background: #e2e8f0;
        color: #334155;
    }
    .notification-pill.unread {
        background: #fee2e2;
        color: #b91c1c;
    }
    .notification-card-message {
        margin: 0;
        color: #475569;
        line-height: 1.55;
    }
    .notification-card-time {
        color: #64748b;
        white-space: nowrap;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .notification-empty {
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 20px;
        padding: 48px 24px;
        text-align: center;
        color: #64748b;
    }
    .notification-empty h2 {
        margin: 12px 0 8px;
        color: #1e293b;
        font-size: 1.25rem;
    }
    .notification-empty i {
        font-size: 2.5rem;
        color: #94a3b8;
    }
    @media (max-width: 767px) {
        .notifications-hero {
            padding: 22px 18px;
            border-radius: 20px;
        }
        .notifications-hero h1 {
            font-size: 1.55rem;
        }
        .notification-card {
            padding: 16px;
        }
        .notification-card-head {
            flex-direction: column;
        }
        .notification-card-time {
            white-space: normal;
        }
    }
</style>

<div class="notifications-shell">
    <section class="notifications-hero">
        <h1>Notification Center</h1>
        <p>Document requests, incident report updates, payment notices, and Community Board posts all land here so you have one clear place to check instead of chasing status changes across different pages.</p>
    </section>

    <div class="notifications-actions">
        <div class="notifications-summary">
            <strong id="notificationTotalCount"><?= count($notifications) ?></strong> recent notifications,
            <strong id="notificationUnreadCount"><?= $unread_count ?></strong> unread.
        </div>
        <div class="notifications-btns">
            <button
                id="markAllNotificationsRead"
                type="button"
                class="notifications-btn notifications-btn-primary"
                <?= $unread_count > 0 ? '' : 'disabled' ?>
            >
                Mark All As Read
            </button>
            <a href="announcements.php" class="notifications-btn notifications-btn-secondary">Open Community Board</a>
        </div>
    </div>

    <div class="notifications-list" id="notificationList">
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <i class="fas fa-bell-slash" aria-hidden="true"></i>
                <h2>No notifications yet</h2>
                <p>New request updates, report decisions, and announcements will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <?php
                    $title = trim((string)($notification['title'] ?? 'Notification'));
                    $message = trim((string)($notification['message'] ?? ''));
                    if ($message === '') {
                        $message = trim((string)($notification['content'] ?? ''));
                    }
                    $type = trim((string)($notification['type'] ?? 'general'));
                    $source = trim((string)($notification['source'] ?? 'notification'));
                    $link = trim((string)($notification['link'] ?? ''));
                    if ($link === '') {
                        $link = 'notifications.php';
                    }
                    $created_at = strtotime((string)($notification['created_at'] ?? ''));
                    $created_label = $created_at ? date('M j, Y g:i A', $created_at) : 'Just now';
                    $is_unread = (int)($notification['is_read'] ?? 0) === 0;
                    $source_id = (int)($notification['source_id'] ?? 0);
                    $can_mark = $is_unread && $source === 'notification' && $source_id > 0;
                    $pill_class = 'general';
                    $pill_label = 'General';
                    if ($source === 'community_board') {
                        $pill_class = 'board';
                        $pill_label = $type === 'event_announcement' ? 'Event' : 'Board';
                    } elseif ($type === 'request_status' || $type === 'incident_status') {
                        $pill_class = 'status';
                        $pill_label = 'Status';
                    } elseif ($type === 'payment_update') {
                        $pill_class = 'payment';
                        $pill_label = 'Payment';
                    }
                ?>
                <a
                    href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
                    class="notification-card<?= $is_unread ? ' is-unread' : '' ?>"
                    <?php if ($can_mark): ?>
                    data-mark-notification-id="<?= $source_id ?>"
                    data-notification-link="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
                    <?php endif; ?>
                >
                    <div class="notification-card-head">
                        <h2 class="notification-card-title"><?= htmlspecialchars($title) ?></h2>
                        <span class="notification-card-time"><?= htmlspecialchars($created_label) ?></span>
                    </div>
                    <div class="notification-card-meta">
                        <span class="notification-pill <?= htmlspecialchars($pill_class) ?>"><?= htmlspecialchars($pill_label) ?></span>
                        <?php if ($is_unread): ?>
                            <span class="notification-pill unread">Unread</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($message !== ''): ?>
                        <p class="notification-card-message"><?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = <?php echo json_encode($notification_csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const markAllButton = document.getElementById('markAllNotificationsRead');
    const notificationList = document.getElementById('notificationList');
    const notificationUnreadCount = document.getElementById('notificationUnreadCount');
    const notificationTotalCount = document.getElementById('notificationTotalCount');

    function postNotificationAction(action, body) {
        return fetch('../api/notifications.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        });
    }

    function escapeHTML(str) {
        return String(str || '').replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    }

    function getNotificationMeta(item) {
        const type = String(item && item.type ? item.type : 'general');
        const source = String(item && item.source ? item.source : 'notification');

        if (source === 'community_board') {
            return {
                pillClass: 'board',
                pillLabel: type === 'event_announcement' ? 'Event' : 'Board'
            };
        }
        if (type === 'request_status' || type === 'incident_status') {
            return {
                pillClass: 'status',
                pillLabel: 'Status'
            };
        }
        if (type === 'payment_update') {
            return {
                pillClass: 'payment',
                pillLabel: 'Payment'
            };
        }
        return {
            pillClass: 'general',
            pillLabel: 'General'
        };
    }

    function renderNotificationList(items) {
        if (!notificationList) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            notificationList.innerHTML = ''
                + '<div class="notification-empty">'
                + '<i class="fas fa-bell-slash" aria-hidden="true"></i>'
                + '<h2>No notifications yet</h2>'
                + '<p>New request updates, report decisions, and announcements will appear here.</p>'
                + '</div>';
            return;
        }

        notificationList.innerHTML = items.map(function(item) {
            const meta = getNotificationMeta(item);
            const title = escapeHTML(item && item.title ? item.title : 'Notification');
            const message = escapeHTML(item && item.message ? item.message : '');
            const link = escapeHTML(item && item.link ? item.link : 'notifications.php');
            const createdLabel = escapeHTML(item && item.created_label ? item.created_label : 'Just now');
            const isUnread = Number(item && item.is_read ? item.is_read : 0) === 0;
            const source = String(item && item.source ? item.source : '');
            const sourceId = parseInt(item && item.source_id ? item.source_id : 0, 10);
            const canMark = isUnread && source === 'notification' && sourceId > 0;
            const attrs = canMark
                ? ' data-mark-notification-id="' + sourceId + '" data-notification-link="' + link + '"'
                : '';

            return '<a href="' + link + '" class="notification-card' + (isUnread ? ' is-unread' : '') + '"' + attrs + '>'
                + '<div class="notification-card-head">'
                + '<h2 class="notification-card-title">' + title + '</h2>'
                + '<span class="notification-card-time">' + createdLabel + '</span>'
                + '</div>'
                + '<div class="notification-card-meta">'
                + '<span class="notification-pill ' + escapeHTML(meta.pillClass) + '">' + escapeHTML(meta.pillLabel) + '</span>'
                + (isUnread ? '<span class="notification-pill unread">Unread</span>' : '')
                + '</div>'
                + (message ? '<p class="notification-card-message">' + message + '</p>' : '')
                + '</a>';
        }).join('');
    }

    function applyResidentNotificationFeed(detail) {
        const items = Array.isArray(detail && detail.notifications) ? detail.notifications : [];
        const unreadCount = Number(detail && detail.unread_count ? detail.unread_count : 0);

        renderNotificationList(items);

        if (notificationTotalCount) {
            notificationTotalCount.textContent = String(items.length);
        }
        if (notificationUnreadCount) {
            notificationUnreadCount.textContent = String(unreadCount);
        }
        if (markAllButton && markAllButton.dataset.busy !== '1') {
            markAllButton.disabled = unreadCount <= 0;
            markAllButton.textContent = 'Mark All As Read';
        }
    }

    document.addEventListener('click', function(event) {
        if (!event.target || typeof event.target.closest !== 'function') {
            return;
        }

        const link = event.target.closest('#notificationList [data-mark-notification-id]');
        if (!link) {
            return;
        }

        const notificationId = parseInt(link.getAttribute('data-mark-notification-id') || '0', 10);
        const targetHref = link.getAttribute('data-notification-link') || link.getAttribute('href') || 'notifications.php';
        if (!notificationId) {
            return;
        }

        event.preventDefault();

        if (link.dataset.marking === '1') {
            return;
        }
        link.dataset.marking = '1';

        const body = new URLSearchParams();
        body.append('notification_id', String(notificationId));
        body.append('csrf_token', csrfToken);

        postNotificationAction('mark_read', body)
            .catch(function() {
                return null;
            })
            .finally(function() {
                window.location.href = targetHref;
            });
    });

    if (markAllButton) {
        markAllButton.addEventListener('click', function() {
            if (markAllButton.disabled) {
                return;
            }

            markAllButton.dataset.busy = '1';
            markAllButton.disabled = true;
            markAllButton.textContent = 'Marking...';

            const body = new URLSearchParams();
            body.append('csrf_token', csrfToken);

            postNotificationAction('mark_all_read', body)
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (!result || !result.success) {
                        throw new Error((result && result.error) || 'Failed to mark notifications as read.');
                    }
                    window.location.reload();
                })
                .catch(function(error) {
                    console.error(error);
                    delete markAllButton.dataset.busy;
                    markAllButton.disabled = false;
                    markAllButton.textContent = 'Mark All As Read';
                    alert('Failed to mark notifications as read. Please try again.');
                });
        });
    }

    window.addEventListener('resident-notifications-updated', function(event) {
        applyResidentNotificationFeed(event.detail || {});
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
