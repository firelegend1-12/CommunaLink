<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// This function will be defined in auth.php or a new resident-auth.php
// For now, let's assume a function that requires a specific role
function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        // Redirect to login or an unauthorized page
        redirect_to('../index.php');
    }
}

require_role('resident');

$page_title = "Resident Dashboard";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

require_once '../config/database.php';

// Fetch Recent Document Requests
$stmtReq = $pdo->prepare("SELECT id, document_type, purpose, date_requested, status FROM document_requests WHERE requested_by_user_id = ? ORDER BY date_requested DESC LIMIT 3");
$stmtReq->execute([$_SESSION['user_id']]);
$recentRequests = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Incident Reports
$stmtInc = $pdo->prepare("SELECT id, type, description, location, reported_at, status FROM incidents WHERE resident_user_id = ? ORDER BY reported_at DESC LIMIT 3");
$stmtInc->execute([$_SESSION['user_id']]);
$recentIncidents = $stmtInc->fetchAll(PDO::FETCH_ASSOC);

// Fetch Latest Announcements for Banner Ticker
try {
    $stmtAnn = $pdo->query("SELECT title FROM announcements ORDER BY created_at DESC LIMIT 5");
    $bannerAnnouncements = $stmtAnn->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $bannerAnnouncements = [];
}

$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id && isset($_SESSION['user_id'])) {
    $resident_id = get_resident_id($pdo, $_SESSION['user_id']);
    if ($resident_id) {
        $_SESSION['resident_id'] = $resident_id;
    }
}

