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
        $stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $resident = $stmt->fetch();
        if ($resident) {
            $resident_id = $resident['id'];
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
$stmt = $pdo->prepare("SELECT document_type, purpose, date_requested, status, remarks, details FROM document_requests WHERE resident_id = ? ORDER BY date_requested DESC");
$stmt->execute([$resident_id]);
$requests = $stmt->fetchAll();
?>
<div class="max-w-6xl mx-auto px-4 py-8 mt-4">
    <h1 class="text-3xl font-bold mb-8 text-blue-700">My Requests</h1>
    
    /* Global grid and card styles are now in resident.css */
    </style>

    <div id="requests-grid-container" class="mobile-grid-2col">
    <?php if (empty($requests)): ?>
        <div class="col-span-full py-20 text-center text-gray-400">
            <i class="fas fa-folder-open text-5xl mb-4 block opacity-20"></i>
            <p>You have not submitted any requests yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $req): ?>
            <?php 
                $details = json_decode($req['details'], true) ?? [];
                $status = $req['status'] ?? 'Pending';
                $statusClass = 'bg-gray-100 text-gray-700';
                if (in_array($status, ['Approved', 'Ready for Pickup', 'Completed'])) $statusClass = 'bg-green-100 text-green-700';
                elseif ($status === 'Rejected') $statusClass = 'bg-red-100 text-red-700';
                elseif ($status === 'Processing') $statusClass = 'bg-yellow-100 text-yellow-700';
            ?>
            <div class="standard-card">
                <div class="request-card-header">
                    <div class="request-type-icon doc-icon"><i class="fas fa-file-invoice"></i></div>
                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                </div>
                <div class="request-card-body">
                    <h3><?= htmlspecialchars($req['document_type']) ?></h3>
                    <p class="purpose"><?= htmlspecialchars($req['purpose']) ?></p>
                </div>
                <div class="request-meta">
                    <div class="meta-item">
                        <i class="far fa-calendar-alt"></i>
                        <span>Requested:</span> <?= date('M d, Y', strtotime($req['date_requested'])) ?>
                    </div>
                    <?php if (!empty($req['remarks'])): ?>
                        <div class="remarks-box">
                            <strong>Note:</strong> <?= htmlspecialchars($req['remarks']) ?>
                        </div>
                    <?php endif; ?>
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
        grid.innerHTML = `<div class="col-span-full py-20 text-center text-gray-400">
            <i class="fas fa-folder-open text-5xl mb-4 block opacity-20"></i>
            <p>You have not submitted any requests yet.</p>
        </div>`;
        return;
    }

    // Document requests
    docRequests.forEach(function(req) {
        let status = req.status || 'Pending';
        let statusClass = 'bg-gray-100 text-gray-700';
        if (["Approved","Ready for Pickup","Completed"].includes(status)) statusClass = 'bg-green-100 text-green-700';
        else if (status === 'Rejected') statusClass = 'bg-red-100 text-red-700';
        else if (status === 'Processing') statusClass = 'bg-yellow-100 text-yellow-700';
        
        cards += `<div class="standard-card">
            <div class="request-card-header">
                <div class="request-type-icon doc-icon"><i class="fas fa-file-invoice"></i></div>
                <span class="status-badge ${statusClass}">${status}</span>
            </div>
            <div class="request-card-body">
                <h3>${req.document_type}</h3>
                <p class="purpose">${req.purpose}</p>
            </div>
            <div class="request-meta">
                <div class="meta-item">
                    <i class="far fa-calendar-alt"></i>
                    <span>Requested:</span> ${new Date(req.date_requested).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                </div>
                ${req.remarks ? `<div class="remarks-box"><strong>Note:</strong> ${req.remarks}</div>` : ''}
            </div>
        </div>`;
    });

    // Business transactions
    bizRequests.forEach(function(req) {
        let status = req.status || 'PENDING';
        let statusClass = 'bg-gray-100 text-gray-700';
        if (["APPROVED","Completed","Ready for Pickup"].includes(status)) statusClass = 'bg-green-100 text-green-700';
        else if (["REJECTED","Rejected"].includes(status)) statusClass = 'bg-red-100 text-red-700';
        else if (["PROCESSING","Processing"].includes(status)) statusClass = 'bg-yellow-100 text-yellow-700';
        
        cards += `<div class="standard-card">
            <div class="request-card-header">
                <div class="request-type-icon biz-icon"><i class="fas fa-briefcase"></i></div>
                <span class="status-badge ${statusClass}">${status}</span>
            </div>
            <div class="request-card-body">
                <h3>${req.business_name}</h3>
                <p class="purpose">${req.transaction_type}</p>
            </div>
            <div class="request-meta">
                <div class="meta-item">
                    <i class="far fa-calendar-alt"></i>
                    <span>Requested:</span> ${new Date(req.application_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                </div>
                ${req.remarks ? `<div class="remarks-box"><strong>Note:</strong> ${req.remarks}</div>` : ''}
            </div>
        </div>`;
    });
    grid.innerHTML = cards;
}
setInterval(function() {
    fetch('partials/fetch-live-updates.php')
        .then(res => res.json())
        .then(data => {
            if (data.doc_requests && data.biz_requests) {
                renderRequestsTable(data.doc_requests, data.biz_requests);
            }
        });
}, 5000);
</script>
<?php require_once 'partials/footer.php'; ?> 