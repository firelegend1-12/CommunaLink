<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_role('resident');

if (function_exists('apply_page_security_headers')) {
    apply_page_security_headers('public');
}

$page_title = $page_title ?? "Resident Portal"; // Allow pages to set their own title
$user_fullname = $_SESSION['fullname'] ?? 'Resident';
$user_id = $_SESSION['user_id'] ?? null;
$resident_profile_avatar_src = resident_default_profile_avatar_data_uri();
$resident_bell_items = [];
$resident_bell_unread_count = 0;
if ($user_id) {
    require_once '../config/database.php';
    $stmtProfile = $pdo->prepare('SELECT profile_image_path FROM residents WHERE user_id = ? LIMIT 1');
    $stmtProfile->execute([$user_id]);
    $profileRow = $stmtProfile->fetch(PDO::FETCH_ASSOC);
    $rawProfilePath = isset($profileRow['profile_image_path']) ? trim((string) $profileRow['profile_image_path']) : '';
    if ($rawProfilePath !== '') {
        $resolved = resident_profile_image_url($rawProfilePath);
        if ($resolved !== '') {
            $resident_profile_avatar_src = $resolved;
        }
    }

    try {
        $resolved_resident_id = function_exists('get_resident_id') ? (int)(get_resident_id($pdo, (int)$user_id) ?? 0) : 0;
        $resident_bell_items = get_resident_board_notifications($pdo, (int)$user_id, $resolved_resident_id, 5);
        foreach ($resident_bell_items as $bell_item) {
            if ((int)($bell_item['is_read'] ?? 0) === 0) {
                $resident_bell_unread_count++;
            }
        }
    } catch (Throwable $e) {
        error_log('Resident bell preview load failed: ' . $e->getMessage());
        $resident_bell_items = [];
        $resident_bell_unread_count = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome 6.4.2 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/resident.css">
    <!-- PWA Setup -->
    <link rel="manifest" href="<?= htmlspecialchars(app_url('/resident/manifest.json')) ?>">
    <meta name="theme-color" content="#5c67e2">
    <script src="../assets/js/system-worker.js" defer></script>
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
        .resident-profile-thumb {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            flex-shrink: 0;
            background-color: var(--accent-blue);
            display: block;
        }
        .header-profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            flex-shrink: 0;
            background-color: #e8eaf6;
        }
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
        .header-menu { display: flex; align-items: center; color: var(--text-secondary); min-width: 0; max-width: 100%; }
        .header-menu > span { min-width: 0; }
        .header-menu .header-profile-avatar { margin-left: 8px; }
        .notif-wrapper {
            position: relative;
            margin-left: 12px;
            flex-shrink: 0;
        }
        .notif-bell-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 9999px;
            text-decoration: none;
            color: var(--accent-blue);
            background: #eef2ff;
            border: 1px solid #dbe2ff;
            transition: all 0.2s ease;
            position: relative;
        }
        .notif-bell-btn:hover,
        .notif-bell-btn:focus-visible {
            color: #ffffff;
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 103, 226, 0.25);
        }
        .notif-bell-badge {
            position: absolute;
            top: -5px;
            right: -4px;
            min-width: 16px;
            height: 16px;
            border-radius: 9999px;
            background: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
            padding: 0 4px;
            border: 2px solid #ffffff;
        }
        .notif-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            width: min(360px, calc(100vw - 24px));
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.16);
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(6px);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 70;
        }
        .notif-dropdown-header {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #334155;
            padding: 10px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }
        .notif-dropdown-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .notif-row {
            display: block;
            padding: 10px 12px;
            text-decoration: none;
            color: #1f2937;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s ease;
        }
        .notif-row:hover {
            background-color: #f8fafc;
        }
        .notif-row:last-child {
            border-bottom: none;
        }
        .notif-row-title {
            font-size: 0.86rem;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 4px;
        }
        .notif-row-message {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.35;
        }
        .notif-row-time {
            display: block;
            margin-top: 6px;
            font-size: 0.72rem;
            color: #94a3b8;
        }
        .notif-row-empty {
            padding: 16px 12px;
            font-size: 0.82rem;
            color: #64748b;
        }
        .notif-dropdown-footer {
            display: block;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--accent-blue);
            background: #f8fafc;
            padding: 10px 12px;
            border-top: 1px solid #edf2f7;
        }
        .notif-dropdown-footer:hover {
            background: #eef2ff;
        }
        @media (hover: hover) and (pointer: fine) {
            .notif-wrapper:hover .notif-dropdown,
            .notif-wrapper:focus-within .notif-dropdown {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                pointer-events: auto;
            }
        }
        
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
                max-width: 100%;
                min-width: 0;
                overflow: hidden;
            }
            .notif-wrapper {
                margin-left: 8px;
            }
            .notif-bell-btn {
                width: 34px;
                height: 34px;
            }
            .header-menu span {
                display: none !important;
            }
            .notif-dropdown {
                display: none !important;
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

        /* --- Global Back Button --- */
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: white;
            color: var(--text-primary);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        .back-button:hover {
            background-color: #f8f9fa;
        }
        .back-button i {
            margin-right: 8px;
        }
        @media (max-width: 767px) {
            .back-button {
                padding: 8px 14px;
                font-size: 0.85rem;
                margin-bottom: 16px;
            }
        }
<?php if (!empty($document_print_layout)): ?>
        .document-print-layout .printable-area { text-align: center; }
        .document-print-layout .printable-area svg { display: block; margin: 0 auto; max-width: 100%; height: auto; }

        @page { size: A4 portrait; margin: 10mm; }
        @media print {
            body.document-print-layout {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            body.document-print-layout * { visibility: hidden !important; }
            body.document-print-layout .printable-area,
            body.document-print-layout .printable-area * { visibility: visible !important; }
            body.document-print-layout .printable-area {
                position: absolute !important;
                top: 0 !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                width: 190mm !important;
                max-width: 190mm !important;
                min-height: 277mm !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                border: none !important;
                margin: 0 auto !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            body.document-print-layout .printable-area svg { width: 100% !important; max-width: 100% !important; height: auto !important; }
        }
<?php endif; ?>
    </style>
</head>
<body<?php echo !empty($document_print_layout) ? ' class="document-print-layout"' : ''; ?>>
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
                <div class="header-menu relative" style="margin-left: auto;">
                    <span>Welcome, <?= htmlspecialchars($user_fullname) ?></span>
                    <div id="notif-wrapper" class="notif-wrapper">
                        <a href="announcements.php" class="notif-bell-btn" aria-label="Open Community Board notifications" title="Community Board notifications">
                            <i class="fas fa-bell" aria-hidden="true"></i>
                            <?php if ($resident_bell_unread_count > 0): ?>
                            <span class="notif-bell-badge"><?= $resident_bell_unread_count > 9 ? '9+' : (int)$resident_bell_unread_count ?></span>
                            <?php endif; ?>
                        </a>
                        <div id="notif-dropdown" class="notif-dropdown" aria-hidden="true">
                            <div class="notif-dropdown-header">Community Board Updates</div>
                            <div class="notif-dropdown-list">
                                <?php if (empty($resident_bell_items)): ?>
                                <div class="notif-row-empty">No updates yet. Click the bell to open the Community Board.</div>
                                <?php else: ?>
                                    <?php foreach ($resident_bell_items as $bell_item): ?>
                                        <?php
                                            $bell_title = trim((string)($bell_item['title'] ?? 'Community Board update'));
                                            $bell_message = trim((string)($bell_item['message'] ?? ''));
                                            if ($bell_message === '') {
                                                $bell_message = trim((string)($bell_item['content'] ?? ''));
                                            }
                                            if (function_exists('mb_strimwidth')) {
                                                $bell_message = mb_strimwidth($bell_message, 0, 110, '...');
                                            } else {
                                                $bell_message = strlen($bell_message) > 110 ? substr($bell_message, 0, 107) . '...' : $bell_message;
                                            }
                                            $bell_created = strtotime((string)($bell_item['created_at'] ?? ''));
                                            $bell_created_label = $bell_created ? date('M j, Y g:i A', $bell_created) : 'Just now';
                                        ?>
                                        <a href="announcements.php" class="notif-row">
                                            <div class="notif-row-title"><?= htmlspecialchars($bell_title) ?></div>
                                            <?php if ($bell_message !== ''): ?>
                                            <div class="notif-row-message"><?= htmlspecialchars($bell_message) ?></div>
                                            <?php endif; ?>
                                            <span class="notif-row-time"><?= htmlspecialchars($bell_created_label) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <a href="announcements.php" class="notif-dropdown-footer">Open Community Board</a>
                        </div>
                    </div>
                    <div id="profile-wrapper" class="relative" style="margin-left: 12px; cursor: pointer;">
                        <button id="profile-btn" class="focus:outline-none flex items-center gap-1 text-blue-600 hover:text-blue-800 transition-colors duration-200" title="Profile Menu">
                            <img src="<?= htmlspecialchars($resident_profile_avatar_src, ENT_QUOTES, 'UTF-8') ?>" alt="" class="header-profile-avatar" width="32" height="32">
                            <i class="fas fa-chevron-down text-[10px]"></i>
                        </button>
                        <!-- Profile Dropdown -->
                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                            <div class="py-2">
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors"><i class="fas fa-user-cog w-5 text-center mr-2 text-blue-500"></i> My Account</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="../includes/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"><i class="fas fa-sign-out-alt w-5 text-center mr-2"></i> Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <main class="page-main">
                <?php if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php'): ?>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <?php endif; ?> 
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

                // Toggle profile dropdown
                const profileBtn = document.getElementById('profile-btn');
                const profileDropdown = document.getElementById('profile-dropdown');
                const profileWrapper = document.getElementById('profile-wrapper');
                if (profileBtn && profileDropdown) {
                    profileBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        profileDropdown.classList.toggle('hidden');
                    });
                    document.addEventListener('click', function(e) {
                        if (profileWrapper && !profileWrapper.contains(e.target)) {
                            profileDropdown.classList.add('hidden');
                        }
                    });
                }
            });
            </script> 
