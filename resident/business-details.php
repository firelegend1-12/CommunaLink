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

$trans_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$trans_id) {
    redirect_to('my-requests.php');
}

require_once '../config/database.php';
$cancel_csrf_token = csrf_token();

// Fetch business transaction
$stmt = $pdo->prepare("SELECT * FROM business_transactions WHERE id = ? AND resident_id = ?");
// Note: We use resident_id from session which should be set in header/auth
$resident_id = $_SESSION['resident_id'] ?? 0;
$stmt->execute([$trans_id, $resident_id]);
$trans = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trans) {
    redirect_to('my-requests.php');
}

// Try to fetch extended permit details if it exists
$permit = null;
if (!empty($trans['permit_id'])) {
    $stmtPermit = $pdo->prepare("SELECT * FROM business_permits WHERE id = ? LIMIT 1");
    $stmtPermit->execute([(int) $trans['permit_id']]);
    $permit = $stmtPermit->fetch(PDO::FETCH_ASSOC);
}

if (!$permit) {
    $stmtPermit = $pdo->prepare("SELECT * FROM business_permits WHERE business_trade_name = ? AND taxpayer_name = ? LIMIT 1");
    $stmtPermit->execute([$trans['business_name'], $trans['owner_name']]);
    $permit = $stmtPermit->fetch(PDO::FETCH_ASSOC);
}

$page_title = "Business Permit Details";
$status = $trans['status'] ?? 'Pending';
$date_applied = date('F j, Y', strtotime($trans['application_date']));

