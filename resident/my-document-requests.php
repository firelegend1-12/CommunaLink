<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');

$page_title = 'My Document Requests';
$show_cancel_success = isset($_GET['cancelled']) && $_GET['cancelled'] === '1';
$show_cancel_error = isset($_GET['cancel_error']) && $_GET['cancel_error'] === '1';

// Get logged-in resident's ID
$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    if (isset($_SESSION['user_id'])) {
        require_once '../config/database.php';
        $resident_id = get_resident_id($pdo, $_SESSION['user_id']);
        if ($resident_id) {
            $_SESSION['resident_id'] = $resident_id;
        }
    }
    if (!$resident_id) {
        echo '<div class="max-w-xl mx-auto mt-10 text-red-600">Unable to determine your resident ID. Please contact the administrator.</div>';
        require_once 'partials/footer.php';
        exit;
    }
}

require_once '../config/database.php';

// Fetch document requests only
$stmtDoc = $pdo->prepare("SELECT id, document_type, purpose, date_requested, status, remarks, admin_notes, details FROM document_requests WHERE resident_id = ? ORDER BY date_requested DESC");
$stmtDoc->execute([$resident_id]);
$docRequests = $stmtDoc->fetchAll();

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
    .requests-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .mobile-card-badge.pending { background: #fef3c7; color: #92400e; }
    .mobile-card-badge.approved { background: #dbeafe; color: #1e40af; }
    .mobile-card-badge.completed { background: #dcfce7; color: #166534; }
    .mobile-card-badge.rejected { background: #fee2e2; color: #991b1b; }
</style>

<?php if ($show_cancel_success): ?>
<div id="toast-banner" class="ui-toast success" role="status" aria-live="polite">
    <i class="fas fa-check-circle" aria-hidden="true"></i>
    <span>Request cancelled successfully.</span>
</div>
<?php elseif ($show_cancel_error): ?>
<div id="toast-banner" class="ui-toast error" role="alert" aria-live="assertive">
    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
    <span>Failed to cancel request. Please try again.</span>
</div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <h2>My Document Requests</h2>
        <a href="barangay-services.php" class="submit-btn">Request New Document</a>
    </div>

    <div class="requests-list" id="requests-list">
        <div style="text-align: center; padding: 30px; color: #666;">Loading requests...</div>
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
        let isCancelled = false;

        let normalizeStatus = status.toLowerCase();
        if (["approved"].includes(normalizeStatus)) activeStep = 2;
        else if (["completed"].includes(normalizeStatus)) activeStep = 3;
        else if (["rejected"].includes(normalizeStatus)) { activeStep = 2; isRejected = true; }
        else if (["cancelled"].includes(normalizeStatus)) { activeStep = 1; isCancelled = true; }

        let color = isRejected ? 'text-red-500 border-red-500 bg-red-50' : 'text-blue-600 border-blue-600 bg-blue-50';

        return `
        <div class="mt-4 px-2 mb-2 w-full">
            <div class="flex items-center justify-between relative">
                <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-200 -z-10 rounded-full"></div>
                <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 ${isRejected ? 'bg-red-500' : 'bg-blue-600'} -z-10 transition-all rounded-full" style="width: ${(activeStep - 1) * 50}%"></div>

                <div class="flex flex-col items-center w-1/3">
                    <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 1 ? color : 'border-gray-300 bg-white'} flex items-center justify-center text-[10px] font-bold ring-2 ring-white z-10">
                        ${activeStep > 1 && !isRejected && !isCancelled ? '<i class="fas fa-check"></i>' : (isCancelled ? '<i class="fas fa-times"></i>' : '1')}
                    </div>
                    <span class="text-[10px] mt-1 font-semibold ${activeStep >= 1 ? (isRejected || isCancelled ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">${isCancelled ? 'Cancelled' : 'Submitted'}</span>
                </div>
                <div class="flex flex-col items-center w-1/3">
                    <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 2 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-[10px] font-bold ring-2 ring-white z-10">
                        ${activeStep > 2 && !isRejected ? '<i class="fas fa-check"></i>' : (isRejected ? '<i class="fas fa-times"></i>' : '2')}
                    </div>
                    <span class="text-[10px] mt-1 font-semibold ${activeStep >= 2 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">${isRejected ? 'Rejected' : 'Approved'}</span>
                </div>
                <div class="flex flex-col items-center w-1/3">
                    <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 3 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-[10px] font-bold ring-2 ring-white z-10">
                        ${activeStep >= 3 ? '<i class="fas fa-check"></i>' : '3'}
                    </div>
                    <span class="text-[10px] mt-1 font-semibold ${activeStep >= 3 ? 'text-blue-700' : 'text-gray-400'}">Completed</span>
                </div>
            </div>
        </div>`;
    }

    function renderDocumentRequests(requests) {
        const list = document.getElementById('requests-list');
        if (!list) return;
        let html = '';

        if (requests.length > 0) {
            requests.forEach(req => {
                const status = req.status || 'Pending';
                let badgeClass = 'pending';
                if (["Approved","Completed"].includes(status)) badgeClass = 'approved';
                else if (["Rejected", "Cancelled"].includes(status)) badgeClass = 'rejected';

                const formattedDate = new Date(req.date_requested).toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric'
                });

                html += `
                    <div class="mobile-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition cursor-pointer" onclick="window.location.href='request-details.php?id=${req.id}'">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-gray-800 line-clamp-2">${escapeHTML(req.document_type)}</h3>
                                <span class="text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap mobile-card-badge ${badgeClass}">${escapeHTML(status)}</span>
                            </div>
                            <div class="text-sm text-gray-500 mb-3 line-clamp-2">${escapeHTML(req.purpose || req.document_type)}</div>
                        </div>
                        ${renderTimeline(status)}
                        <div class="flex items-center justify-between text-xs text-gray-400 mt-5 pt-3 border-t border-gray-50">
                            <div class="flex items-center">
                                <i class="far fa-clock mr-1"></i>
                                <span>${formattedDate}</span>
                            </div>
                            <span class="text-blue-500 font-bold">View Details <i class="fas fa-chevron-right ml-1"></i></span>
                        </div>
                    </div>
                `;
            });

            if (list.innerHTML !== html) {
                list.innerHTML = html;
            }
        } else {
            list.innerHTML = '<div style="text-align: center; padding: 30px; color: #666; background: #fff; border-radius: 12px;">You have not submitted any document requests yet.</div>';
        }
    }

    function loadRequests() {
        fetch('partials/fetch-live-updates.php')
            .then(response => response.json())
            .then(data => {
                if (data.doc_requests) {
                    renderDocumentRequests(data.doc_requests);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Initial load
    loadRequests();
    // Polling every 5 seconds for status updates
    setInterval(loadRequests, 5000);
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
