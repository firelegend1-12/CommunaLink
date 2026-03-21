<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'Document Request Wizard';
require_once 'partials/header.php';

// Get logged-in resident's ID
$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    if (isset($_SESSION['user_id'])) {
        require_once '../config/database.php';
        $stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $resident = $stmt->fetch();
        if ($resident) {
            $resident_id = $resident['id'];
            $_SESSION['resident_id'] = $resident_id;
        }
    }
    if (!$resident_id) {
        echo '<div class="max-w-xl mx-auto mt-10 text-red-600">Unable to determine your resident ID.</div>';
        require_once 'partials/footer.php';
        exit;
    }
}

// Get resident details
require_once '../config/database.php';
$stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
$stmt->execute([$resident_id]);
$resident = $stmt->fetch();

if (!$resident) {
    echo '<div class="max-w-xl mx-auto mt-10 text-red-600">Resident profile not found.</div>';
    require_once 'partials/footer.php';
    exit;
}

$fullName = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);
$address = htmlspecialchars($resident['address']);
$contact = htmlspecialchars($resident['contact_no'] ?? 'N/A');
$civil = htmlspecialchars($resident['civil_status']);
?>

<div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-lg p-8 mt-8 border border-gray-100">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center">
            <div class="bg-blue-100 text-blue-700 rounded-full p-4 mr-4">
                <i class="fas fa-file-contract flex text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">Request a Document</h1>
        </div>
        <a href="barangay-services.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Services
        </a>
    </div>

    <!-- Wizard Steps Indicator -->
    <div class="mb-10 w-full">
        <div class="flex justify-between items-center w-full max-w-2xl mx-auto relative px-4">
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-200 -z-10 px-6"></div>
            <div id="progress-bar" class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-blue-600 -z-10 transition-all duration-300" style="width: 0%;"></div>
            
            <div class="step-indicator active flex flex-col items-center" data-step="1">
                <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shadow-md ring-4 ring-white transition z-10"><i class="fas fa-list-alt"></i></div>
                <span class="mt-3 text-sm font-semibold text-blue-700">Select Document</span>
            </div>
            <div class="step-indicator flex flex-col items-center" data-step="2">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold shadow-sm ring-4 ring-white transition z-10"><i class="fas fa-edit"></i></div>
                <span class="mt-3 text-sm font-medium text-gray-500">Provide Details</span>
            </div>
            <div class="step-indicator flex flex-col items-center" data-step="3">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold shadow-sm ring-4 ring-white transition z-10"><i class="fas fa-check-double"></i></div>
                <span class="mt-3 text-sm font-medium text-gray-500">Review & Submit</span>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <form id="wizard-form" action="submit-document-request.php" method="POST">
        <input type="hidden" name="resident_id" value="<?= $resident_id ?>">
        <input type="hidden" id="document_type" name="document_type" value="">
        <input type="hidden" id="price" name="price" value="0.00">

        <!-- Step 1: Select Document Type -->
        <div id="step-1" class="wizard-step">
            <h3 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">Which document do you need?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <div class="doc-card border-2 border-gray-200 rounded-xl p-5 cursor-pointer hover:border-blue-500 hover:shadow-md transition-all group" data-type="Barangay Clearance" data-price="50.00">
                    <div class="flex items-center mb-3">
                        <div class="bg-blue-50 p-3 rounded-lg mr-4 group-hover:bg-blue-100 text-blue-600 transition-colors"><i class="fas fa-shield-alt text-xl"></i></div>
                        <h4 class="text-lg font-bold text-gray-800">Barangay Clearance</h4>
                    </div>
                    <p class="text-sm text-gray-600">Standard clearance for general purposes. Requires valid ID. Fee: ₱50.00</p>
                </div>

                <div class="doc-card border-2 border-gray-200 rounded-xl p-5 cursor-pointer hover:border-blue-500 hover:shadow-md transition-all group" data-type="Business Clearance" data-price="500.00">
                    <div class="flex items-center mb-3">
                        <div class="bg-green-50 p-3 rounded-lg mr-4 group-hover:bg-green-100 text-green-600 transition-colors"><i class="fas fa-store text-xl"></i></div>
                        <h4 class="text-lg font-bold text-gray-800">Business Clearance</h4>
                    </div>
                    <p class="text-sm text-gray-600">For business operations and permits. Processing takes 3-5 days. Fee: ₱500.00+</p>
                </div>

                <div class="doc-card border-2 border-gray-200 rounded-xl p-5 cursor-pointer hover:border-blue-500 hover:shadow-md transition-all group" data-type="Certificate of Indigency" data-price="30.00">
                    <div class="flex items-center mb-3">
                        <div class="bg-yellow-50 p-3 rounded-lg mr-4 group-hover:bg-yellow-100 text-yellow-600 transition-colors"><i class="fas fa-hands-helping text-xl"></i></div>
                        <h4 class="text-lg font-bold text-gray-800">Certificate of Indigency</h4>
                    </div>
                    <p class="text-sm text-gray-600">For social services, financial or medical assistance. Fee: ₱30.00</p>
                </div>

                <div class="doc-card border-2 border-gray-200 rounded-xl p-5 cursor-pointer hover:border-blue-500 hover:shadow-md transition-all group" data-type="Certificate of Residency" data-price="30.00">
                    <div class="flex items-center mb-3">
                        <div class="bg-purple-50 p-3 rounded-lg mr-4 group-hover:bg-purple-100 text-purple-600 transition-colors"><i class="fas fa-home text-xl"></i></div>
                        <h4 class="text-lg font-bold text-gray-800">Certificate of Residency</h4>
                    </div>
                    <p class="text-sm text-gray-600">Proof of residence address within the barangay. Fee: ₱30.00</p>
                </div>
            </div>
            
            <div class="mt-8 flex justify-end">
                <button type="button" class="next-btn bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>Next Step <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- Step 2: Form Details -->
        <div id="step-2" class="wizard-step hidden">
            <div class="bg-gray-50 rounded-xl p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center"><i class="fas fa-user-circle text-blue-500 mr-2"></i> Applicant Data (Pre-filled)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Full Name</label>
                        <div class="bg-white p-3 rounded border border-gray-200 text-gray-700 font-medium"><?= $fullName ?></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Address</label>
                        <div class="bg-white p-3 rounded border border-gray-200 text-gray-700 font-medium"><?= $address ?></div>
                    </div>
                </div>
            </div>

            <div id="dynamic-fields-container" class="space-y-6">
                <!-- Fields injected via JS based on document type -->
            </div>

            <div class="mt-8 flex justify-between">
                <button type="button" class="prev-btn bg-white border border-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition"><i class="fas fa-arrow-left mr-2"></i> Back</button>
                <button type="button" class="next-btn bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">Review <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- Step 3: Review & Submit -->
        <div id="step-3" class="wizard-step hidden">
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-6 mb-8 text-center">
                <i class="fas fa-file-invoice flex text-4xl text-blue-500 mb-4 justify-center"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-1">Review Your Request</h3>
                <p class="text-gray-600">Please review the details below before submitting.</p>
            </div>
            
            <div class="bg-white border text-sm border-gray-200 rounded-lg overflow-hidden shadow-sm mb-6">
                <table class="w-full text-left border-collapse">
                    <tbody id="review-table-body" class="divide-y divide-gray-200">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>

            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8 rounded-r-lg">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                    <p class="text-sm text-yellow-800">
                        By submitting, you certify that all information is accurate. You will be notified via the portal when your request is ready. Payment of <strong id="review-price"></strong> will be collected upon pickup.
                    </p>
                </div>
            </div>
            
            <div id="form-message" class="hidden mb-6 p-4 rounded-lg"></div>

            <div class="mt-4 flex justify-between">
                <button type="button" class="prev-btn bg-white border border-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition"><i class="fas fa-arrow-left mr-2"></i> Edit Details</button>
                <button type="submit" id="submit-btn" class="bg-green-600 text-white px-8 py-3 rounded-lg font-bold text-lg shadow-lg hover:bg-green-700 transition transform hover:-translate-y-1"><i class="fas fa-paper-plane mr-2"></i> Submit Request</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentStep = 1;
    const form = document.getElementById('wizard-form');
    const docInput = document.getElementById('document_type');
    const priceInput = document.getElementById('price');
    const nextBtns = document.querySelectorAll('.next-btn');
    const prevBtns = document.querySelectorAll('.prev-btn');
    const cards = document.querySelectorAll('.doc-card');
    const dynamicContainer = document.getElementById('dynamic-fields-container');
    const reviewBody = document.getElementById('review-table-body');
    const reviewPrice = document.getElementById('review-price');
    const progressBar = document.getElementById('progress-bar');
    const steps = document.querySelectorAll('.wizard-step');
    const indicators = document.querySelectorAll('.step-indicator');

    // Handle Card Selection
    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => {
                c.classList.remove('border-blue-600', 'bg-blue-50', 'ring-2', 'ring-blue-200');
                c.classList.add('border-gray-200');
            });
            card.classList.remove('border-gray-200');
            card.classList.add('border-blue-600', 'bg-blue-50', 'ring-2', 'ring-blue-200');
            
            docInput.value = card.dataset.type;
            priceInput.value = card.dataset.price;
            
            // Enable next button
            document.querySelector('#step-1 .next-btn').disabled = false;
        });
    });

    // Handle Next Buttons
    nextBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentStep === 1) {
                renderDynamicFields(docInput.value);
            } else if (currentStep === 2) {
                if (!form.reportValidity()) return; // Validate HTML5 constraints
                populateReview();
            }
            changeStep(1);
        });
    });

    // Handle Prev Buttons
    prevBtns.forEach(btn => {
        btn.addEventListener('click', () => changeStep(-1));
    });

    function changeStep(dir) {
        steps[currentStep - 1].classList.add('hidden');
        indicators[currentStep - 1].querySelector('div').classList.remove('bg-blue-600', 'text-white');
        indicators[currentStep - 1].querySelector('div').classList.add('bg-gray-200', 'text-gray-500');
        indicators[currentStep - 1].querySelector('span').classList.remove('text-blue-700');
        indicators[currentStep - 1].querySelector('span').classList.add('text-gray-500');
        
        currentStep += dir;
        
        steps[currentStep - 1].classList.remove('hidden');
        
        for (let i = 0; i < currentStep; i++) {
            indicators[i].querySelector('div').classList.remove('bg-gray-200', 'text-gray-500');
            indicators[i].querySelector('div').classList.add('bg-blue-600', 'text-white');
            indicators[i].querySelector('span').classList.remove('text-gray-500');
            indicators[i].querySelector('span').classList.add('text-blue-700', 'font-semibold');
        }
        
        progressBar.style.width = ((currentStep - 1) * 50) + '%';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderDynamicFields(type) {
        let html = '';
        
        // Common Purpose field
        html += `
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Purpose of Request *</label>
                <textarea name="purpose" rows="3" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" required placeholder="State your reason for requesting this document..."></textarea>
            </div>
        `;

        if (type === 'Barangay Clearance') {
            html += `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Application Type *</label>
                        <select name="application_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select type</option>
                            <option value="New">New Application</option>
                            <option value="Renewal">Renewal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Urgency Level</label>
                        <select name="urgency" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                            <option value="Normal">Normal (1-2 business days)</option>
                            <option value="Urgent">Urgent (Same day)</option>
                        </select>
                    </div>
                </div>
            `;
        } else if (type === 'Business Clearance') {
            html += `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Business Name *</label>
                        <input type="text" name="business_name" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Business Type *</label>
                        <select name="business_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select type...</option>
                            <option value="Retail">Retail</option>
                            <option value="Food and Beverage">Food and Beverage</option>
                            <option value="Services">Services</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Business Address *</label>
                        <textarea name="business_address" rows="2" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Application Type *</label>
                        <select name="application_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                            <option value="New Permit">New Permit</option>
                            <option value="Renewal">Renewal</option>
                            <option value="Amendment">Amendment</option>
                        </select>
                    </div>
                </div>
            `;
        } else if (type === 'Certificate of Indigency') {
            html += `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Monthly Income (₱) *</label>
                        <input type="number" step="0.01" name="monthly_income" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" placeholder="0.00" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Family Size *</label>
                        <input type="number" min="1" name="family_size" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Source of Income *</label>
                        <select name="source_of_income" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select...</option>
                            <option value="Employment">Employment</option>
                            <option value="Self-employed">Self-employed</option>
                            <option value="Unemployed">Unemployed</option>
                            <option value="Student/Retired/Other">Student/Retired/Other</option>
                        </select>
                    </div>
                </div>
            `;
        } else if (type === 'Certificate of Residency') {
            html += `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Length of Residence *</label>
                        <select name="length_of_residence" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select...</option>
                            <option value="Less than 1 year">Less than 1 year</option>
                            <option value="1-5 years">1-5 years</option>
                            <option value="More than 5 years">More than 5 years</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Residence Type *</label>
                        <select name="residence_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select...</option>
                            <option value="Owned">Owned</option>
                            <option value="Rented">Rented</option>
                            <option value="With relatives">With relatives</option>
                        </select>
                    </div>
                </div>
            `;
        }

        // Common Additional Notes
        html += `
            <div class="mt-5">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes (Optional)</label>
                <textarea name="additional_notes" rows="2" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" placeholder="Any special requirements..."></textarea>
            </div>
        `;

        dynamicContainer.innerHTML = html;
    }

    function populateReview() {
        reviewPrice.textContent = '₱ ' + priceInput.value;
        let html = `
            <tr class="bg-gray-50"><td class="py-3 px-4 font-bold text-gray-700 w-1/3 border-r border-gray-200">Document Type</td><td class="py-3 px-4 font-semibold text-blue-700 border-l border-white">${docInput.value}</td></tr>
            <tr class="bg-white"><td class="py-3 px-4 font-bold text-gray-700 border-r border-gray-200">Fee</td><td class="py-3 px-4 font-bold text-green-600 border-l border-gray-50">₱ ${priceInput.value}</td></tr>
        `;
        
        const fd = new FormData(form);
        const exclusions = ['document_type', 'price', 'resident_id'];
        
        for (let [key, value] of fd.entries()) {
            if (!exclusions.includes(key) && value.trim() !== '') {
                // Humanize key
                let cleanKey = key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                html += `<tr class="border-t border-gray-100"><td class="py-3 px-4 font-medium text-gray-600 border-r border-gray-200">${cleanKey}</td><td class="py-3 px-4 text-gray-800 border-l border-gray-50">${value}</td></tr>`;
            }
        }
        
        reviewBody.innerHTML = html;
    }

    // Handle AJAX Submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submit-btn');
        const msgDiv = document.getElementById('form-message');
        
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        
        fetch('submit-document-request.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(res => res.json())
        .then(data => {
            msgDiv.classList.remove('hidden');
            if (data.success) {
                msgDiv.className = 'mb-6 p-4 rounded-lg bg-green-100 text-green-800 border-l-4 border-green-500 font-medium';
                msgDiv.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${data.message} Redirecting to your requests...`;
                setTimeout(() => window.location.href = 'my-requests.php', 2000);
            } else {
                msgDiv.className = 'mb-6 p-4 rounded-lg bg-red-100 text-red-800 border-l-4 border-red-500 font-medium';
                msgDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${data.error}`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(err => {
            msgDiv.classList.remove('hidden');
            msgDiv.className = 'mb-6 p-4 rounded-lg bg-red-100 text-red-800 border-l-4 border-red-500 font-medium';
            msgDiv.innerHTML = `<i class="fas fa-wifi mr-2"></i>Network error occurred. Please try again.`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
