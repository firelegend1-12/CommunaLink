<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'Request Barangay Business Clearance';
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
        echo '<div class="max-w-xl mx-auto mt-4 text-gray-600">This usually means your resident profile is not properly linked to your user account.</div>';
        echo '<div class="max-w-xl mx-auto mt-4">';
        echo '<a href="../debug_residents.php" class="text-blue-600 hover:text-blue-800">View Database Status</a> | ';
        echo '<a href="../fix_resident_links.php" class="text-green-600 hover:text-green-800">Fix Database Links</a>';
        echo '</div>';
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
?>

<!-- Dashboard-matching background and accent -->
<div class="min-h-screen bg-gray-100 py-8">
  <div class="max-w-3xl mx-auto">
    <div class="relative bg-white rounded-2xl shadow-xl p-0 mb-8 flex overflow-hidden border border-gray-200">
      <!-- Blue accent bar -->
      <div class="hidden sm:block w-2 bg-blue-700 rounded-l-2xl"></div>
      <div class="flex-1 p-8">
        <div class="flex items-center mb-6">
          <div class="bg-blue-100 text-blue-700 rounded-full p-3 mr-3">
            <i class="fas fa-briefcase fa-lg"></i>
          </div>
          <h1 class="text-3xl font-extrabold text-blue-700 tracking-tight">Request Barangay Business Clearance</h1>
        </div>
        <p class="text-gray-600 mb-6">
          <span class="font-semibold">Processing Time:</span> 3-5 business days<br>
          <span class="font-semibold">Fee:</span> Varies by business type (₱500.00 - ₱2,000.00)<br>
          <span class="font-semibold">Requirements:</span> Business registration, Valid ID, Proof of location
        </p>
        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="bg-green-50 border-l-4 border-green-400 text-green-700 p-4 mb-6 rounded">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
          </div>
          <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
          <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-4 mb-6 rounded">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
          </div>
          <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <form action="submit-document-request.php" method="POST" class="space-y-8">
          <!-- Applicant Information -->
          <div class="bg-gray-50 rounded-xl p-6 mb-4 shadow-sm border border-gray-100">
            <div class="flex items-center mb-4">
              <div class="bg-blue-200 text-blue-700 rounded-full p-2 mr-2">
                <i class="fas fa-user fa-lg"></i>
              </div>
              <h2 class="text-lg font-bold text-blue-700 tracking-tight">Applicant Information</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" value="<?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-800 focus:ring-2 focus:ring-blue-200 focus:border-blue-400" readonly>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Address</label>
                <input type="text" value="<?php echo htmlspecialchars($resident['address']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-800 focus:ring-2 focus:ring-blue-200 focus:border-blue-400" readonly>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                <input type="text" value="<?php echo htmlspecialchars($resident['contact_no'] ?? 'N/A'); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-800 focus:ring-2 focus:ring-blue-200 focus:border-blue-400" readonly>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Civil Status</label>
                <input type="text" value="<?php echo htmlspecialchars($resident['civil_status']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-800 focus:ring-2 focus:ring-blue-200 focus:border-blue-400" readonly>
              </div>
            </div>
          </div>
          <!-- Business Information -->
          <div class="bg-gray-50 rounded-xl p-6 mb-4 shadow-sm border border-gray-100">
            <div class="flex items-center mb-4">
              <div class="bg-green-200 text-green-700 rounded-full p-2 mr-2">
                <i class="fas fa-store fa-lg"></i>
              </div>
              <h2 class="text-lg font-bold text-green-700 tracking-tight">Business Information</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="business_name" class="block text-sm font-medium text-gray-700">Business Name *</label>
                <input type="text" id="business_name" name="business_name" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="Enter business name" required>
              </div>
              <div>
                <label for="business_type" class="block text-sm font-medium text-gray-700">Business Type *</label>
                <select id="business_type" name="business_type" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" required>
                  <option value="">Select business type</option>
                  <option value="Retail">Retail</option>
                  <option value="Food and Beverage">Food and Beverage</option>
                  <option value="Services">Services</option>
                  <option value="Manufacturing">Manufacturing</option>
                  <option value="Construction">Construction</option>
                  <option value="Transportation">Transportation</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <label for="business_address" class="block text-sm font-medium text-gray-700">Business Address *</label>
                <textarea id="business_address" name="business_address" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="Enter complete business address" required></textarea>
              </div>
              <div>
                <label for="business_contact" class="block text-sm font-medium text-gray-700">Business Contact Number</label>
                <input type="tel" id="business_contact" name="business_contact" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="Business phone number">
              </div>
              <div>
                <label for="number_of_employees" class="block text-sm font-medium text-gray-700">Number of Employees</label>
                <input type="number" id="number_of_employees" name="number_of_employees" min="1" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="0">
              </div>
              <div class="md:col-span-2">
                <label for="business_description" class="block text-sm font-medium text-gray-700">Business Description *</label>
                <textarea id="business_description" name="business_description" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="Describe your business activities and services..." required></textarea>
              </div>
            </div>
          </div>
          <!-- Request Details -->
          <div class="bg-gray-50 rounded-xl p-6 mb-4 shadow-sm border border-gray-100">
            <div class="flex items-center mb-4">
              <div class="bg-purple-200 text-purple-700 rounded-full p-2 mr-2">
                <i class="fas fa-file-alt fa-lg"></i>
              </div>
              <h2 class="text-lg font-bold text-purple-700 tracking-tight">Request Details</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="md:col-span-2">
                <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose of Clearance *</label>
                <textarea id="purpose" name="purpose" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="Please specify the purpose for requesting this clearance..." required></textarea>
              </div>
              <div>
                <label for="application_type" class="block text-sm font-medium text-gray-700">Application Type *</label>
                <select id="application_type" name="application_type" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" required>
                  <option value="">Select application type</option>
                  <option value="New Permit">New Permit</option>
                  <option value="Renewal">Renewal</option>
                  <option value="Amendment">Amendment</option>
                </select>
              </div>
              <div>
                <label for="urgency" class="block text-sm font-medium text-gray-700">Urgency Level</label>
                <select id="urgency" name="urgency" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm">
                  <option value="Normal">Normal (3-5 business days)</option>
                  <option value="Urgent">Urgent (1-2 business days)</option>
                  <option value="Rush">Rush (Same day)</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <label for="additional_notes" class="block text-sm font-medium text-gray-700">Additional Information</label>
                <textarea id="additional_notes" name="additional_notes" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 sm:text-sm" placeholder="Any additional information about your business or special requirements..."></textarea>
              </div>
            </div>
          </div>
          <!-- Hidden Inputs -->
          <input type="hidden" name="document_type" value="Business Clearance">
          <input type="hidden" name="resident_id" value="<?php echo $resident_id; ?>">
          <input type="hidden" name="price" value="500.00">
          <!-- Buttons -->
          <div class="flex justify-end space-x-4 mt-6">
            <a href="barangay-services.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg text-sm font-medium shadow-sm border border-gray-300">Cancel</a>
            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-7 py-3 rounded-lg text-base font-bold shadow-md border border-blue-700 flex items-center transition-all duration-150">
              <i class="fas fa-paper-plane mr-2"></i>Submit Request
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.querySelector('button[type="submit"]');
    
    const formMessage = document.createElement('div');
    formMessage.style.display = 'none';
    formMessage.className = 'mb-4';
    form.insertBefore(formMessage, submitBtn.closest('.flex.justify-end'));

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            formMessage.style.display = 'none';

            fetch('submit-document-request.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.json())
            .then(data => {
                formMessage.style.display = 'block';
                if (data.success) {
                    formMessage.className = 'p-4 mb-4 rounded-md font-medium bg-green-100 text-green-700 border-l-4 border-green-500';
                    formMessage.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message + ' Redirecting...';
                    setTimeout(() => window.location.href = 'my-requests.php', 1500);
                } else {
                    formMessage.className = 'p-4 mb-4 rounded-md font-medium bg-red-100 text-red-700 border-l-4 border-red-500';
                    formMessage.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + (data.error || 'Submission failed');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                formMessage.style.display = 'block';
                formMessage.className = 'p-4 mb-4 rounded-md font-medium bg-red-100 text-red-700 border-l-4 border-red-500';
                formMessage.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>Network error occurred.';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
});
</script>

<?php require_once 'partials/footer.php'; ?> 