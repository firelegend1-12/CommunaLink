<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get current script name to determine active page
$current_page = basename($_SERVER['PHP_SELF']);
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

?>
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="../assets/images/barangay-logo.png" alt="Barangay Logo" style="height: 45px; width: 45px; border-radius: 50%; margin-right: 15px; object-fit: cover; background: #5c67e2; display: block;">
        <div class="sidebar-title">
            <span class="main-title">Barangay Masigasig</span>
            <span class="sub-title">Resident Portal</span>
        </div>
    </div>
    <div class="sidebar-profile">
        <i class="fas fa-user-circle profile-icon"></i>
        <div class="profile-info">
            <span class="profile-name"><?= htmlspecialchars($user_fullname) ?></span>
            <span class="profile-role">Resident</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="announcements.php" class="nav-link <?= $current_page === 'announcements.php' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
        <a href="barangay-services.php" class="nav-link <?= $current_page === 'barangay-services.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-heart"></i>
            <span>Barangay Services</span>
        </a>
        <a href="events.php" class="nav-link <?= $current_page === 'events.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Events</span>
        </a>
        <a href="my-reports.php" class="nav-link <?= $current_page === 'my-reports.php' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i>
            <span>My Reports</span>
        </a>
        <a href="report-incident.php" class="nav-link <?= $current_page === 'report-incident.php' ? 'active' : '' ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Report Incident</span>
        </a>
        <a href="emergency-contacts.php" class="nav-link <?= $current_page === 'emergency-contacts.php' ? 'active' : '' ?>">
            <i class="fas fa-phone-alt"></i>
            <span>Emergency Contacts</span>
        </a>
        <a href="chat.php" class="nav-link <?= $current_page === 'chat.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i>
            <span>Live Chat</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="account.php" class="nav-link <?= $current_page === 'account.php' ? 'active' : '' ?>">
            <i class="fas fa-user-cog"></i>
            <span>My Account</span>
        </a>
        <a href="../includes/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside> 