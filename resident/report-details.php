<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/env_loader.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}
require_role('resident');

$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$report_id) {
    redirect_to('my-reports.php');
}

require_once '../config/database.php';

$stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ? AND resident_user_id = ?");
$stmt->execute([$report_id, $_SESSION['user_id']]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    redirect_to('my-reports.php');
}

$page_title = "Incident Report Details";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

$status = $report['status'] ?? 'Pending';
$statusClass = strtolower(str_replace(' ', '-', $status));
$reported_date = date('F j, Y, g:i a', strtotime($report['reported_at']));

require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="my-reports.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to My Reports
        </a>
        
        <?php if(strtolower($status) === 'pending'): ?>
            <button onclick="cancelReport(<?= $report['id'] ?>)" class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg font-semibold border border-red-200 transition-colors shadow-sm">
                <i class="fas fa-times-circle mr-1"></i> Cancel Report
            </button>
        <?php else: ?>
            <button class="bg-gray-100 text-gray-700 hover:bg-gray-200 px-4 py-2 rounded-lg font-semibold border border-gray-300 transition-colors shadow-sm" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print Copy
            </button>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-6 text-white flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars($report['type']) ?></h1>
                <p class="opacity-80 text-sm"><i class="far fa-clock mr-1"></i> Reported on <?= $reported_date ?></p>
            </div>
            <span class="px-4 py-1.5 rounded-full text-sm font-bold bg-white/20 border border-white/30 backdrop-blur-sm shadow-sm uppercase tracking-wider">
                <?= htmlspecialchars($status) ?>
            </span>
        </div>

        <div class="p-8">
            <div id="timeline-container" class="mb-10 pb-8 border-b border-gray-100"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-align-left text-blue-500 mr-2"></i>Description</h3>
                    <p class="text-gray-600 leading-relaxed whitespace-pre-wrap bg-gray-50 p-4 rounded-xl border border-gray-100"><?= htmlspecialchars($report['description']) ?></p>

                    <?php if (!empty($report['media_path'])): ?>
                        <h3 class="text-lg font-bold text-gray-800 mt-8 mb-4 border-b pb-2"><i class="fas fa-paperclip text-blue-500 mr-2"></i>Attachments</h3>
                        <div class="bg-gray-50 p-2 rounded-xl border border-gray-100 inline-block">
                            <?php 
                            $ext = strtolower(pathinfo($report['media_path'], PATHINFO_EXTENSION));
                            if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <a href="../<?= htmlspecialchars($report['media_path']) ?>" target="_blank">
                                    <img src="../<?= htmlspecialchars($report['media_path']) ?>" alt="Evidence" class="max-w-full h-auto max-h-64 object-cover rounded-lg shadow-sm hover:opacity-90 transition">
                                </a>
                            <?php elseif(in_array($ext, ['mp4', 'mov', 'avi'])): ?>
                                <video controls class="max-w-full h-auto max-h-64 rounded-lg shadow-sm">
                                    <source src="../<?= htmlspecialchars($report['media_path']) ?>" type="video/<?= $ext === 'mov' ? 'quicktime' : $ext ?>">
                                    Your browser does not support the video tag.
                                </video>
                            <?php else: ?>
                                <a href="../<?= htmlspecialchars($report['media_path']) ?>" target="_blank" class="text-blue-600 hover:underline flex items-center gap-2 p-2">
                                    <i class="fas fa-file-download text-xl"></i> View Attached File
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-map-marked-alt text-blue-500 mr-2"></i>Location</h3>
                    <p class="text-gray-600 mb-3 text-sm"><i class="fas fa-location-dot mr-1 text-red-500"></i> <?= htmlspecialchars($report['location']) ?></p>
                    
                    <?php if ($report['latitude'] && $report['longitude']): ?>
                        <div id="map" class="w-full h-64 rounded-xl border-2 border-gray-200 shadow-inner overflow-hidden relative">
                            <div class="absolute inset-0 flex items-center justify-center bg-gray-50 text-gray-400">
                                <i class="fas fa-spinner fa-spin text-2xl"></i><span class="ml-2">Loading Map...</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="w-full h-64 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center bg-gray-50 text-gray-400">
                            <div class="text-center">
                                <i class="fas fa-map-marker-slash text-3xl mb-2"></i>
                                <p>No exact coordinates provided.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-10 bg-blue-50/50 border border-blue-100 rounded-xl p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-2"><i class="fas fa-clipboard-check text-blue-600 mr-2"></i>Official Remarks / Resolution</h3>
                <p class="text-gray-600 italic">
                    <?php if(empty($report['admin_remarks'])): ?>
                        No official remarks added yet. The barangay administration will update this report once action is taken.
                    <?php else: ?>
                        <?= htmlspecialchars($report['admin_remarks']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Timeline Logic
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
    if (["resolved", "completed", "closed"].includes(normalizeStatus)) activeStep = 3;
    else if (["review", "in progress", "processing", "under review"].includes(normalizeStatus)) activeStep = 2;
    else if (["rejected", "cancelled"].includes(normalizeStatus)) { activeStep = 3; isRejected = true; }
    
    let color = isRejected ? 'text-red-500 border-red-500 bg-red-50' : 'text-blue-600 border-blue-600 bg-blue-50';
    
    return `
    <div class="w-full px-4 md:px-12 pt-4">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1.5 bg-gray-200 rounded-full -z-10"></div>
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1.5 ${isRejected ? 'bg-red-500' : 'bg-blue-600'} rounded-full -z-10 transition-all duration-1000 ease-out" style="width: ${(activeStep - 1) * 50}%"></div>
            
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 ${activeStep >= 1 ? color : 'border-gray-300 bg-gray-50 text-gray-400'} flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 transition-colors duration-500 bg-white">
                    ${activeStep > 1 && !isRejected ? '<i class="fas fa-check text-blue-600"></i>' : '1'}
                </div>
                <span class="text-xs md:text-sm mt-2 font-bold ${activeStep >= 1 ? (isRejected ? 'text-red-600' : 'text-blue-800') : 'text-gray-400'} uppercase tracking-wide">Submitted</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 ${activeStep >= 2 ? color : 'border-gray-200 bg-gray-50 text-gray-400'} flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 transition-colors duration-500 delay-300 bg-white">
                    ${activeStep > 2 && !isRejected ? '<i class="fas fa-check text-blue-600"></i>' : '2'}
                </div>
                <span class="text-xs md:text-sm mt-2 font-bold ${activeStep >= 2 ? (isRejected ? 'text-red-600' : 'text-blue-800') : 'text-gray-400'} uppercase tracking-wide">Under Review</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border-4 ${activeStep >= 3 ? color : 'border-gray-200 bg-gray-50 text-gray-400'} flex items-center justify-center text-sm md:text-base font-bold shadow-md z-10 transition-colors duration-500 delay-700 bg-white">
                    ${isRejected ? '<i class="fas fa-times text-red-600"></i>' : (activeStep >= 3 ? '<i class="fas fa-check text-blue-600"></i>' : '3')}
                </div>
                <span class="text-xs md:text-sm mt-2 font-bold ${activeStep >= 3 ? (isRejected ? 'text-red-600' : 'text-blue-800') : 'text-gray-400'} uppercase tracking-wide">${isRejected ? 'Rejected' : 'Resolved'}</span>
            </div>
        </div>
    </div>`;
}

