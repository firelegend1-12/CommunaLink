<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'My Document Requests';
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
<div class="max-w-4xl mx-auto px-4 py-8 mt-4">
    <h1 class="text-3xl font-bold mb-8 text-blue-700">My Requests</h1>

    <div id="requests-grid-container" class="space-y-4">
    <?php if (empty($docRequests) && empty($bizRequests)): ?>
        <div class="py-20 text-center text-gray-400">
            <i class="fas fa-folder-open text-5xl mb-4 block opacity-20"></i>
            <p>You have not submitted any requests yet.</p>
        </div>
    <?php else: ?>
        <?php 
        // Helper to get badge class
        function getBadgeClass($status) {
            $status = strtolower($status);
            if (in_array($status, ['approved', 'ready for pickup', 'completed'])) return 'approved';
            if ($status === 'rejected') return 'rejected';
            if ($status === 'processing') return 'processing';
            return 'pending';
        }

        // Render Document Requests
        foreach ($docRequests as $req): 
            $badgeClass = getBadgeClass($req['status'] ?? 'Pending');
        ?>
            <div class="mobile-card">
                <div class="mobile-card-header">
                    <h3 class="mobile-card-title"><?= htmlspecialchars($req['document_type']) ?></h3>
                    <span class="mobile-card-badge <?= $badgeClass ?>"><?= htmlspecialchars($req['status'] ?? 'Pending') ?></span>
                </div>
                <div class="mobile-card-desc"><?= htmlspecialchars($req['purpose']) ?> <?= !empty($req['remarks']) ? ' - ' . htmlspecialchars($req['remarks']) : '' ?></div>
                <div class="mobile-card-meta">
                    <div class="mobile-card-meta-item">
                        <i class="far fa-clock"></i>
                        <span><?= date('M d, Y', strtotime($req['date_requested'])) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php 
        // Render Business Transactions
        foreach ($bizRequests as $req): 
            $badgeClass = getBadgeClass($req['status'] ?? 'Pending');
        ?>
            <div class="mobile-card">
                <div class="mobile-card-header">
                    <h3 class="mobile-card-title"><?= htmlspecialchars($req['business_name']) ?></h3>
                    <span class="mobile-card-badge <?= $badgeClass ?>"><?= htmlspecialchars($req['status'] ?? 'Pending') ?></span>
                </div>
                <div class="mobile-card-desc"><?= htmlspecialchars($req['transaction_type'] ?? 'Business Transaction') ?> <?= !empty($req['remarks']) ? ' - ' . htmlspecialchars($req['remarks']) : '' ?></div>
                <div class="mobile-card-meta">
                    <div class="mobile-card-meta-item">
                        <i class="far fa-clock"></i>
                        <span><?= date('M d, Y', strtotime($req['application_date'])) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>
<script>
function renderRequestsTable(docRequests, bizRequests) {
    let grid = document.getElementById('requests-grid-container');
    if (!grid) return;
    let cards = '';
    
    if (docRequests.length === 0 && bizRequests.length === 0) {
        cards = `<div class="py-20 text-center text-gray-400">
            <i class="fas fa-folder-open text-5xl mb-4 block opacity-20"></i>
            <p>You have not submitted any requests yet.</p>
        </div>`;
    } else {
        // Document requests
        docRequests.forEach(function(req) {
            let status = req.status || 'Pending';
            let badgeClass = 'pending';
            let sLower = status.toLowerCase();
            if (["approved","ready for pickup","completed"].includes(sLower)) badgeClass = 'approved';
            else if (sLower === 'rejected') badgeClass = 'rejected';
            else if (sLower === 'processing') badgeClass = 'processing';
            
            cards += `<div class="mobile-card">
                <div class="mobile-card-header">
                    <h3 class="mobile-card-title">${escapeHTML(req.document_type)}</h3>
                    <span class="mobile-card-badge ${badgeClass}">${status}</span>
                </div>
                <div class="mobile-card-desc">${escapeHTML(req.purpose || 'Document Request')} ${req.remarks ? ' - ' + escapeHTML(req.remarks) : ''}</div>
                <div class="mobile-card-meta">
                    <div class="mobile-card-meta-item">
                        <i class="far fa-clock"></i>
                        <span>${new Date(req.date_requested).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                    </div>
                </div>
            </div>`;
        });

        // Business transactions
        bizRequests.forEach(function(req) {
            let status = req.status || 'PENDING';
            let badgeClass = 'pending';
            let sLower = status.toLowerCase();
            if (["approved","completed","ready for pickup"].includes(sLower)) badgeClass = 'approved';
            else if (sLower === 'rejected') badgeClass = 'rejected';
            else if (sLower === 'processing') badgeClass = 'processing';
            
            cards += `<div class="mobile-card">
                <div class="mobile-card-header">
                    <h3 class="mobile-card-title">${escapeHTML(req.business_name)}</h3>
                    <span class="mobile-card-badge ${badgeClass}">${status}</span>
                </div>
                <div class="mobile-card-desc">${escapeHTML(req.transaction_type || 'Business Transaction')} ${req.remarks ? ' - ' + escapeHTML(req.remarks) : ''}</div>
                <div class="mobile-card-meta">
                    <div class="mobile-card-meta-item">
                        <i class="far fa-clock"></i>
                        <span>${new Date(req.application_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                    </div>
                </div>
            </div>`;
        });
    }
    
    // Diff update
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
setInterval(fetchUpdates, 5000);
</script>
<?php require_once 'partials/footer.php'; ?> 