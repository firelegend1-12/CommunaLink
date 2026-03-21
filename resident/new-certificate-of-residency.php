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

$page_title = "Certificate of Residency Request";
$user_id = $_SESSION['user_id'];

// Get Resident Details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile.";
    redirect_to('account.php');
}

$full_name = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);

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
        <div class="bg-gradient-to-r from-purple-700 to-purple-900 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Certificate of Residency</h1>
            <p class="opacity-80 text-sm mt-1">Please provide the details required for your certificate.</p>
        </div>

        <div class="p-8 md:p-12">
            <form action="partials/submit-residency.php" method="POST" id="residency-form" class="space-y-8">
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">

                <!-- Applicant Details -->
                <div class="bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">I. Applicant Details</h3>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Name of Applicant</label>
                        <input type="text" name="applicant_name" value="<?= $full_name ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium" readonly>
                    </div>
                </div>

                <!-- Property and Residency Details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">II. Property and Residency Details <span class="text-red-500">*</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Property is owned by</label>
                            <input type="text" name="property_owner" required placeholder="Name of owner or 'Self'" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Sitio / Purok / Zone / Building No.</label>
                            <input type="text" name="sitio" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">District</label>
                            <input type="text" name="district" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Residency Status -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">III. Residency Status</h3>
                    <p class="text-sm text-gray-600 mb-4">Please select any applicable statuses below (based on records of this office):</p>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="status[]" value="low income bracket" class="h-5 w-5 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <span class="text-gray-700 font-medium">Low Income Bracket</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="status[]" value="informal settler" class="h-5 w-5 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <span class="text-gray-700 font-medium">Informal Settler</span>
                        </label>
                    </div>
                </div>

                <!-- Purpose -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">IV. Purpose</h3>
                    <div class="bg-purple-50 p-5 rounded-xl border border-purple-100 text-purple-800">
                        <p class="text-sm leading-relaxed font-medium">
                            <i class="fas fa-info-circle mr-2"></i> This certification is being issued intended for compliance with the requirements of the <strong>iKonek ELECTRIFICATION PROGRAM OF MAYOR JERRY P. TRENAS</strong> and <strong>MORE ELECTRIC AND POWER CORP.</strong>
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-8 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold shadow-lg shadow-purple-500/30 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('residency-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const formData = new FormData(this);
    
    fetch('partials/submit-residency.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            window.location.href = 'my-requests.php?success=1';
        } else {
            alert("Error: " + data.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
        }
    })
    .catch(err => {
        console.error(err);
        alert("A network error occurred.");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
