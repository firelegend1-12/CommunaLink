<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_role('resident');

$page_title = $page_title ?? "Resident Portal"; // Allow pages to set their own title
$user_fullname = $_SESSION['fullname'] ?? 'Resident';
$user_id = $_SESSION['user_id'] ?? null;
$mark_read_csrf_token = csrf_token();

function sanitize_notification_link_server($raw_link) {
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

// Fetch unread notifications count and latest notifications
$notifications = [];
$unread_count = 0;
if ($user_id) {
    require_once '../config/database.php';
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    $unread_count = 0;
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) $unread_count++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Barangay Pakiad</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome 6.4.2 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/resident.css">
    <!-- PWA Setup -->
    <link rel="manifest" href="/resident/manifest.json">
    <meta name="theme-color" content="#5c67e2">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/resident/sw.js', { scope: '/resident/' })
                    .then(reg => console.log('Service Worker registered successfully!', reg))
                    .catch(err => console.log('Service Worker registration failed: ', err));
            });
        }
    </script>
    <style>
        /* Inlined styles for resident pages */
        :root {
            --primary-bg: #f0f2f5;
            --sidebar-bg: #ffffff;
            --header-bg: #ffffff;
            --card-bg: #ffffff;
            --text-primary: #1d2129;
            --text-secondary: #606770;
            --text-light: #ffffff;
            --accent-blue: #5c67e2;
            --accent-blue-dark: #4a54b5;
            --accent-red: #ff5c5c;
            --accent-green: #2ecc71;
            --shadow-color: rgba(0, 0, 0, 0.08);
            --border-color: #e0e6f1;
            --sidebar-width: 280px;
        }
        
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: var(--primary-bg); color: var(--text-primary); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        
        .page-container {
            display: flex;
            min-height: 100vh;
        }

        /* --- Sidebar Styles --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            box-shadow: 2px 0 10px var(--shadow-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            z-index: 1000;
        }
        .sidebar-header { padding: 25px 20px; display: flex; align-items: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header .barangay-logo { height: 45px; width: 45px; background-color: var(--accent-blue); border-radius: 12px; margin-right: 15px; }
        .sidebar-title .main-title { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .sidebar-title .sub-title { font-size: 0.8rem; color: var(--text-secondary); }

        .sidebar-profile { padding: 25px 20px; display: flex; align-items: center; background-color: #f9faff; }
        .profile-icon { font-size: 38px; color: var(--accent-blue); margin-right: 15px; }
        .profile-info .profile-name { font-weight: 600; color: var(--text-primary); display: block; }
        .profile-info .profile-role { font-size: 0.9rem; color: var(--text-secondary); }
        
        .sidebar-nav { flex-grow: 1; padding: 20px 15px; }
        .nav-link { display: flex; align-items: center; padding: 14px 20px; margin-bottom: 8px; border-radius: 10px; text-decoration: none; color: var(--text-secondary); font-weight: 500; transition: all 0.2s ease-in-out; }
        .nav-link:hover { background-color: var(--primary-bg); color: var(--accent-blue); }
        .nav-link.active { background-color: var(--accent-blue); color: var(--text-light); box-shadow: 0 4px 10px rgba(92, 103, 226, 0.4); }
        .nav-link i { font-size: 1.1rem; margin-right: 18px; width: 20px; text-align: center; }

        .sidebar-footer { padding: 20px 15px; border-top: 1px solid var(--border-color); }
        
        /* --- Main Content Styles --- */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .main-header {
            background-color: var(--header-bg);
            box-shadow: 0 2px 4px var(--shadow-color);
            padding: 0 40px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            height: 70px;
        }
        .header-menu { display: flex; align-items: center; color: var(--text-secondary); }
        .header-menu .fa-bell { font-size: 20px; margin-right: 25px; cursor: pointer; }
        .header-menu .fa-user-circle { font-size: 24px; margin-left: 8px; color: var(--accent-blue); }
        
        .page-main {
            flex-grow: 1;
            padding: 30px 40px;
        }
        
        /* --- Header Mobile Responsive --- */
        @media (max-width: 767px) {
            .main-header {
                padding: 0 12px;
                height: 60px;
                justify-content: space-between;
            }
            .header-menu {
                gap: 10px;
                font-size: 0.9rem;
            }
            .header-menu .fa-bell {
                font-size: 18px;
                margin-right: 12px;
            }
            .page-main {
                padding: 12px;
            }
        }

        /* --- Mobile: let body be the scroll container --- */
        @media (max-width: 767px) {
            html, body {
                height: auto !important;
                min-height: 0 !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior-y: auto;
                touch-action: manipulation;
            }
            .page-container {
                display: block !important;
                min-height: auto !important;
                height: auto !important;
                overflow: visible !important;
                position: relative !important;
            }
            .main-content {
                display: block !important;
                margin-left: 0 !important;
                overflow: visible !important;
                height: auto !important;
                min-height: auto !important;
            }
            .page-main {
                overflow: visible !important;
                padding-bottom: 90px !important;
                height: auto !important;
                min-height: auto !important;
            }
            .main-header {
                position: sticky !important;
                top: 0;
                z-index: 100;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar overlay — darkens background when sidebar is open on mobile -->
    <div id="sidebar-overlay"></div>

    <div class="page-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <header class="main-header">
                <!-- Hamburger toggle button (mobile only) -->
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open navigation menu" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-menu relative">
                    <!-- Notification Bell -->
                    <div class="relative group" id="notif-bell-wrapper">
                        <button id="notif-bell" class="focus:outline-none">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1.5 py-0.5 font-bold animate-pulse"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Dropdown -->
                        <div id="notif-dropdown" class="hidden group-focus-within:block group-hover:block absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                            <div class="p-4 border-b font-bold text-gray-700 flex items-center"><i class="fas fa-bell mr-2 text-blue-600"></i>Notifications</div>
                            <ul class="max-h-80 overflow-y-auto">
                                <?php if (count($notifications) === 0): ?>
                                    <li class="p-4 text-gray-500 text-center">No notifications</li>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <?php $notif_link = sanitize_notification_link_server($notif['link'] ?? ''); ?>
                                        <li class="px-4 py-3 border-b last:border-b-0 <?php echo !$notif['is_read'] ? 'bg-blue-50' : ''; ?>">
                                            <?php if ($notif_link !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($notif_link, ENT_QUOTES, 'UTF-8'); ?>" class="block text-gray-800 hover:text-blue-700">
                                            <?php else: ?>
                                            <span class="block text-gray-800">
                                            <?php endif; ?>
                                                <div class="text-sm"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                <div class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></div>
                                            <?php if ($notif_link !== ''): ?>
                                            </a>
                                            <?php else: ?>
                                            </span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <span>Welcome, <?= htmlspecialchars($user_fullname) ?></span>
                    <a href="account.php" class="text-blue-600 hover:text-blue-800 transition-colors duration-200" title="My Account">
                        <i class="fas fa-user-circle"></i>
                    </a>
                </div>
            </header>
            <main class="page-main"> 
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // --- Off-Canvas Sidebar Toggle ---
                const hamburgerBtn = document.getElementById('hamburger-btn');
                const overlay = document.getElementById('sidebar-overlay');
                const body = document.body;

                function openSidebar() {
                    body.classList.add('sidebar-open');
                    hamburgerBtn.setAttribute('aria-expanded', 'true');
                }

                function closeSidebar() {
                    body.classList.remove('sidebar-open');
                    hamburgerBtn.setAttribute('aria-expanded', 'false');
                }

                if (hamburgerBtn) {
                    hamburgerBtn.addEventListener('click', function() {
                        body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
                    });
                }

                // Close sidebar when clicking overlay
                if (overlay) {
                    overlay.addEventListener('click', closeSidebar);
                }

                // Close sidebar when any nav link is tapped (smooth mobile UX)
                document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            closeSidebar();
                        }
                    });
                });

                // Toggle notification dropdown

                const bell = document.getElementById('notif-bell');
                const dropdown = document.getElementById('notif-dropdown');
                const wrapper = document.getElementById('notif-bell-wrapper');
                if (bell && dropdown) {
                    bell.addEventListener('click', function(e) {
                        e.stopPropagation();
                        dropdown.classList.toggle('hidden');
                        // Mark notifications as read via AJAX
                        if (!dropdown.classList.contains('hidden')) {
                            const formData = new URLSearchParams();
                            formData.append('csrf_token', <?php echo json_encode($mark_read_csrf_token); ?>);
                            fetch('../api/notifications.php?action=mark_all_read', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                body: formData.toString(),
                                credentials: 'same-origin',
                                keepalive: true
                            }).catch(function () {});
                            // Optionally, remove the badge
                            const badge = bell.querySelector('span');
                            if (badge) badge.style.display = 'none';
                        }
                    });
                    // Hide dropdown when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!wrapper.contains(e.target)) {
                            dropdown.classList.add('hidden');
                        }
                    });
                }

                // Live notification polling
                function escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function sanitizeNotificationLink(rawLink) {
                    const link = String(rawLink || '').trim();
                    if (!link) {
                        return '';
                    }

                    const lower = link.toLowerCase();
                    if (lower.startsWith('javascript:') || lower.startsWith('data:')) {
                        return '';
                    }

                    if (lower.startsWith('http://') || lower.startsWith('https://') || link.startsWith('/')) {
                        return link;
                    }

                    // Allow only constrained relative paths.
                    return /^[A-Za-z0-9_\-\/.]+(?:\?[A-Za-z0-9_\-\.=&%]*)?$/.test(link) ? link : '';
                }

                function updateNotifications(notifications) {
                    let unread = notifications.filter(n => n.is_read == 0).length;
                    // Update badge
                    let badge = bell.querySelector('span');
                    if (unread > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1.5 py-0.5 font-bold animate-pulse';
                            bell.appendChild(badge);
                        }
                        badge.textContent = unread;
                        badge.style.display = '';
                    } else if (badge) {
                        badge.style.display = 'none';
                    }
                    // Update dropdown
                    let dropdownList = document.querySelector('#notif-dropdown ul');
                    if (dropdownList) {
                        dropdownList.innerHTML = '';
                        if (notifications.length === 0) {
                            dropdownList.innerHTML = '<li class="p-4 text-gray-500 text-center">No notifications</li>';
                        } else {
                            notifications.forEach(function(notif) {
                                let li = document.createElement('li');
                                li.className = 'px-4 py-3 border-b last:border-b-0 ' + (notif.is_read == 0 ? 'bg-blue-50' : '');
                                const safeLink = sanitizeNotificationLink(notif.link);
                                const hasLink = safeLink !== '';
                                const titleHtml = notif.title ? `<div class="text-xs font-bold text-gray-700 mb-1">${escapeHtml(notif.title)}</div>` : '';
                                const messageHtml = `<div class="text-sm">${escapeHtml(notif.message)}</div>`;
                                const createdAt = new Date(notif.created_at).toLocaleString();
                                const createdAtHtml = `<div class="text-xs text-gray-400 mt-1">${escapeHtml(createdAt)}</div>`;
                                if (hasLink) {
                                    li.innerHTML = `<a href="${escapeHtml(safeLink)}" class="block text-gray-800 hover:text-blue-700">
                                        ${titleHtml}
                                        ${messageHtml}
                                        ${createdAtHtml}
                                    </a>`;
                                } else {
                                    li.innerHTML = `<div class="block text-gray-800 cursor-default">
                                        ${titleHtml}
                                        ${messageHtml}
                                        ${createdAtHtml}
                                    </div>`;
                                }
                                dropdownList.appendChild(li);
                            });
                        }
                    }
                }
                setInterval(function() {
                    fetch('../resident/partials/fetch-live-updates.php')
                        .then(res => res.json())
                        .then(data => {
                            if (data.notifications) {
                                updateNotifications(data.notifications);
                            }
                        });
                }, 5000);
            });
            </script> 