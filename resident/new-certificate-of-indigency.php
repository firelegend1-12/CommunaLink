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

$page_title = "Certificate of Indigency Request";
$user_id = $_SESSION['user_id'];

// Get Resident Details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile. Please update your profile first.";
    redirect_to('account.php');
}

$full_name = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);
$civil_status = htmlspecialchars($resident['civil_status'] ?? 'Single');

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
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Certificate of Indigency</h1>
            <p class="opacity-80 text-sm mt-1">Review the certificate details below before submitting your request.</p>
        </div>

        <div class="p-8 md:p-12">
            <form action="partials/submit-indigency.php" method="POST" id="indigency-form">
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" name="recipient_name" value="<?= $full_name ?>">
                <input type="hidden" name="civil_status" value="<?= $civil_status ?>">

                <!-- Header Section -->
                <div class="text-center mb-10">
                    <p class="text-gray-600 font-medium">REPUBLIC OF THE PHILIPPINES</p>
                    <p class="text-gray-600 font-medium">Iloilo City</p>
                    <p class="font-bold text-gray-800 text-lg">Barangay Pakiad Oton</p>
                    <h2 class="text-xl font-bold mt-2 text-gray-800">Office of the Punong Barangay</h2>
                </div>

                <h1 class="text-center text-3xl font-extrabold text-teal-800 uppercase my-8 tracking-widest border-b-2 border-teal-100 pb-4">Certificate of Indigency</h1>

                <!-- Main Body -->
                <div class="mb-8 space-y-8 bg-gray-50/50 p-8 rounded-xl border border-gray-200 shadow-inner">
                    <p class="font-bold text-gray-800 text-lg">TO WHOM IT MAY CONCERN:</p>
                    
                    <p class="text-justify indent-8 leading-loose text-gray-700 text-lg">
                        This is to CERTIFY that Mr./Ms. <strong class="text-black underline underline-offset-4 px-2"><?= $full_name ?></strong>, 
                        of legal age, <strong class="text-black underline underline-offset-4 px-2"><?= $civil_status ?></strong>, 
                        Filipino Citizen and a resident of Barangay Pakiad Oton, Iloilo City,
                        belongs to the Indigent Families of this barangay having an annual income not exceeding the Regional Poverty Threshold (RPT) of Php 169, 824.00 per anum as determined by the National Economic Development Authority (NEDA).
                    </p>
                    
                    <p class="text-justify indent-8 leading-loose text-gray-700 text-lg">
                        This CERTIFICATION is issued upon the request of the above-mentioned individual for whatever legal purpose/s it may best serve him or her.
                    </p>
                    
                    <div class="pt-6 text-gray-500 italic text-sm text-center">
                        Note: Date of issuance and official signatures will be applied by the Barangay Administration upon approval.
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-8">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-bold shadow-lg shadow-teal-500/30 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('indigency-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const formData = new FormData(this);
    
    fetch('partials/submit-indigency.php', {
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
