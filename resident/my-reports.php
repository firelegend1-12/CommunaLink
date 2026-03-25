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
$show_cancel_success = isset($_GET['cancelled']) && $_GET['cancelled'] === '1';
$show_cancel_error = isset($_GET['cancel_error']) && $_GET['cancel_error'] === '1';

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
<?php if ($show_cancel_success): ?>
<div id="toast-banner" class="ui-toast success" role="status" aria-live="polite">
    <i class="fas fa-check-circle" aria-hidden="true"></i>
    <span>Report cancelled successfully.</span>
</div>
<?php elseif ($show_cancel_error): ?>
<div id="toast-banner" class="ui-toast error" role="alert" aria-live="assertive">
    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
    <span>Failed to cancel report. Please try again.</span>
</div>
<?php endif; ?>
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
    const toast = document.getElementById('toast-banner');
    if (toast) {
        const container = document.getElementById('residentToastContainer');
        if (container) {
            container.appendChild(toast);
        }
        setTimeout(() => toast.classList.add('hide'), 2600);
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }

function renderTimeline(status) {
    let activeStep = 1;
    let isRejected = false;
    
    let normalizeStatus = status.toLowerCase();
    if (["resolved", "completed", "closed"].includes(normalizeStatus)) activeStep = 3;
    else if (["review", "in progress", "processing", "under review"].includes(normalizeStatus)) activeStep = 2;
    else if (["rejected", "cancelled"].includes(normalizeStatus)) { activeStep = 3; isRejected = true; }
    
    let color = isRejected ? 'text-red-500 border-red-500 bg-red-50' : 'text-blue-600 border-blue-600 bg-blue-50';
    
    return `
    <div class="mt-4 px-2 mb-2 w-full">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-200 -z-10"></div>
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 ${isRejected ? 'bg-red-500' : 'bg-blue-600'} -z-10 transition-all" style="width: ${(activeStep - 1) * 50}%"></div>
            
            <div class="flex flex-col items-center">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 1 ? color : 'border-gray-300 bg-white'} flex items-center justify-center text-xs font-bold ring-2 ring-white z-10">
                    ${activeStep > 1 && !isRejected ? '<i class="fas fa-check"></i>' : '1'}
                </div>
                <span class="text-[10px] mt-1 font-semibold ${activeStep >= 1 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">Submitted</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 2 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-xs font-bold ring-2 ring-white z-10">
                    ${activeStep > 2 && !isRejected ? '<i class="fas fa-check"></i>' : '2'}
                </div>
                <span class="text-[10px] mt-1 font-semibold ${activeStep >= 2 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">Review</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 3 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-xs font-bold ring-2 ring-white z-10">
                    ${isRejected ? '<i class="fas fa-times text-red-500"></i>' : (activeStep >= 3 ? '<i class="fas fa-check"></i>' : '3')}
                </div>
                <span class="text-[10px] mt-1 font-semibold ${activeStep >= 3 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">${isRejected ? 'Rejected' : 'Resolved'}</span>
            </div>
        </div>
    </div>`;
}

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
                            <div class="mobile-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition cursor-pointer" onclick="window.location.href='report-details.php?id=${report.id}'">
                                <div>
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-bold text-gray-800 line-clamp-2">${escapeHTML(report.type)}</h3>
                                        <span class="text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap mobile-card-badge ${statusClass}">${escapeHTML(report.status)}</span>
                                    </div>
                                    <div class="text-sm text-gray-500 mb-3 line-clamp-2">${escapeHTML(report.description || report.type)}</div>
                                </div>
                                ${renderTimeline(report.status)}
                                <div class="flex items-center justify-between text-xs text-gray-400 mt-5 pt-3 border-t border-gray-50">
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <span>${coordsHtml}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="far fa-clock mr-1"></i>
                                        <span>${formattedDate}</span>
                                    </div>
                                </div>
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