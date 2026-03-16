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
<div class="max-w-4xl mx-auto bg-white rounded-lg shadow p-8 mt-8">
    <h1 class="text-2xl font-bold mb-6 text-blue-700">My Requests</h1>
    <div id="requests-table-container">
    <?php if (empty($requests)): ?>
        <div class="text-gray-600">You have not submitted any document requests yet.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="requests-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Document/Business</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Purpose/Transaction</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date Requested</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="requests-table-body">
                <?php foreach ($requests as $req): ?>
                <?php 
                    $details = json_decode($req['details'], true) ?? [];
                    $application_type = $details['application_type'] ?? '';
                    $urgency = $details['urgency'] ?? '';
                ?>
                <tr>
                    <td class="px-4 py-2 text-sm text-gray-800">Document</td>
                    <td class="px-4 py-2 text-sm text-gray-800"><?php echo htmlspecialchars($req['document_type']); ?></td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($req['purpose']); ?></td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($req['date_requested'])); ?></td>
                    <td class="px-4 py-2 text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            <?php
                                if ($req['status'] === 'Approved' || $req['status'] === 'Ready for Pickup' || $req['status'] === 'Completed') echo 'bg-green-100 text-green-800';
                                elseif ($req['status'] === 'Rejected') echo 'bg-red-100 text-red-800';
                                elseif ($req['status'] === 'Processing') echo 'bg-yellow-100 text-yellow-800';
                                else echo 'bg-gray-100 text-gray-800';
                            ?>">
                            <?php echo htmlspecialchars($req['status']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-600">
                        <?php if ($application_type): ?>
                            <div><strong>Type:</strong> <?php echo htmlspecialchars($application_type); ?></div>
                        <?php endif; ?>
                        <?php if ($urgency): ?>
                            <div><strong>Urgency:</strong> <?php echo htmlspecialchars($urgency); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($req['remarks'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    </div>
</div>
<script>
function renderRequestsTable(docRequests, bizRequests) {
    let tbody = document.getElementById('requests-table-body');
    if (!tbody) return;
    let rows = '';
    // Document requests
    docRequests.forEach(function(req) {
        let details = {};
        try { details = JSON.parse(req.details || '{}'); } catch(e) {}
        let application_type = details.application_type || '';
        let urgency = details.urgency || '';
        let statusClass = 'bg-gray-100 text-gray-800';
        if (["Approved","Ready for Pickup","Completed"].includes(req.status)) statusClass = 'bg-green-100 text-green-800';
        else if (req.status === 'Rejected') statusClass = 'bg-red-100 text-red-800';
        else if (req.status === 'Processing') statusClass = 'bg-yellow-100 text-yellow-800';
        rows += `<tr>
            <td class='px-4 py-2 text-sm text-gray-800'>Document</td>
            <td class='px-4 py-2 text-sm text-gray-800'>${req.document_type}</td>
            <td class='px-4 py-2 text-sm text-gray-600'>${req.purpose}</td>
            <td class='px-4 py-2 text-sm text-gray-600'>${new Date(req.date_requested).toLocaleString()}</td>
            <td class='px-4 py-2 text-sm'><span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}'>${req.status}</span></td>
            <td class='px-4 py-2 text-sm text-gray-600'>${application_type ? `<div><strong>Type:</strong> ${application_type}</div>` : ''}${urgency ? `<div><strong>Urgency:</strong> ${urgency}</div>` : ''}</td>
            <td class='px-4 py-2 text-sm text-gray-500'>${req.remarks || ''}</td>
        </tr>`;
    });
    // Business transactions
    bizRequests.forEach(function(req) {
        let statusClass = 'bg-gray-100 text-gray-800';
        if (["APPROVED","Completed","Ready for Pickup"].includes(req.status)) statusClass = 'bg-green-100 text-green-800';
        else if (["REJECTED","Rejected"].includes(req.status)) statusClass = 'bg-red-100 text-red-800';
        else if (["PROCESSING","Processing"].includes(req.status)) statusClass = 'bg-yellow-100 text-yellow-800';
        rows += `<tr>
            <td class='px-4 py-2 text-sm text-purple-800'>Business</td>
            <td class='px-4 py-2 text-sm text-gray-800'>${req.business_name}</td>
            <td class='px-4 py-2 text-sm text-gray-600'>${req.transaction_type}</td>
            <td class='px-4 py-2 text-sm text-gray-600'>${new Date(req.application_date).toLocaleString()}</td>
            <td class='px-4 py-2 text-sm'><span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}'>${req.status}</span></td>
            <td class='px-4 py-2 text-sm text-gray-600'>${req.business_type}</td>
            <td class='px-4 py-2 text-sm text-gray-500'>${req.remarks || ''}</td>
        </tr>`;
    });
    tbody.innerHTML = rows;
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