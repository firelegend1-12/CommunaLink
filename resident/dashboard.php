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
$stmtReq = $pdo->prepare("SELECT * FROM document_requests WHERE requested_by_user_id = ? ORDER BY date_requested DESC LIMIT 3");
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

// --- Placeholder removed, replaced with real query above ---

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
/* Banner Announcements Ticker */
.banner-announcements {
    flex: 1;
    margin: 0 32px;
    background: rgba(255,255,255,0.13);
    border: 1.5px solid rgba(255,255,255,0.25);
    border-radius: 12px;
    padding: 18px 20px;
    overflow: hidden;
    align-self: stretch;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}
.banner-announcements .ann-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    opacity: 0.7;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.banner-announcements .ann-ticker { overflow: hidden; white-space: nowrap; width: 100%; }
.banner-announcements .ann-ticker-inner {
    display: inline-block;
    animation: ticker-scroll 20s linear infinite;
    font-size: 20px;
    font-weight: 500;
    opacity: 0.95;
    white-space: nowrap;
}
.banner-announcements .ann-sep { margin: 0 24px; opacity: 0.45; }
@keyframes ticker-scroll {
    0%   { transform: translateX(100%); }
    100% { transform: translateX(-100%); }
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
.reports-list { display: flex; flex-direction: column; }
.report-item { display: flex; align-items: flex-start; padding: 20px 0; border-bottom: 1px solid var(--border-color); }
.report-item:last-child { border-bottom: none; }
.report-icon { font-size: 1.2rem; color: var(--text-secondary); margin-right: 20px; padding-top: 4px; }
.report-details { flex-grow: 1; }
.report-details h4 { margin: 0 0 4px; font-size: 1.1rem; font-weight: 600; }
.report-details p { margin: 0 0 4px; color: var(--text-secondary); font-size: 0.95rem; }
.report-details .location { font-size: 0.85rem; font-style: italic; }
.report-status { margin-left: 20px; }
.status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
.status-badge.status-pending { background-color: #fffbe6; color: #f7b924; }
.status-badge.status-approved { background-color: #e6f7ff; color: #1890ff; }
.status-badge.status-resolved { background-color: #f6ffed; color: #52c41a; }
.status-badge.status-rejected { background-color: #fff1f0; color: #f5222d; }
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
    align-items: start; /* Independent scaling for both containers */
}
.recent-reports, .latest-announcements {
    background-color: var(--card-bg);
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 12px var(--shadow-color);
}
.recent-reports-header, .announcements-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}
.recent-reports-header i, .announcements-header i {
    font-size: 1.5rem;
    margin-right: 12px;
    color: var(--accent-blue);
}
.recent-reports-header h2, .announcements-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    flex-grow: 1;
}
.view-all-btn {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--accent-blue);
    text-decoration: none;
    transition: color 0.2s ease;
}
.view-all-btn:hover {
    color: var(--accent-blue-dark);
}
.announcement-item {
    display: flex;
    align-items: flex-start;
    padding: 15px 0;
    border-bottom: 1px solid var(--border-color);
}
.announcement-item:last-child {
    border-bottom: none;
}
.announcement-icon {
    font-size: 1.2rem;
    color: var(--text-secondary);
    margin-right: 20px;
    padding-top: 4px;
}
.announcement-details h4 {
    margin: 0 0 4px;
    font-size: 1rem;
    font-weight: 600;
}
.announcement-details p {
    margin: 0 0 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
}
.announcement-meta {
    font-size: 0.8rem;
    font-style: italic;
    color: var(--text-secondary);
}
</style>

<section class="welcome-banner">
    <div class="welcome-text">
        <h1>Good Morning, <?= htmlspecialchars($user_fullname) ?>!</h1>
        <p>Welcome to your Resident Dashboard</p>
        <p class="quote">“Your barangay is your home. Let's keep it safe and thriving!”</p>
    </div>

    <div class="banner-announcements">
        <div class="ann-label"><i class="fas fa-bullhorn"></i> Barangay Announcements</div>
        <div class="ann-ticker">
            <span class="ann-ticker-inner">
                <?php if (empty($bannerAnnouncements)): ?>
                    <span>No current announcements &mdash; stay tuned!</span>
                <?php else: ?>
                    <?php foreach ($bannerAnnouncements as $title): ?>
                        <?= htmlspecialchars($title) ?><span class="ann-sep">&#9679;</span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </span>
        </div>
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
    <section class="recent-reports">
        <div class="recent-reports-header">
            <i class="fas fa-history"></i>
            <h2>Recent Incident Reports</h2>
            <a href="my-reports.php" class="view-all-btn">View All</a>
        </div>
        <div class="reports-list">
            <?php if (empty($recentIncidents)): ?>
                <div class="report-item-empty"><p style="padding: 20px 0; color: #666;">No recent incidents reported.</p></div>
            <?php else: ?>
                <?php foreach ($recentIncidents as $inc): ?>
                    <div class="report-item">
                        <div class="report-icon"><i class="fas fa-bell"></i></div>
                        <div class="report-details">
                            <h4><?= htmlspecialchars($inc['type']) ?></h4>
                            <p><?= htmlspecialchars($inc['description']) ?></p>
                            <p class="location"><?= htmlspecialchars($inc['location']) ?></p>
                            <span class="announcement-meta">Reported: <?= date('M d, Y h:i A', strtotime($inc['reported_at'])) ?></span>
                        </div>
                        <div class="report-status">
                            <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $inc['status']))) ?>">
                                <?= htmlspecialchars($inc['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="recent-reports">
        <div class="recent-reports-header">
            <i class="fas fa-file-signature"></i>
            <h2>Recent Document Requests</h2>
            <a href="my-requests.php" class="view-all-btn">View All</a>
        </div>
        <div class="reports-list">
            <?php if (empty($recentRequests)): ?>
                <div class="report-item-empty"><p style="padding: 20px 0; color: #666;">No recent document requests.</p></div>
            <?php else: ?>
                <?php foreach ($recentRequests as $req): ?>
                    <div class="report-item">
                        <div class="report-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="report-details">
                            <h4><?= htmlspecialchars($req['document_type']) ?></h4>
                            <p>Purpose: <?= htmlspecialchars($req['purpose']) ?></p>
                            <span class="announcement-meta">Requested: <?= date('M d, Y h:i A', strtotime($req['date_requested'])) ?></span>
                        </div>
                        <div class="report-status">
                            <span class="status-badge status-<?= htmlspecialchars(strtolower($req['status'] ?? 'pending')) ?>">
                                <?= htmlspecialchars($req['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

</script>

<?php require_once 'partials/footer.php'; ?> 