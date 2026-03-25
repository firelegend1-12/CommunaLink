<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}
require_role('resident');

$page_title = "Application for Barangay Clearance";
$user_id = $_SESSION['user_id'];

// Get Resident Details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile. Please update your profile first.";
    redirect_to('account.php');
}

// Calculate age
$age = '';
if (!empty($resident['date_of_birth'])) {
    $birthDate = new DateTime($resident['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <!-- The exact form structure expected by Admin, styled beautifully for the Resident -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Application for Barangay Clearance</h1>
            <p class="opacity-80 text-sm mt-1">Please completely fill out the required information below.</p>
        </div>

        <div class="p-8">
            <form action="partials/submit-clearance.php" method="POST" id="clearance-form" class="space-y-8">
                <!-- Hidden Resident ID to mirror admin handler -->
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">

                <!-- Applicant Data (Pre-Filled) -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="fas fa-user text-blue-500 mr-2"></i>Applicant's Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Last Name</label>
                            <input type="text" value="<?= htmlspecialchars($resident['last_name']) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">First Name</label>
                            <input type="text" value="<?= htmlspecialchars($resident['first_name']) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Middle Initial</label>
                            <input type="text" value="<?= htmlspecialchars($resident['middle_initial'] ?? '') ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Complete Address</label>
                            <input type="text" value="<?= htmlspecialchars($resident['address']) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Sex</label>
                            <input type="text" value="<?= htmlspecialchars($resident['gender']) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                            <input type="text" value="<?= htmlspecialchars($resident['date_of_birth']) ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Age</label>
                            <input type="text" value="<?= $age ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Civil Status</label>
                            <input type="text" value="<?= htmlspecialchars($resident['civil_status'] ?? '') ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Place of Birth</label>
                            <input type="text" value="<?= htmlspecialchars($resident['place_of_birth'] ?? '') ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Occupation</label>
                            <input type="text" value="<?= htmlspecialchars($resident['occupation'] ?? '') ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600" readonly>
                        </div>
                    </div>
                </div>

                <!-- Application Details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="fas fa-edit text-blue-500 mr-2"></i>Application Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Application Type <span class="text-red-500">*</span></label>
                            <div class="flex gap-6 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer bg-blue-50 px-4 py-2 rounded-lg border border-blue-100 w-1/2">
                                    <input type="radio" name="application_type" value="New" checked class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                    <span class="font-medium text-blue-800">New Clearance</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer bg-blue-50 px-4 py-2 rounded-lg border border-blue-100 w-1/2">
                                    <input type="radio" name="application_type" value="Renewal" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                    <span class="font-medium text-blue-800">Renewal</span>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Precinct No. (Optional)</label>
                            <input type="text" name="precinct_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Resident Since (Year)</label>
                            <input type="text" name="resident_since" placeholder="e.g. 2015" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">If employed, Name of Company (Optional)</label>
                            <input type="text" name="company_name" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Purpose of Clearance <span class="text-red-500">*</span></label>
                            <textarea name="purpose" required rows="2" placeholder="e.g. For Employment" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"></textarea>
                        </div>
                    </div>
                </div>

                <!-- References -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="fas fa-users text-blue-500 mr-2"></i>Personal References</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Reference 1 (Not a relative)</label>
                            <input type="text" name="reference_1" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Reference 2 (Not a relative)</label>
                            <input type="text" name="reference_2" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">References Telephone / Contact No.</label>
                            <input type="tel" name="reference_tel_no" class="w-full md:w-1/2 px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                    </div>
                </div>

                <!-- CTC (Cedula) -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="fas fa-id-card text-blue-500 mr-2"></i>Community Tax Certificate (Cedula)</h3>
                    <p class="text-sm text-gray-500 mb-4">If you already have a Cedula for this year, please provide the details. Otherwise, you can request one at the barangay hall during claiming.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">CTC No.</label>
                            <input type="text" name="ctc_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Issued At (Place)</label>
                            <input type="text" name="ctc_issued_at" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Issued On (Date)</label>
                            <input type="date" name="ctc_issued_on" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-8 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('clearance-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const formData = new FormData(this);
    
    fetch('partials/submit-clearance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            window.location.href = 'my-requests.php?success=1';
        } else {
            residentShowToast("Error: " + data.error, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
        }
    })
    .catch(err => {
        console.error(err);
        residentShowToast('A network error occurred.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
