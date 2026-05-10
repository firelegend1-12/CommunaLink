<?php
// Get current page to set active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="mobile-bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="my-reports.php" class="bottom-nav-item <?= $current_page === 'my-reports.php' ? 'active' : '' ?>">
        <i class="fas fa-file-alt"></i>
        <span>My Reports</span>
    </a>
    <a href="my-document-requests.php" class="bottom-nav-item <?= $current_page === 'my-document-requests.php' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice"></i>
        <span>Documents</span>
    </a>
    <a href="announcements.php" class="bottom-nav-item <?= in_array($current_page, ['announcements.php', 'notifications.php'], true) ? 'active' : '' ?>">
        <i class="fas fa-bell"></i>
        <span>Notification</span>
    </a>
</nav>

<style>
/* Ensure bottom nav is only visible on mobile */
@media (min-width: 768px) {
    .mobile-bottom-nav {
        display: none !important;
    }
}
@media (max-width: 767px) {
    .bottom-nav-item span {
        white-space: nowrap;
        line-height: 1.05;
    }
}
@media (max-width: 400px) {
    .bottom-nav-item {
        padding-left: 2px !important;
        padding-right: 2px !important;
        font-size: 0.68rem !important;
    }
    .bottom-nav-item i {
        font-size: 1.1rem !important;
    }
}
</style>
