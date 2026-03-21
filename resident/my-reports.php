<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        redirect_to('../index.php');
    }
}

require_role('resident');

$page_title = "My Incident Reports";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

require_once '../config/database.php';

$resident_user_id = $_SESSION['user_id'] ?? null;
$reports = [];
if ($resident_user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM incidents WHERE resident_user_id = ? ORDER BY reported_at DESC");
        $stmt->execute([$resident_user_id]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $reports = [];
    }
}

require_once 'partials/header.php';
?>
<style>
    .table-container {
        padding: 30px;
        background-color: transparent;
        max-width: 800px;
        margin: 20px auto;
        border-radius: 0;
        box-shadow: none;
    }
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .table-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }
    .submit-btn {
        background: linear-gradient(135deg, var(--accent-blue), var(--accent-blue-dark));
        color: var(--text-light);
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(92, 103, 226, 0.3);
        transition: all 0.3s ease;
        text-decoration: none;
    }
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(92, 103, 226, 0.5);
    }
    
    .reports-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
</style>
<div class="table-container">
    <div class="table-header">
        <h2>My Submitted Reports</h2>
        <a href="report-incident.php" class="submit-btn">Report New Incident</a>
    </div>
    
    <div class="reports-list" id="reports-list">
        <div style="text-align: center; padding: 30px; color: #666;">Loading reports...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function loadReports() {
        fetch('../api/incidents.php?action=get_my_reports')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('reports-list');
                let html = '';

                if (data.success && data.reports.length > 0) {
                    data.reports.forEach(report => {
                        const formattedDate = new Date(report.reported_at).toLocaleString('en-US', {
                            month: 'short', day: 'numeric', year: 'numeric'
                        });

                        const statusClass = report.status.toLowerCase().replace(' ', '-');
                        
                        let coordsHtml = escapeHTML(report.location);
                        if (report.latitude && report.longitude) {
                            coordsHtml = `<a href="https://www.google.com/maps?q=${report.latitude},${report.longitude}" class="coords-link" target="_blank" onclick="event.stopPropagation();">Coordinates: ${report.latitude.substring(0,9)}, ${report.longitude.substring(0,10)}</a>`;
                        }

                        html += `
                            <div class="mobile-card" onclick="window.location.href='report-details.php?id=${report.id}'" style="cursor:pointer;">
                                <div class="mobile-card-header">
                                    <h3 class="mobile-card-title">${escapeHTML(report.type)}</h3>
                                    <span class="mobile-card-badge ${statusClass}">${escapeHTML(report.status)}</span>
                                </div>
                                <div class="mobile-card-desc">${escapeHTML(report.description || report.type)}</div>
                                <div class="mobile-card-meta">
                                    <div class="mobile-card-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>${coordsHtml}</span>
                                    </div>
                                    <div class="mobile-card-meta-item">
                                        <i class="far fa-clock"></i>
                                        <span>${formattedDate}</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right mobile-card-chevron"></i>
                            </div>
                        `;
                    });
                    
                    // Only update DOM if HTML changed (diff-update approach to reduce flicker)
                    if (list.innerHTML !== html) {
                        list.innerHTML = html;
                    }
                } else if (data.success) {
                    list.innerHTML = '<div style="text-align: center; padding: 30px; color: #666; background: #fff; border-radius: 12px;">You have not submitted any reports yet.</div>';
                } else {
                    list.innerHTML = `<div style="text-align: center; padding: 30px; color: #d92d20; background: #fde8e8; border-radius: 12px;">Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Initial load
    loadReports();
    
    // Polling every 5 seconds for status updates
    setInterval(loadReports, 5000);
});

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}
</script>
<?php require_once 'partials/footer.php'; ?> 