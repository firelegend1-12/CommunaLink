<?php
/**
 * Sidebar Navigation
 * Reusable navigation component for admin pages
 */

// Note: Admin authentication is now handled at the top of each page by including admin_auth.php.
// This partial primarily handles the sidebar UI and navigation highlights.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/permission_checker.php';

// Get current script name and directory depth
$current_script = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

$current_page = '';
if ($current_dir === 'admin' && $current_script === 'index.php') {
    $current_page = 'dashboard';
} elseif ($current_dir === 'pages') {
    $current_page = $current_script;
} elseif ($current_dir === 'admin' && $current_script !== 'index.php') {
    // Handle direct admin directory access
    $current_page = $current_script;
}

function sidebar_has_permission($permission, $role = null) {
    $resolved_role = resolve_permission_role($role);
    if ($resolved_role === null) {
        return false;
    }

    if (!rbac_is_known_role($resolved_role) || !rbac_is_known_permission($permission)) {
        return false;
    }

    return can_access($resolved_role, $permission);
}

$sidebar_role = $_SESSION['role'] ?? null;
$can_view_residents = sidebar_has_permission('view_residents', $sidebar_role);
$can_manage_incidents = sidebar_has_permission('manage_incidents', $sidebar_role);
$can_manage_announcements = sidebar_has_permission('manage_announcements', $sidebar_role);
$can_manage_events = sidebar_has_permission('manage_events', $sidebar_role);
$can_view_monitoring = sidebar_has_permission('view_monitoring_requests', $sidebar_role);
$can_manage_documents = sidebar_has_permission('manage_documents', $sidebar_role);
$can_user_management = sidebar_has_permission('user_management', $sidebar_role);
$can_view_logs = sidebar_has_permission('view_logs', $sidebar_role);
$can_show_admin_tools = $can_user_management || $can_view_logs;



// Determine time-based greeting
date_default_timezone_set('Asia/Manila');
$hour = (int)date('H');
$greeting = 'Good Morning';
if ($hour >= 12 && $hour < 18) {
    $greeting = 'Good Afternoon';
} elseif ($hour >= 18) {
    $greeting = 'Good Evening';
}

