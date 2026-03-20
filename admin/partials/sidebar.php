<?php
/**
 * Sidebar Navigation
 * Reusable navigation component for admin pages
 */

// Enforce access control for the entire admin section
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin'])) {
    // If user is a resident, redirect to their dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        header('Location: ../resident/dashboard.php');
    } else {
        // Otherwise, redirect to the main login page
        header('Location: ../index.php');
    }
    exit;
}

// Get current script name to determine active page
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
        <link rel="stylesheet" href="<?php echo $asset_path; ?>/css/admin-sidebar.min.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin-sidebar.min.css') ?>">
        <script>
            window.sidebarConfig = {
                currentPage: '<?php echo $current_page; ?>',
                apiBase: '<?php echo ($current_dir === "pages") ? "../../api/chat.php" : "../api/chat.php"; ?>',
                notifBase: '<?php echo ($current_dir === "pages") ? "../../api/notifications.php" : "../api/notifications.php"; ?>'
            };
        </script>
        <script src="<?php echo $asset_path; ?>/js/admin-sidebar.min.js?v=<?= filemtime(__DIR__ . '/../../assets/js/admin-sidebar.min.js') ?>" defer></script>

        <!-- Navigation Links -->
        <div class="flex-grow px-4 py-2 mt-4">
            <nav class="space-y-1">
                <!-- Dashboard Link -->
                <a href="<?php echo ($current_dir === 'pages') ? '../index.php' : 'index.php'; ?>" class="<?php echo $current_page === 'dashboard' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                    <i class="fas fa-tachometer-alt mr-3 text-lg <?php echo $current_page === 'dashboard' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Dashboard
                </a>
                
                <!-- Residents Link -->
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/residents.php' : 'residents.php'; ?>" class="<?php echo $current_page === 'residents.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                    <i class="fas fa-users mr-3 text-lg <?php echo $current_page === 'residents.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Residents
                </a>
                
                <!-- Report Dropdown -->
                <div id="report-dropdown" class="dropdown-container">
                    <button onclick="toggleDropdown('report-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                        <i class="fas fa-flag mr-3 text-lg <?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Report
                        <span id="report-unread-dot" class="chat-unread-dot"></span>
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200" id="report-chevron"></i>
                    </button>
                    <div id="report-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['incident-reports.php', 'maps.php']) ? 'block' : 'none'; ?>">
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/incident-reports.php' : 'incident-reports.php'; ?>" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'incident-reports.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-bullhorn mr-2 text-gray-400"></i>
                                Incident Reports
                                <span id="incident-reports-badge" class="chat-unread-badge"></span>
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/maps.php' : 'maps.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'maps.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-map-marked-alt mr-2 text-gray-400"></i>
                                Maps
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Announcements Link -->
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/announcements.php' : 'announcements.php'; ?>" class="<?php echo $current_page === 'announcements.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                    <i class="fas fa-newspaper mr-3 text-lg <?php echo $current_page === 'announcements.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Announcements
                </a>
                

                
                <!-- Events Link -->
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/events.php' : 'events.php'; ?>" class="<?php echo $current_page === 'events.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                    <i class="fas fa-calendar-alt mr-3 text-lg <?php echo $current_page === 'events.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Events
                </a>
                
                <!-- Monitoring of Request Link -->
                <a href="<?php echo ($current_dir === 'admin') ? 'pages/monitoring-of-request.php' : 'monitoring-of-request.php'; ?>" class="<?php echo $current_page === 'monitoring-of-request.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                    <i class="fas fa-desktop mr-3 text-lg <?php echo $current_page === 'monitoring-of-request.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                    Monitoring of Request
                </a>
                
                <!-- Business Link -->
                <div id="business-dropdown" class="dropdown-container">
                    <button onclick="toggleDropdown('business-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['business-records.php', 'business-transactions.php', 'business-monitoring.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                        <i class="fas fa-store mr-3 text-lg <?php echo $current_page === 'business-records.php' || $current_page === 'business-transactions.php' || $current_page === 'business-monitoring.php' ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Business
                        <span id="biz-unread-dot" class="chat-unread-dot"></span>
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200" id="business-chevron"></i>
                    </button>
                    <div id="business-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['business-records.php', 'business-transactions.php', 'business-monitoring.php']) ? 'block' : 'none'; ?>">
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/business-records.php' : 'business-records.php'; ?>" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'business-records.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-list-alt mr-2 text-gray-400"></i>
                                Business Records
                                <span id="biz-records-badge" class="chat-unread-badge"></span>
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/business-transactions.php' : 'business-transactions.php'; ?>" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'business-transactions.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-exchange-alt mr-2 text-gray-400"></i>
                                Transactions
                                <span id="biz-transactions-badge" class="chat-unread-badge"></span>
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/business-monitoring.php' : 'business-monitoring.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'business-monitoring.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-chart-line mr-2 text-gray-400"></i>
                                Monitoring
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Document Requests Link -->
                <div id="document-dropdown" class="dropdown-container">
                    <button onclick="toggleDropdown('document-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                        <i class="fas fa-file-alt mr-3 text-lg <?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Document Requests
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200" id="document-chevron"></i>
                    </button>
                    <div id="document-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['new-barangay-clearance.php', 'new-certificate-of-indigency.php', 'new-certificate-of-residency.php', 'new-barangay-business-clearance.php']) ? 'block' : 'none'; ?>">
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-barangay-clearance.php' : 'new-barangay-clearance.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'new-barangay-clearance.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file mr-2 text-gray-400"></i>
                                New Barangay Clearance
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-certificate-of-indigency.php' : 'new-certificate-of-indigency.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'new-certificate-of-indigency.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file-medical mr-2 text-gray-400"></i>
                                New Cert. of Indigency
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-certificate-of-residency.php' : 'new-certificate-of-residency.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'new-certificate-of-residency.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file-signature mr-2 text-gray-400"></i>
                                New Cert. of Residency
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/new-barangay-business-clearance.php' : 'new-barangay-business-clearance.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'new-barangay-business-clearance.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-file-invoice-dollar mr-2 text-gray-400"></i>
                                New Barangay Business Clearance
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Admin Dropdown -->
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin'])): ?>
                <div id="admin-dropdown" class="dropdown-container">
                    <button id="admin-dropdown-btn" onclick="toggleDropdown('admin-dropdown')" class="w-full text-left <?php echo in_array($current_page, ['user-management.php', 'chat.php', 'logs.php', 'rate-limiting.php', 'performance.php']) ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                        <i class="fas fa-cog mr-3 text-lg <?php echo in_array($current_page, ['user-management.php', 'chat.php', 'logs.php', 'rate-limiting.php', 'performance.php']) ? 'text-blue-400' : 'text-gray-400'; ?>"></i>
                        Admin
                        <span id="admin-unread-dot" class="chat-unread-dot"></span>
                        <i class="fas fa-chevron-down ml-auto h-3 w-3 transform transition-transform duration-200" id="admin-chevron"></i>
                    </button>
                    <div id="admin-content" class="mt-1 ml-4 space-y-1 bg-gray-700 rounded-md overflow-hidden divide-y divide-gray-600" style="display: <?php echo in_array($current_page, ['user-management.php', 'chat.php', 'logs.php', 'rate-limiting.php', 'performance.php']) ? 'block' : 'none'; ?>">
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/user-management.php' : 'user-management.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'user-management.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-users-cog mr-2 text-gray-400"></i>
                                User Management
                            </a>
                        </div>
                        <div>
                            <a id="chat-nav-link" href="<?php echo ($current_dir === 'admin') ? 'pages/chat.php' : 'chat.php'; ?>" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'chat.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-comments mr-2 text-gray-400"></i>
                                Chat
                                <span id="chat-unread-badge" class="chat-unread-badge"></span>
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/logs.php' : 'logs.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'logs.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-clipboard-list mr-2 text-gray-400"></i>
                                Logs
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/rate-limiting.php' : 'rate-limiting.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'rate-limiting.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-shield-alt mr-2 text-gray-400"></i>
                                Rate Limiting
                            </a>
                        </div>
                        <div>
                            <a href="<?php echo ($current_dir === 'admin') ? 'pages/performance.php' : 'performance.php'; ?>" class="block px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-600 hover:text-white <?php echo $current_page === 'performance.php' ? 'bg-gray-900 text-white' : ''; ?>">
                                <i class="fas fa-tachometer-alt mr-2 text-gray-400"></i>
                                Performance
                            </a>
                        </div>


                    </div>
                </div>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Footer Links -->
        <div class="p-4 border-t border-gray-700 space-y-1">
             <a href="<?php echo ($current_dir === 'admin') ? 'pages/account.php' : 'account.php'; ?>" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                <i class="fas fa-user-circle mr-3 text-lg text-gray-400"></i>
                Account
            </a>
            <a href="<?php echo ($current_dir === 'admin') ? 'pages/about-us.php' : 'about-us.php'; ?>" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                <i class="fas fa-info-circle mr-3 text-lg text-gray-400"></i>
                About Us
            </a>
            <a href="<?php echo ($current_dir === 'pages') ? '../../includes/logout.php' : '../includes/logout.php'; ?>" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-3 py-3 text-sm font-medium rounded-md">
                <i class="fas fa-sign-out-alt mr-3 text-lg text-gray-400"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
