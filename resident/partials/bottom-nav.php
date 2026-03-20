<?php
// Get current page to set active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="mobile-bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="my-requests.php" class="bottom-nav-item <?= $current_page === 'my-requests.php' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice"></i>
        <span>Requests</span>
    </a>
    <a href="chat.php" class="bottom-nav-item <?= $current_page === 'chat.php' ? 'active' : '' ?>">
        <i class="fas fa-comments"></i>
        <span>Chat</span>
    </a>
    <a href="notifications.php" class="bottom-nav-item <?= $current_page === 'notifications.php' ? 'active' : '' ?>">
        <div class="relative inline-block">
            <i class="fas fa-bell"></i>
            <?php if (isset($unread_count) && $unread_count > 0): ?>
                <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                </span>
            <?php endif; ?>
        </div>
        <span>Alerts</span>
    </a>
</nav>

<style>
/* Ensure bottom nav is only visible on mobile */
@media (min-width: 768px) {
    .mobile-bottom-nav {
        display: none !important;
    }
}
</style>