require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="my-requests.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to My Requests
        </a>
        
        <?php if($status === 'Pending'): ?>
            <button class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg font-semibold border border-red-200 transition-colors shadow-sm" onclick="cancelBusinessApplication(<?= (int) $trans['id'] ?>)">
                <i class="fas fa-times-circle mr-1"></i> Cancel Application
            </button>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-indigo-600 to-blue-700 px-8 py-6 text-white flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars($trans['business_name']) ?></h1>
                <p class="opacity-80 text-sm"><i class="far fa-clock mr-1"></i> Applied on <?= $date_applied ?></p>
            </div>
             <span class="px-4 py-1.5 rounded-full text-sm font-bold bg-white/20 border border-white/30 backdrop-blur-sm shadow-sm uppercase tracking-wider">
                <?= htmlspecialchars($status) ?>
            </span>
        </div>

        <div class="p-8">
            <!-- Timeline -->
            <div id="timeline-container" class="mb-10 pb-8 border-b border-gray-100">
                <?php
                $activeStep = 1;
                $isRejected = false;
                $normalizeStatus = strtolower($status);
                if (in_array($normalizeStatus, ["approved", "ready for pickup", "completed", "ready"])) $activeStep = 3;
                if ($normalizeStatus === "completed") $activeStep = 4;
                else if ($normalizeStatus === "processing") $activeStep = 2;
                else if (in_array($normalizeStatus, ["rejected", "cancelled"])) { $activeStep = 4; $isRejected = true; }
                
                $color = $isRejected ? 'text-red-500 border-red-500 bg-red-50' : 'text-blue-600 border-blue-600 bg-blue-50';
                $lineColor = $isRejected ? 'bg-red-500' : 'bg-blue-600';
                ?>
                <div class="w-full px-2 pt-4">
                    <div class="flex items-center justify-between relative">
                        <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1.5 bg-gray-100 rounded-full -z-10"></div>
                        <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1.5 <?= $lineColor ?> rounded-full -z-10 transition-all duration-1000" style="width: <?= ($activeStep - 1) * 33.3 ?>%"></div>
                        
                        <?php 
                        $steps = ["Submitted", "Processing", "Ready", $isRejected ? "Rejected" : "Completed"];
                        for($i=1; $i<=4; $i++): 
                            $stepActive = $activeStep >= $i;
                            $dotColor = $stepActive ? $color : 'border-gray-200 bg-gray-50 text-gray-400';
                        ?>
                        <div class="flex flex-col items-center w-1/4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 <?= $dotColor ?> flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 bg-white">
                                <?php 
                                if($isRejected && $i === 4) echo '<i class="fas fa-times text-red-600"></i>';
                                elseif($activeStep > $i && !$isRejected) echo '<i class="fas fa-check text-blue-600"></i>';
                                else echo $i;
                                ?>
                            </div>
                            <span class="text-[10px] md:text-xs mt-2 font-bold <?= $stepActive ? ($isRejected ? 'text-red-600' : 'text-blue-800') : 'text-gray-400' ?> uppercase tracking-tighter text-center"><?= $steps[$i-1] ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Business Information -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-store text-indigo-500 mr-2"></i>Business Information</h3>
                    <div class="bg-gray-50 rounded-xl border border-gray-100 p-5 space-y-4 text-sm">
                        <div>
                            <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Line of Business</span>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($trans['business_type'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Transaction Type</span>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($trans['transaction_type'] ?? 'Permit Application') ?></p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Business Address</span>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($trans['address'] ?? 'N/A') ?></p>
                        </div>
                        
                        <?php if(!empty($permit['permit_number'])): ?>
                            <div class="pt-2 border-t border-gray-200">
                                <span class="text-xs text-indigo-500 uppercase font-black tracking-widest">Official Permit No.</span>
                                <p class="text-xl font-black text-indigo-700"><?= htmlspecialchars($permit['permit_number']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Claiming Requirements -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-clipboard-check text-indigo-500 mr-2"></i>Requirements for Pickup</h3>
                    <div class="bg-indigo-50/50 rounded-xl border border-indigo-100 p-5">
                        <ul class="space-y-3 text-sm">
                            <li class="flex items-start text-gray-700">
                                <i class="far fa-check-circle text-indigo-600 mt-1 mr-3"></i>
                                <span class="leading-tight font-medium">Valid Government Issued ID</span>
                            </li>
                            <li class="flex items-start text-gray-700">
                                <i class="far fa-check-circle text-indigo-600 mt-1 mr-3"></i>
                                <span class="leading-tight font-medium">Original Receipt of Payment</span>
                            </li>
                            <li class="flex items-start text-gray-700">
                                <i class="far fa-check-circle text-indigo-600 mt-1 mr-3"></i>
                                <span class="leading-tight font-medium">DTI/SEC Registration (Original for verification)</span>
                            </li>
                        </ul>
                        
                        <div class="mt-6 pt-4 border-t border-indigo-100 text-xs text-gray-500 italic">
                            Claim your permit at the Barangay Hall, Monday to Friday, 8:00 AM - 5:00 PM.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Official Remarks -->
            <div class="mt-10 bg-gray-50 border border-gray-200 rounded-xl p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-2"><i class="fas fa-comment-dots text-gray-600 mr-2"></i>Official Remarks</h3>
                <p class="text-gray-600 italic text-sm">
                    <?php if(empty($trans['remarks'])): ?>
                        Your application is currently being evaluated. Official remarks will appear here once processed.
                    <?php else: ?>
                        <?= htmlspecialchars($trans['remarks']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function cancelBusinessApplication(transactionId) {
    residentPrompt('Please provide a reason for cancellation:', function(reason) {
        if (reason === null) {
            return;
        }

        residentConfirm('Are you sure you want to cancel this application?', function() {
            const csrfToken = '<?php echo htmlspecialchars($cancel_csrf_token, ENT_QUOTES); ?>';
            fetch('partials/cancel-business-application.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'id=' + encodeURIComponent(String(transactionId)) + '&reason=' + encodeURIComponent(reason) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'my-requests.php?cancelled=1';
                } else {
                    window.location.href = 'my-requests.php?cancel_error=1';
                }
            })
            .catch(() => {
                window.location.href = 'my-requests.php?cancel_error=1';
            });
        }, {
            confirmText: 'Cancel Application',
            danger: true
        });
    }, {
        placeholder: 'Enter cancellation reason',
        confirmText: 'Continue',
        required: true,
        requiredMessage: 'Cancellation reason is required.'
    });
}
</script>

<?php require_once 'partials/footer.php'; ?>