// Google Maps Setup
window.initMap = async function() {
    const lat = <?= json_encode($report['latitude']) ?>;
    const lng = <?= json_encode($report['longitude']) ?>;
    
    if(!lat || !lng) return;

    const { Map } = await google.maps.importLibrary("maps");
    const { Marker } = await google.maps.importLibrary("marker");

    const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
    const mapDiv = document.getElementById('map');
    
    const map = new Map(mapDiv, {
        center: pos,
        zoom: 17,
        mapTypeId: 'satellite',
        disableDefaultUI: true,
        zoomControl: true,
    });

    new Marker({
        position: pos,
        map: map,
        title: "Incident Location",
        animation: google.maps.Animation.DROP
    });
};

function cancelReport(id) {
    residentPrompt('Please provide a reason for cancellation:', function(reason) {
        if (reason === null) {
            return;
        }

        residentConfirm('Are you sure you want to cancel and delete this incident report? This action cannot be undone.', function() {
            fetch('partials/cancel-incident-report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'id=' + encodeURIComponent(String(id)) + '&reason=' + encodeURIComponent(reason)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'my-reports.php?cancelled=1';
                } else {
                    window.location.href = 'my-reports.php?cancel_error=1';
                }
            })
            .catch(() => {
                window.location.href = 'my-reports.php?cancel_error=1';
            });
        }, {
            title: 'Cancel Incident Report',
            confirmText: 'Cancel Report',
            cancelText: 'Keep Report',
            danger: true
        });
    }, {
        title: 'Incident Cancellation Reason',
        placeholder: 'Enter cancellation reason',
        confirmText: 'Continue',
        cancelText: 'Back',
        required: true,
        requiredMessage: 'Cancellation reason is required.'
    });
}
</script>

<?php if ($report['latitude'] && $report['longitude']): ?>
<script>
    const script = document.createElement('script');
    const apiKey = "<?php echo function_exists('env') ? env('GOOGLE_MAPS_API_KEY', 'AIzaSyDSePOKkt_W5bY7YsYaEJrMoSRWxTMGnuI') : 'AIzaSyDSePOKkt_W5bY7YsYaEJrMoSRWxTMGnuI'; ?>";
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&loading=async&callback=initMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
</script>
<?php endif; ?>

<?php require_once 'partials/footer.php'; ?>
