<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'Request Certificate of Indigency';
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

<div class="max-w-4xl mx-auto bg-white rounded-lg shadow p-8 mt-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-green-700">Request Certificate of Indigency</h1>
        <a href="barangay-services.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm">
            <i class="fas fa-arrow-left mr-2"></i>Back to Services
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700">
                    <strong>Processing Time:</strong> 1 business day<br>
                    <strong>Fee:</strong> ₱30.00<br>
                    <strong>Requirements:</strong> Proof of income, Valid ID, Supporting documents
                </p>
            </div>
        </div>
    </div>

    <form action="submit-document-request.php" method="POST" class="space-y-6">
        <input type="hidden" name="document_type" value="Certificate of Indigency">
        <input type="hidden" name="resident_id" value="<?php echo $resident_id; ?>">
        <input type="hidden" name="price" value="30.00">

        <!-- Applicant Information (Read-only) -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Applicant Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>" class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md text-sm" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" value="<?php echo htmlspecialchars($resident['address']); ?>" class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md text-sm" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($resident['contact_no'] ?? 'N/A'); ?>" class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md text-sm" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Civil Status</label>
                    <input type="text" value="<?php echo htmlspecialchars($resident['civil_status']); ?>" class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md text-sm" readonly>
                </div>
            </div>
        </div>

        <!-- Request Details -->
        <div class="space-y-4">
            <div>
                <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose of Certificate *</label>
                <textarea id="purpose" name="purpose" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="Please specify the purpose for requesting this certificate..." required></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="monthly_income" class="block text-sm font-medium text-gray-700">Monthly Income (₱) *</label>
                    <input type="number" id="monthly_income" name="monthly_income" step="0.01" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="0.00" required>
                </div>
                <div>
                    <label for="family_size" class="block text-sm font-medium text-gray-700">Number of Family Members *</label>
                    <input type="number" id="family_size" name="family_size" min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="1" required>
                </div>
            </div>

            <div>
                <label for="source_of_income" class="block text-sm font-medium text-gray-700">Source of Income *</label>
                <select id="source_of_income" name="source_of_income" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    <option value="">Select source of income</option>
                    <option value="Employment">Employment</option>
                    <option value="Self-employed">Self-employed</option>
                    <option value="Unemployed">Unemployed</option>
                    <option value="Student">Student</option>
                    <option value="Retired">Retired</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label for="additional_notes" class="block text-sm font-medium text-gray-700">Additional Information</label>
                <textarea id="additional_notes" name="additional_notes" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="Please provide additional information about your financial situation, dependents, or any special circumstances..."></textarea>
            </div>
        </div>

        <!-- Terms and Conditions -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Important:</strong> By submitting this request, you acknowledge that:
                    </p>
                    <ul class="text-sm text-yellow-700 mt-2 list-disc list-inside">
                        <li>All information provided is true and accurate</li>
                        <li>You may be required to provide supporting documents</li>
                        <li>False information may result in legal consequences</li>
                        <li>You will be notified once your request is ready for pickup</li>
                        <li>Payment of ₱30.00 is required upon pickup</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="barangay-services.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-sm font-medium">
                Cancel
            </a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                <i class="fas fa-paper-plane mr-2"></i>Submit Request
            </button>
        </div>
    </form>
</div>

<?php require_once 'partials/footer.php'; ?> 