?>
<div class="hidden md:flex md:flex-shrink-0 sidebar-container">
    <div class="flex flex-col w-64 bg-gray-800 flex-shrink-0">
        <!-- Sidebar Header -->
        <div class="flex flex-row items-center justify-center px-4 bg-gray-900 text-center"
             style="height: 72px; min-height: 72px; max-height: 72px; overflow: hidden;">
            <?php $logo_path = ($current_dir === 'pages') ? '../../assets/images/barangay-logo.png' : '../assets/images/barangay-logo.png'; ?>
            <img src="<?php echo $logo_path; ?>" alt="Barangay Logo" style="max-width: 40px; margin-right: 12px; border-radius: 8px;">
            <div class="text-left whitespace-nowrap">
                <div class="text-lg font-semibold text-white"><?php echo $greeting; ?>,</div>
                <div class="text-md text-blue-400">Barangay Pakiad Oton</div>
                <!-- PHP Test: <?php echo "Current page: " . $current_page; ?> -->
            </div>
        </div>
        
        <!-- JavaScript for dropdown functionality -->
        <!-- External Sidebar Assets -->
        <?php $asset_path = ($current_dir === 'pages') ? '../../assets' : '../assets'; ?>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo $asset_path; ?>/css/admin-sidebar.min.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin-sidebar.min.css') ?>">
        <script>
            window.sidebarConfig = {
                currentPage: '<?php echo $current_page; ?>',
                notifBase: '<?php echo ($current_dir === "pages") ? "../../api/notifications.php" : "../api/notifications.php"; ?>'
            };
        </script>
        <script src="<?php echo $asset_path; ?>/js/admin-sidebar.min.js?v=<?= filemtime(__DIR__ . '/../../assets/js/admin-sidebar.min.js') ?>" defer></script>
        <script src="<?php echo $asset_path; ?>/js/system-worker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/system-worker.js') ?>" defer></script>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto px-4 py-2 mt-4 sidebar-scroll">
            <nav class="space-y-1">
                <!-- Dashboard Link -->
                <a href="<?php echo ($current_dir === 'pages') ? '../index.php' : 'index.php'; ?>" class="<?php echo $current_page === 'dashboard' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                    <i class="fas fa-tachometer-alt mr-3 text-lg flex-shrink-0 <?php echo $current_page === 'dashboard' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Dashboard
                </a>
                
                <!-- Residents Link -->
                <?php if ($can_view_residents): ?>
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/residents.php' : 'residents.php'; ?>" class="<?php echo $current_page === 'residents.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                    <i class="fas fa-users mr-3 text-lg flex-shrink-0 <?php echo $current_page === 'residents.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Residents
                </a>
                <?php endif; ?>
                
                <!-- Report Dropdown -->
                <?php if ($can_manage_incidents): ?>
                <div id="report-dropdown" class="dropdown-container">
                    <button onclick="toggleDropdown('report-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                        <i class="fas fa-flag mr-3 text-lg flex-shrink-0 <?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Report
                        <span id="report-unread-dot" class="unread-dot"></span>
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200<?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? ' rotate-180' : ''; ?>" id="report-chevron"></i>
                    </button>
                    <div id="report-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? 'block' : 'none'; ?>">
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/incident-reports.php' : 'incident-reports.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'incident-reports.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-bullhorn mr-2 text-gray-400 flex-shrink-0"></i>
                                Incident Reports
                                <span id="incident-reports-badge" class="unread-badge"></span>
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/maps.php' : 'maps.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'maps.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-map-marked-alt mr-2 text-gray-400 flex-shrink-0"></i>
                                Maps
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Community Board Link -->
                <?php if ($can_manage_announcements || $can_manage_events): ?>
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/announcements.php' : 'announcements.php'; ?>" class="<?php echo $current_page === 'announcements.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                    <i class="fas fa-clipboard-list mr-3 text-lg flex-shrink-0 <?php echo $current_page === 'announcements.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Community Board
                </a>
                <?php endif; ?>
                
                <!-- Monitoring of Request Link -->
                <?php if ($can_view_monitoring): ?>
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/monitoring-of-request.php' : 'monitoring-of-request.php'; ?>" class="<?php echo $current_page === 'monitoring-of-request.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                    <i class="fas fa-desktop mr-3 text-lg flex-shrink-0 <?php echo $current_page === 'monitoring-of-request.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Monitoring of Request
                </a>
                <?php endif; ?>

                <!-- Document Requests Link -->
                <?php if ($can_manage_documents): ?>
                <div id="document-dropdown" class="dropdown-container">
                    <button onclick="toggleDropdown('document-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                        <i class="fas fa-file-alt mr-3 text-lg flex-shrink-0 <?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Document Requests
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200<?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? ' rotate-180' : ''; ?>" id="document-chevron"></i>
                    </button>
                    <div id="document-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? 'block' : 'none'; ?>">
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-barangay-clearance.php' : 'new-barangay-clearance.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'new-barangay-clearance.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file mr-2 text-gray-400 flex-shrink-0"></i>
                                New Barangay Clearance
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-certificate-of-indigency.php' : 'new-certificate-of-indigency.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'new-certificate-of-indigency.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file-medical mr-2 text-gray-400 flex-shrink-0"></i>
                                New Cert. of Indigency
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-certificate-of-residency.php' : 'new-certificate-of-residency.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'new-certificate-of-residency.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file-signature mr-2 text-gray-400 flex-shrink-0"></i>
                                New Cert. of Residency
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-barangay-business-clearance.php' : 'new-barangay-business-clearance.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'new-barangay-business-clearance.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file-invoice-dollar mr-2 text-gray-400 flex-shrink-0"></i>
                                New Barangay Business Clearance
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin Dropdown -->
                <?php if ($can_show_admin_tools): ?>
                <div id="admin-dropdown" class="dropdown-container">
                    <button id="admin-dropdown-btn" onclick="toggleDropdown('admin-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['user-management.php', 'logs.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                        <i class="fas fa-cog mr-3 text-lg flex-shrink-0 <?php echo in_array($current_page, ['user-management.php', 'logs.php']) ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Admin
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200<?php echo in_array($current_page, ['user-management.php', 'logs.php']) ? ' rotate-180' : ''; ?>" id="admin-chevron"></i>
                    </button>
                    <div id="admin-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['user-management.php', 'logs.php']) ? 'block' : 'none'; ?>">
                        <?php if ($can_user_management): ?>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/user-management.php' : 'user-management.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'user-management.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-users-cog mr-2 text-gray-400 flex-shrink-0"></i>
                                User Management
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if ($can_view_logs): ?>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/logs.php' : 'logs.php'; ?>" class="flex w-full items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white whitespace-nowrap <?php echo $current_page === 'logs.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-clipboard-list mr-2 text-gray-400 flex-shrink-0"></i>
                                Logs
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Footer Links -->
        <div class="p-4 border-t border-gray-700 space-y-1">
             <a href="<?php echo ($current_dir === 'admin') ? 'pages/account.php' : 'account.php'; ?>" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                <i class="fas fa-user-circle mr-3 text-lg text-gray-400 flex-shrink-0"></i>
                Account
            </a>
            <a href="<?php echo ($current_dir === 'admin') ? 'pages/about-us.php' : 'about-us.php'; ?>" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                <i class="fas fa-info-circle mr-3 text-lg text-gray-400 flex-shrink-0"></i>
                About Us
            </a>
            
            <a href="<?php echo ($current_dir === 'pages') ? '../../includes/logout.php' : '../includes/logout.php'; ?>" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex w-full items-center px-3 py-3 text-sm font-medium rounded-md whitespace-nowrap">
                <i class="fas fa-sign-out-alt mr-3 text-lg text-gray-400 flex-shrink-0"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

