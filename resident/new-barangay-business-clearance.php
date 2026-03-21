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

$page_title = "Business Permit Application";
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

<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <!-- The exact form structure expected by Admin, styled beautifully for the Resident -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8" x-data="{ proof: 'owned' }">
        <div class="bg-gradient-to-r from-indigo-700 to-indigo-900 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Application for Barangay Business Clearance</h1>
            <p class="opacity-80 text-sm mt-1">Fill out the comprehensive business permit application below.</p>
        </div>

        <div class="p-8 md:p-12">
            <form action="partials/submit-business-permit.php" method="POST" id="business-form" class="space-y-10">
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">

                <!-- Applicant Information -->
                <div>
                    <h3 class="text-xl font-bold text-gray-800 border-b-2 border-indigo-100 pb-2 mb-6 flex items-center gap-2"><i class="fas fa-user-tie text-indigo-500"></i> I. Taxpayer / Applicant Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                        <div class="md:col-span-2 text-sm text-gray-500 mb-2">
                            <i class="fas fa-info-circle"></i> Basic information is auto-filled from your resident profile.
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Name of Taxpayer (Applicant)</label>
                            <input type="text" value="<?= $full_name ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium" readonly>
                            <input type="hidden" name="taxpayer_name" value="<?= $full_name ?>">
                            <input type="hidden" name="applicant_name" value="<?= $full_name ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Position / Role in Business</label>
                            <input type="text" name="applicant_position" required placeholder="e.g. Owner, Manager" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Taxpayer Address</label>
                            <input type="text" name="taxpayer_address" value="<?= htmlspecialchars($resident['address']) ?>" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Telephone No.</label>
                            <input type="tel" name="taxpayer_tel_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Fax No.</label>
                            <input type="tel" name="taxpayer_fax_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Capital (₱)</label>
                            <input type="number" name="capital" step="0.01" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Taxpayer Barangay No.</label>
                            <input type="text" name="taxpayer_barangay_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Business Details -->
                <div>
                    <h3 class="text-xl font-bold text-gray-800 border-b-2 border-indigo-100 pb-2 mb-6 flex items-center gap-2"><i class="fas fa-store text-indigo-500"></i> II. Business Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 rounded-xl border border-gray-100">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Business Trade Name <span class="text-red-500">*</span></label>
                            <input type="text" name="business_trade_name" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Business Telephone No.</label>
                            <input type="tel" name="business_tel_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        
                        <!-- Commercial Address Group -->
                        <div class="md:col-span-2 mt-4">
                            <label class="block text-sm font-bold text-gray-800 mb-3 border-b pb-1">Commercial Address</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Building Name</label>
                                    <input type="text" name="comm_address_building" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">No.</label>
                                    <input type="text" name="comm_address_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Street</label>
                                    <input type="text" name="comm_address_street" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Barangay No.</label>
                                    <input type="text" name="comm_address_barangay_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                            </div>
                        </div>

                        <!-- Registration & Line of Business Group -->
                        <div class="md:col-span-2 mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">DTI Reg. No.</label>
                                <input type="text" name="dti_reg_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">SEC Reg. No.</label>
                                <input type="text" name="sec_reg_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">No. of Employees</label>
                                <input type="number" name="num_employees" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Main Line of Business</label>
                                <input type="text" name="main_line_business" required placeholder="e.g. Sari-Sari Store" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Other Line of Business</label>
                                <input type="text" name="other_line_business" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Main Products / Services</label>
                                <input type="text" name="main_products_services" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Others (Products/Services)</label>
                                <input type="text" name="other_products_services" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ownership Details Group -->
                <div>
                    <h3 class="text-xl font-bold text-gray-800 border-b-2 border-indigo-100 pb-2 mb-6 flex items-center gap-2"><i class="fas fa-file-contract text-indigo-500"></i> III. Ownership Details</h3>
                    <div class="bg-indigo-50/50 p-6 rounded-xl border border-indigo-100 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-800 mb-3">Ownership Type</label>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 border border-gray-200 rounded-lg flex-1 hover:border-indigo-300 transition">
                                        <input type="radio" name="ownership_type" value="single" checked class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="text-gray-700 font-medium tracking-wide">Single</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 border border-gray-200 rounded-lg flex-1 hover:border-indigo-300 transition">
                                        <input type="radio" name="ownership_type" value="partnership" class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="text-gray-700 font-medium tracking-wide">Partnership</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 border border-gray-200 rounded-lg flex-1 hover:border-indigo-300 transition">
                                        <input type="radio" name="ownership_type" value="corporation" class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="text-gray-700 font-medium tracking-wide">Corporation</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-800 mb-3">Proof of Ownership</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 border border-gray-200 rounded-lg flex-1 hover:border-indigo-300 transition">
                                        <input type="radio" name="proof_of_ownership" value="owned" x-model="proof" class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="text-gray-700 font-medium tracking-wide">Owned</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 border border-gray-200 rounded-lg flex-1 hover:border-indigo-300 transition">
                                        <input type="radio" name="proof_of_ownership" value="leased" x-model="proof" class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="text-gray-700 font-medium tracking-wide">Leased</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Dynamic Proof Field -->
                            <div class="col-span-full">
                                <div x-show="proof === 'owned'" x-cloak x-transition>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Registered Name (if Owned)</label>
                                    <input type="text" name="proof_owned_reg_name" class="w-full md:w-1/2 px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                                <div x-show="proof === 'leased'" x-cloak x-transition>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lessor's Name (if Leased)</label>
                                    <input type="text" name="proof_leased_lessor_name" class="w-full md:w-1/2 px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-indigo-200 border-dashed">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Rent per Month (₱)</label>
                                <input type="number" name="rent_per_month" step="0.01" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Area in Sq. Meter</label>
                                <input type="number" name="area_sq_meter" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Real Property Tax Receipt No.</label>
                                <input type="text" name="real_property_tax_receipt_no" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Insurances -->
                <div>
                    <h3 class="text-xl font-bold text-gray-800 border-b-2 border-indigo-100 pb-2 mb-6 flex items-center gap-2"><i class="fas fa-shield-alt text-indigo-500"></i> IV. Compliances & Insurance</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 rounded-xl border border-gray-100">
                        <div class="flex flex-col gap-4">
                            <label class="flex items-start gap-3 w-max cursor-pointer">
                                <input type="checkbox" name="has_barangay_clearance" value="1" class="mt-1 w-5 h-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <div>
                                    <span class="block text-gray-800 font-bold">Has valid Barangay Clearance</span>
                                    <span class="text-xs text-gray-500">Check if your personal clearance is updated</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 w-max cursor-pointer">
                                <input type="checkbox" name="has_public_liability_insurance" value="1" class="mt-1 w-5 h-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <div>
                                    <span class="block text-gray-800 font-bold">Has Public Liability Insurance</span>
                                    <span class="text-xs text-gray-500">Check if your business is insured</span>
                                </div>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Insurance Company Name</label>
                                <input type="text" name="insurance_company" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Insurance</label>
                                <input type="date" name="insurance_date" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-8 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-500/30 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('business-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const formData = new FormData(this);
    
    fetch('partials/submit-business-permit.php', {
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
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
        }
    })
    .catch(err => {
        console.error(err);
        alert("A network error occurred.");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
