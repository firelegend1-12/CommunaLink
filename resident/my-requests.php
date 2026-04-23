<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'My Document Requests';
$show_cancel_success = isset($_GET['cancelled']) && $_GET['cancelled'] === '1';
$show_cancel_error = isset($_GET['cancel_error']) && $_GET['cancel_error'] === '1';
require_once 'partials/header.php';

// Get logged-in resident's ID
$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    // Try to get resident ID from user_id if resident_id is not set
    if (isset($_SESSION['user_id'])) {
        require_once '../config/database.php';
        $resident_id = get_resident_id($pdo, $_SESSION['user_id']);
        if ($resident_id) {
            $_SESSION['resident_id'] = $resident_id; // Set it for future use
        }
    }
    
    if (!$resident_id) {
        echo '<div class="max-w-xl mx-auto mt-10 text-red-600">Unable to determine your resident ID. Please contact the administrator.</div>';
        require_once 'partials/footer.php';
        exit;
    }
}

require_once '../config/database.php';
// Get document requests
$stmtDoc = $pdo->prepare("SELECT id, document_type, purpose, date_requested, status, remarks, details FROM document_requests WHERE resident_id = ? ORDER BY date_requested DESC");
$stmtDoc->execute([$resident_id]);
$docRequests = $stmtDoc->fetchAll();

// Get business transactions
$stmtBiz = $pdo->prepare("SELECT id, business_name, business_type, transaction_type, application_date, status, remarks FROM business_transactions WHERE resident_id = ? ORDER BY application_date DESC");
$stmtBiz->execute([$resident_id]);
$bizRequests = $stmtBiz->fetchAll();
?>
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
<div class="max-w-4xl mx-auto px-4 py-8 mt-4">
    <h1 class="text-3xl font-bold mb-8 text-blue-700">My Requests</h1>

    <div id="requests-grid-container" class="responsive-card-grid">
        <!-- Rendered by JS on load -->
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
});

function renderTimeline(status) {
    let activeStep = 1;
    let isRejected = false;
    
    let normalizeStatus = status.toLowerCase();
    if (["approved", "ready for pickup", "ready", "completed"].includes(normalizeStatus)) activeStep = 3;
    if (normalizeStatus === "completed") activeStep = 4;
    else if (normalizeStatus === "processing") activeStep = 2;
    else if (["rejected", "cancelled"].includes(normalizeStatus)) { activeStep = 4; isRejected = true; }
    
    let color = isRejected ? 'text-red-500 border-red-500 bg-red-50' : 'text-blue-600 border-blue-600 bg-blue-50';
    
    return `
    <div class="mt-6 px-1">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-3 transform -translate-y-1/2 w-full h-1 bg-gray-100 -z-10 rounded-full"></div>
            <div class="absolute left-0 top-3 transform -translate-y-1/2 h-1 ${isRejected ? 'bg-red-500' : 'bg-blue-600'} -z-10 transition-all rounded-full" style="width: ${(activeStep - 1) * 33.3}%"></div>
            
            <div class="flex flex-col items-center w-1/4">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 1 ? color : 'border-gray-300 bg-white'} flex items-center justify-center text-[10px] font-bold ring-4 ring-white z-10">
                    ${activeStep > 1 && !isRejected ? '<i class="fas fa-check"></i>' : '1'}
                </div>
                <span class="text-[9px] mt-2 font-bold uppercase tracking-tighter w-full text-center leading-none ${activeStep >= 1 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">Submitted</span>
            </div>
            <div class="flex flex-col items-center w-1/4">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 2 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-[10px] font-bold ring-4 ring-white z-10">
                    ${activeStep > 2 && !isRejected ? '<i class="fas fa-check"></i>' : '2'}
                </div>
                <span class="text-[9px] mt-2 font-bold uppercase tracking-tighter w-full text-center leading-none ${activeStep >= 2 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">Processing</span>
            </div>
            <div class="flex flex-col items-center w-1/4">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 3 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-[10px] font-bold ring-4 ring-white z-10">
                    ${activeStep > 3 && !isRejected ? '<i class="fas fa-check"></i>' : '3'}
                </div>
                <span class="text-[9px] mt-2 font-bold uppercase tracking-tighter w-full text-center leading-none ${activeStep >= 3 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">Ready</span>
            </div>
            <div class="flex flex-col items-center w-1/4">
                <div class="w-6 h-6 rounded-full border-2 ${activeStep >= 4 ? color : 'border-gray-300 bg-white text-gray-400'} flex items-center justify-center text-[10px] font-bold ring-4 ring-white z-10">
                    ${isRejected ? '<i class="fas fa-times text-red-500"></i>' : (activeStep >= 4 ? '<i class="fas fa-check"></i>' : '4')}
                </div>
                <span class="text-[9px] mt-2 font-bold uppercase tracking-tighter w-full text-center leading-none ${activeStep >= 4 ? (isRejected ? 'text-red-600' : 'text-blue-700') : 'text-gray-400'}">${isRejected ? 'Rejected' : 'Done'}</span>
            </div>
        </div>
    </div>`;
}

