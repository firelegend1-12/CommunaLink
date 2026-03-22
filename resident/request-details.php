<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}
require_role('resident');

$req_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$req_id) {
    redirect_to('my-requests.php');
}

require_once '../config/database.php';

$resident_id = $_SESSION['resident_id'] ?? 0;
if (!$resident_id && isset($_SESSION['user_id'])) {
    $resident_id = get_resident_id($pdo, $_SESSION['user_id']) ?? 0;
    if ($resident_id) {
        $_SESSION['resident_id'] = $resident_id;
    }
}

$stmt = $pdo->prepare("SELECT * FROM document_requests WHERE id = ? AND (requested_by_user_id = ? OR (requested_by_user_id IS NULL AND resident_id = ?))");
$stmt->execute([$req_id, $_SESSION['user_id'], $resident_id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    redirect_to('my-requests.php');
}

$page_title = "Document Request Details";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

$status = $req['status'] ?? 'Pending';
$date_requested = date('F j, Y, g:i a', strtotime($req['date_requested']));
$details = json_decode($req['details'], true) ?? [];

require_once 'partials/header.php';

// Generate Requirements Checklist
$requirements = [];
$doc_type = $req['document_type'];
if (stripos($doc_type, 'Business') !== false) {
    $requirements = [
        "Community Tax Certificate (Cedula)",
        "DTI / SEC / CDA Registration",
        "Contract of Lease or TCT of Property",
        "Sketch of Business Location"
    ];
} elseif (stripos($doc_type, 'Indigency') !== false) {
    $requirements = [
        "Valid Government ID",
        "Proof of No Property (if applicable)"
    ];
} elseif (stripos($doc_type, 'Residency') !== false) {
    $requirements = [
        "Valid Government ID (address matching the barangay)",
        "Recent Utility Bill (water/electricity)"
    ];
} else {
    // Default Barangay Clearance
    $requirements = [
        "Community Tax Certificate (Cedula)",
        "Valid Identification Card"
    ];
}
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="my-requests.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to My Requests
        </a>
        
        <?php if(strtolower($status) === 'pending'): ?>
            <button class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg font-semibold border border-red-200 transition-colors shadow-sm" onclick="cancelDocumentRequest(<?= (int) $req['id'] ?>)">
                <i class="fas fa-times-circle mr-1"></i> Cancel Request
            </button>
        <?php else: ?>
            <button class="bg-gray-100 text-gray-700 hover:bg-gray-200 px-4 py-2 rounded-lg font-semibold border border-gray-300 transition-colors shadow-sm" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print Copy
            </button>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 px-8 py-6 text-white flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars($doc_type) ?></h1>
                <p class="opacity-80 text-sm"><i class="far fa-clock mr-1"></i> Requested on <?= $date_requested ?></p>
            </div>
             <span class="px-4 py-1.5 rounded-full text-sm font-bold bg-white/20 border border-white/30 backdrop-blur-sm shadow-sm uppercase tracking-wider">
                <?= htmlspecialchars($status) ?>
            </span>
        </div>

        <div class="p-8">
            <div id="timeline-container" class="mb-10 pb-8 border-b border-gray-100"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Application Details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-file-invoice text-teal-500 mr-2"></i>Application Details</h3>
                    <div class="bg-gray-50 rounded-xl border border-gray-100 p-5 space-y-4">
                        <div>
                            <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Purpose</span>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($req['purpose'] ?? 'N/A') ?></p>
                        </div>
                        
                        <?php if(!empty($details['urgency'])): ?>
                            <div>
                                <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Urgency</span>
                                <p class="text-gray-800 font-medium capitalize"><?= htmlspecialchars($details['urgency']) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Render dynamic fields if present -->
                        <?php foreach(['business_name' => 'Business Name', 'monthly_income' => 'Monthly Income', 'length_of_residence' => 'Length of Residence'] as $key => $label): ?>
                            <?php if(!empty($details[$key])): ?>
                                <div>
                                    <span class="text-xs text-gray-400 uppercase font-bold tracking-wider"><?= $label ?></span>
                                    <p class="text-gray-800 font-medium"><?= htmlspecialchars($details[$key]) ?></p>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if(!empty($details['additional_notes'])): ?>
                            <div>
                                <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Additional Notes</span>
                                <p class="text-gray-600 text-sm italic"><?= nl2br(htmlspecialchars($details['additional_notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Requirements Checklist -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-tasks text-teal-500 mr-2"></i>Claiming Requirements</h3>
                    <div class="bg-teal-50/50 rounded-xl border border-teal-100 p-5">
                        <p class="text-sm text-teal-800 mb-4 font-medium"><i class="fas fa-info-circle mr-1"></i> Please prepare the following documents before claiming your request:</p>
                        
                        <ul class="space-y-3">
                            <?php foreach($requirements as $req_item): ?>
                                <li class="flex items-start text-gray-700">
                                    <i class="far fa-check-circle text-teal-600 mt-1 mr-3"></i>
                                    <span class="leading-tight"><?= htmlspecialchars($req_item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if (intval($req['price']) > 0): ?>
                        <div class="mt-6 bg-yellow-50 rounded-xl border border-yellow-200 p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-gray-800">Processing Fee</h4>
                                <p class="text-xs text-gray-500">Payable at the barangay hall.</p>
                            </div>
                            <span class="text-2xl font-bold text-yellow-600">₱<?= number_format($req['price'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barangay Remarks -->
            <div class="mt-10 bg-gray-50 border border-gray-200 rounded-xl p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-2"><i class="fas fa-user-tie text-gray-600 mr-2"></i>Barangay Remarks</h3>
                <p class="text-gray-600 italic">
                    <?php if(empty($req['remarks'])): ?>
                        No official remarks added yet. Check back later for updates from the barangay staff.
                    <?php else: ?>
                        <?= htmlspecialchars($req['remarks']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rawStatus = "<?= addslashes($status) ?>";
    const timelineContainer = document.getElementById('timeline-container');
    if(timelineContainer) {
        timelineContainer.innerHTML = renderTimeline(rawStatus);
    }
});

function renderTimeline(status) {
    let activeStep = 1;
    let isRejected = false;
    
    let normalizeStatus = status.toLowerCase();
    if (["approved", "ready for pickup", "completed", "ready"].includes(normalizeStatus)) activeStep = 3;
    else if (["processing", "under review"].includes(normalizeStatus)) activeStep = 2;
    else if (["rejected", "cancelled"].includes(normalizeStatus)) { activeStep = 3; isRejected = true; }
    
    let color = isRejected ? 'text-red-500 border-red-500 bg-red-50' : 'text-teal-600 border-teal-600 bg-teal-50';
    
    return `
    <div class="w-full px-4 md:px-12 pt-4">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1.5 bg-gray-200 rounded-full -z-10"></div>
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1.5 ${isRejected ? 'bg-red-500' : 'bg-teal-600'} rounded-full -z-10 transition-all duration-1000 ease-out" style="width: ${(activeStep - 1) * 50}%"></div>
            
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 ${activeStep >= 1 ? color : 'border-gray-300 bg-gray-50 text-gray-400'} flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 transition-colors duration-500 bg-white">
                    ${activeStep > 1 && !isRejected ? '<i class="fas fa-check text-teal-600"></i>' : '1'}
                </div>
                <span class="text-xs md:text-sm mt-2 font-bold ${activeStep >= 1 ? (isRejected ? 'text-red-600' : 'text-teal-800') : 'text-gray-400'} uppercase tracking-wide">Submitted</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 ${activeStep >= 2 ? color : 'border-gray-200 bg-gray-50 text-gray-400'} flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 transition-colors duration-500 delay-300 bg-white">
                    ${activeStep > 2 && !isRejected ? '<i class="fas fa-check text-teal-600"></i>' : '2'}
                </div>
                <span class="text-xs md:text-sm mt-2 font-bold ${activeStep >= 2 ? (isRejected ? 'text-red-600' : 'text-teal-800') : 'text-gray-400'} uppercase tracking-wide">Processing</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 ${activeStep >= 3 ? color : 'border-gray-200 bg-gray-50 text-gray-400'} flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 transition-colors duration-500 delay-700 bg-white">
                    ${isRejected ? '<i class="fas fa-times text-red-600"></i>' : (activeStep >= 3 ? '<i class="fas fa-check text-teal-600"></i>' : '3')}
                </div>
                <span class="text-xs md:text-sm mt-2 font-bold ${activeStep >= 3 ? (isRejected ? 'text-red-600' : 'text-teal-800') : 'text-gray-400'} uppercase tracking-wide">${isRejected ? 'Rejected' : 'Ready'}</span>
            </div>
        </div>
    </div>`;
}

function cancelDocumentRequest(requestId) {
    const reason = prompt('Please provide a reason for cancellation (required):');
    if (reason === null) return;

    const trimmedReason = reason.trim();
    if (!trimmedReason) {
        alert('Cancellation reason is required.');
        return;
    }

    if (!confirm('Are you sure you want to cancel this request?')) {
        return;
    }

    fetch('partials/cancel-document-request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'id=' + encodeURIComponent(String(requestId)) + '&reason=' + encodeURIComponent(trimmedReason)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'my-requests.php?cancelled=1';
        } else {
            alert(data.error || 'Failed to cancel request.');
        }
    })
    .catch(() => {
        alert('Failed to cancel request. Please try again.');
    });
}
</script>

<?php require_once 'partials/footer.php'; ?>