// Fetch Latest Alerts (Notifications) for Banner Ticker
try {
    if ($resident_id) {
        $stmtAlerts = $pdo->prepare("SELECT message FROM notifications WHERE resident_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmtAlerts->execute([$resident_id]);
        $bannerAlerts = $stmtAlerts->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $bannerAlerts = [];
    }
} catch (Exception $e) {
    $bannerAlerts = [];
}

require_once 'partials/header.php';
?>

<style>
/* Page-specific styles for dashboard */
.welcome-banner { background: linear-gradient(105deg, var(--accent-blue), var(--accent-blue-dark)); color: var(--text-light); border-radius: 16px; padding: 40px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.welcome-text h1 { margin: 0 0 8px; font-size: 2.5rem; font-weight: 700; }
.welcome-text p { margin: 0; font-size: 1.1rem; opacity: 0.9; }
.welcome-text .quote { font-style: italic; margin-top: 16px; opacity: 0.8; }
.welcome-logo { text-align: center; color: rgba(255, 255, 255, 0.8); }
.barangay-logo-big { height: 100px; width: 100px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; margin: 0 auto 10px; border: 3px solid var(--text-light); }
/* Banner Tickers Container */
.banner-tickers-container {
    flex: 1;
    margin: 0 32px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    min-width: 0;
}

/* Banner Announcements & Alerts Ticker Setup */
.banner-announcements,
.banner-alerts {
    background: rgba(255,255,255,0.13);
    border: 1.5px solid rgba(255,255,255,0.25);
    border-radius: 12px;
    padding: 14px 20px;
    overflow: hidden;
    align-self: stretch;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative; /* for absolute children or pseudo */
}

.banner-alerts {
    background: rgba(255,100,100,0.15);
    border-color: rgba(255,200,200,0.3);
}

.banner-announcements .ann-label,
.banner-alerts .ann-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    opacity: 0.9;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.banner-announcements .ann-ticker,
.banner-alerts .ann-ticker {
    overflow: hidden; 
    white-space: nowrap; 
    width: 100%;
    position: relative;
    mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
    -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
}

.banner-announcements .ann-ticker-inner,
.banner-alerts .ann-ticker-inner {
    display: inline-block;
    animation: ticker-scroll 25s linear infinite;
    font-size: 18px;
    font-weight: 500;
    opacity: 0.95;
    white-space: nowrap;
    will-change: transform;
}

.banner-announcements .ann-ticker-inner:hover,
.banner-alerts .ann-ticker-inner:hover {
    animation-play-state: paused;
}

.banner-announcements .ann-sep,
.banner-alerts .ann-sep { 
    margin: 0 32px; 
    opacity: 0.5; 
    font-size: 0.6em;
    vertical-align: middle;
}

@keyframes ticker-scroll {
    0%   { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(-50%, 0, 0); }
}
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-bottom: 30px; }
.action-card { display: block; background-color: var(--card-bg); border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px var(--shadow-color); transition: transform 0.2s ease, box-shadow 0.2s ease; text-decoration: none; color: var(--text-light); position: relative; overflow: hidden; }
.action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px var(--shadow-color); }
.action-card i { font-size: 2.5rem; margin-bottom: 16px; position: relative; z-index: 1; }
.action-card h3 { margin: 0 0 8px; font-size: 1.4rem; font-weight: 600; position: relative; z-index: 1; }
.action-card p { margin: 0; opacity: 0.9; position: relative; z-index: 1; }
.action-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background-color: rgba(255,255,255,0.1); border-radius: 50%; transition: all 0.3s ease; }
.action-card:hover::before { transform: scale(3); }
.action-card.report-incident { background: linear-gradient(135deg, #ff7e5f, #feb47b); }
.action-card.my-reports { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.action-card.barangay-services { background: linear-gradient(135deg, #2af598, #009efd); }
.recent-reports { background-color: var(--card-bg); border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px var(--shadow-color); }
.recent-reports-header { display: flex; align-items: center; margin-bottom: 20px; }
.recent-reports-header i { font-size: 1.5rem; margin-right: 12px; color: var(--accent-blue); }
.recent-reports-header h2 { margin: 0; font-size: 1.5rem; font-weight: 600; }
/* Global grid and card styles are now in resident.css */

@media (max-width: 480px) {
    /* On very small phones, maybe single column is better? 
       But user insisted on 2-column max. I'll stick to 2. */
}

</style>

<section class="welcome-banner">
    <div class="welcome-text">
        <h1>Good Morning, <?= htmlspecialchars($user_fullname) ?>!</h1>
        <p>Welcome to your Resident Dashboard</p>
        <p class="quote">“Your barangay is your home. Let's keep it safe and thriving!”</p>
    </div>

    <div class="banner-tickers-container">
        <?php if (!empty($bannerAnnouncements)): ?>
        <div class="banner-announcements mb-3">
            <div class="announcement-ticker w-full">
                <div class="ann-ticker-container flex w-full">
                    <div class="ann-ticker-label text-yellow-300">
                        <i class="fas fa-bullhorn animate-pulse"></i> <span>Announcements</span>
                    </div>
                    <div class="ann-ticker-content flex-grow">
                        <div class="ann-ticker-inner">
                            <?php foreach ($bannerAnnouncements as $ann_title): ?>
                                <span class="ticker-item">
                                    <?= htmlspecialchars($ann_title) ?>
                                    <a href="notifications.php" class="ticker-link ml-2 text-white text-xs font-semibold px-2 py-1 rounded-full bg-white/20 hover:bg-white/30 transition-colors">Read more</a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($bannerAlerts)): ?>
        <div class="banner-alerts">
            <div class="announcement-ticker w-full">
                <div class="ann-ticker-container flex w-full" style="background: rgba(255, 100, 100, 0.2); border-color: rgba(255, 200, 200, 0.4);">
                    <div class="ann-ticker-label text-red-200">
                        <i class="fas fa-bell animate-pulse"></i> <span>Alerts</span>
                    </div>
                    <div class="ann-ticker-content flex-grow">
                        <div class="ann-ticker-inner">
                            <?php foreach ($bannerAlerts as $alertMsg): ?>
                                <span class="ticker-item">
                                    <?= htmlspecialchars($alertMsg) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="welcome-logo">
        <img src="../assets/images/barangay-logo.png" alt="Barangay Logo" class="barangay-logo-big" style="object-fit:cover;">
    </div>
</section>

<section class="quick-actions">
    <a href="report-incident.php" class="action-card report-incident">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Report Incident</h3>
        <p>Report emergencies or issues</p>
    </a>
    <a href="my-reports.php" class="action-card my-reports">
        <i class="fas fa-file-alt"></i>
        <h3>My Reports</h3>
        <p>View and track your reports</p>
    </a>
    <a href="barangay-services.php" class="action-card barangay-services">
        <i class="fas fa-hand-holding-heart"></i>
        <h3>Barangay Services</h3>
        <p>Access available services</p>
    </a>
    <a href="emergency-contacts.php" class="action-card" style="background: linear-gradient(135deg, #f43f5e, #fb7185);">
        <i class="fas fa-phone-alt"></i>
        <h3>Emergency Contacts</h3>
        <p>Important hotline numbers</p>
    </a>
</section>

<div class="dashboard-grid">
    <section class="recent-reports mt-8 bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
        <div class="recent-reports-header">
            <h2>
                <i class="fas fa-history text-blue-600"></i>
                Recent Incident Reports
            </h2>
            <a href="my-reports.php" class="view-all-link">View All</a>
        </div>
        <div class="responsive-card-grid">
            <?php if (empty($recentIncidents)): ?>
                <div class="p-8 text-center text-gray-400">No recent incidents reported.</div>
            <?php else: ?>
                <?php foreach ($recentIncidents as $inc): ?>
                    <?php 
                        $statusClass = strtolower(str_replace(' ', '-', $inc['status']));
                    ?>
                    <a href="my-reports.php" class="dashboard-item hover:shadow-md transition-shadow" style="text-decoration: none; color: inherit;">
                        <div class="dashboard-item-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="dashboard-item-content">
                            <span class="dashboard-item-title"><?= htmlspecialchars($inc['type']) ?></span>
                            <div class="dashboard-item-desc"><?= htmlspecialchars($inc['description']) ?></div>
                            <div class="dashboard-item-meta">
                                <span>Coordinates: <?= htmlspecialchars($inc['location']) ?></span>
                                <span>Reported: <?= date('M d, Y h:i A', strtotime($inc['reported_at'])) ?></span>
                            </div>
                        </div>
                        <div class="dashboard-item-status">
                            <span class="status-text-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($inc['status']) ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="recent-reports mt-8 bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
        <div class="recent-reports-header">
            <h2>
                <i class="fas fa-file-signature text-green-600"></i>
                Recent Document Requests
            </h2>
            <a href="my-requests.php" class="view-all-link">View All</a>
        </div>
        <div class="responsive-card-grid">
            <?php if (empty($recentRequests)): ?>
                <div class="p-8 text-center text-gray-400">No recent document requests.</div>
            <?php else: ?>
                <?php foreach ($recentRequests as $req): ?>
                    <?php 
                        $statusClass = strtolower(str_replace(' ', '-', $req['status'] ?? 'pending'));
                    ?>
                    <a href="my-requests.php" class="dashboard-item hover:shadow-md transition-shadow" style="text-decoration: none; color: inherit;">
                        <div class="dashboard-item-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="dashboard-item-content">
                            <span class="dashboard-item-title"><?= htmlspecialchars($req['document_type']) ?></span>
                            <div class="dashboard-item-desc">Purpose: <?= htmlspecialchars($req['purpose']) ?></div>
                            <div class="dashboard-item-meta">
                                <span>Requested: <?= date('M d, Y h:i A', strtotime($req['date_requested'])) ?></span>
                            </div>
                        </div>
                        <div class="dashboard-item-status">
                            <span class="status-text-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($req['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>


<?php require_once 'partials/footer.php'; ?> 