function renderRequestsTable(docRequests, bizRequests) {
    let grid = document.getElementById('requests-grid-container');
    if (!grid) return;
    let cards = '';
    
    if (docRequests.length === 0 && bizRequests.length === 0) {
        cards = `<div class="col-span-full py-20 text-center text-gray-400">
            <i class="fas fa-folder-open text-5xl mb-4 block opacity-20"></i>
            <p>You have not submitted any requests yet.</p>
        </div>`;
    } else {
        // Document requests
        docRequests.forEach(function(req) {
            let status = req.status || 'Pending';
            let badgeClass = 'pending';
            if (["Approved","Ready for Pickup","Completed"].includes(status)) badgeClass = 'approved';
            else if (['Rejected', 'Cancelled'].includes(status)) badgeClass = 'rejected';
            else if (status === 'Processing') badgeClass = 'processing';
            
            cards += `<div class="mobile-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition cursor-pointer group" onclick="window.location.href='request-details.php?id=${req.id}'">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-gray-800 line-clamp-2 group-hover:text-blue-600 transition-colors">${escapeHTML(req.document_type)}</h3>
                        <span class="text-[10px] font-extrabold px-2 py-1 rounded-md whitespace-nowrap mobile-card-badge ${badgeClass} uppercase tracking-tight">${escapeHTML(status)}</span>
                    </div>
                    <div class="text-xs text-gray-500 mb-3 line-clamp-1">${escapeHTML(req.purpose || 'Document Request')} ${req.remarks ? ' - ' + escapeHTML(req.remarks) : ''}</div>
                </div>
                ${renderTimeline(status)}
                <div class="flex items-center justify-between text-[10px] text-gray-400 mt-5 pt-3 border-t border-gray-50">
                    <div class="flex items-center">
                        <i class="far fa-clock mr-1"></i>
                        <span>${new Date(req.date_requested).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                    </div>
                    <span class="text-blue-500 font-bold group-hover:underline">View Details <i class="fas fa-chevron-right ml-1"></i></span>
                </div>
            </div>`;
        });

        // Business transactions
        bizRequests.forEach(function(req) {
            let status = req.status || 'Pending';
            let badgeClass = 'pending';
            if (["Approved","Completed","Ready for Pickup"].includes(status)) badgeClass = 'approved';
            else if (["Rejected","Cancelled"].includes(status)) badgeClass = 'rejected';
            else if (["Processing"].includes(status)) badgeClass = 'processing';
            const businessLabel = req.remarks === 'Barangay Business Clearance'
                ? 'Business Clearance'
                : (req.transaction_type || 'Business Transaction');
            
            cards += `<div class="mobile-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition cursor-pointer group" onclick="window.location.href='business-details.php?id=${req.id}'">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-gray-800 line-clamp-2 group-hover:text-blue-600 transition-colors">${escapeHTML(req.business_name)}</h3>
                        <span class="text-[10px] font-extrabold px-2 py-1 rounded-md whitespace-nowrap mobile-card-badge ${badgeClass} uppercase tracking-tight">${escapeHTML(status)}</span>
                    </div>
                    <div class="text-xs text-gray-500 mb-3 line-clamp-1">${escapeHTML(businessLabel)}${req.remarks && req.remarks !== 'Barangay Business Clearance' ? ' - ' + escapeHTML(req.remarks) : ''}</div>
                </div>
                ${renderTimeline(status)}
                <div class="flex items-center justify-between text-[10px] text-gray-400 mt-5 pt-3 border-t border-gray-50">
                    <div class="flex items-center">
                        <i class="far fa-clock mr-1"></i>
                        <span>${new Date(req.application_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                    </div>
                    <span class="text-blue-500 font-bold group-hover:underline">View Details <i class="fas fa-chevron-right ml-1"></i></span>
                </div>
            </div>`;
        });
    }
    
    // Diff to prevent flicker
    if (grid.innerHTML !== cards) {
        grid.innerHTML = cards;
    }
}

function fetchUpdates() {
    fetch('partials/fetch-live-updates.php')
        .then(res => res.json())
        .then(data => {
            if (data.doc_requests && data.biz_requests) {
                renderRequestsTable(data.doc_requests, data.biz_requests);
            }
        })
        .catch(err => console.error('Error fetching updates:', err));
}

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

// Initial fetch
fetchUpdates();
// Polling
setInterval(fetchUpdates, 15000);
</script>
<?php require_once 'partials/footer.php'; ?> 