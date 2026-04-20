<?php
require_once '../partials/admin_auth.php';
/**
 * New Certificate of Residency Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_login();

$page_title = "New Certificate of Residency - CommunaLink";

// Fetch residents for the dropdown
try {
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name FROM residents ORDER BY last_name ASC");
    $residents = $resident_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $residents = [];
    $_SESSION['error_message'] = "A database error occurred while fetching residents.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php
include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">New Certificate of Residency</h1>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php
echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php
echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                 <div class="max-w-4xl mx-auto">
                    <?php
display_flash_messages(); ?>

                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <form action="../partials/new-certificate-of-residency-handler.php" method="POST" class="space-y-8" x-data='{ residents: <?php
echo json_encode($residents); ?>, applicantName: "" }'>
                            <?php echo csrf_field(); ?>
                            
                            <!-- Header Section -->
                            <div class="text-center border-b-2 border-gray-200 pb-6 mb-8">
                                <h2 class="text-3xl font-bold uppercase text-gray-800">Certificate of Residency</h2>
                            </div>

                            <!-- Main Body/Content -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">I. Applicant Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="resident_id" class="block text-sm font-medium text-gray-700">Select Applicant</label>
                                        <select name="resident_id" id="resident_id" required @change="applicantName = residents.find(r => r.id == $event.target.value)?.full_name || ''" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="" disabled selected>-- Select a Resident --</option>
                                            <?php
foreach ($residents as $resident): ?>
                                                <option value="<?php
echo $resident['id']; ?>"><?php
echo htmlspecialchars($resident['full_name']); ?></option>
                                            <?php
endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="applicant_name" class="block text-sm font-medium text-gray-700">Name of Applicant</label>
                                        <input type="text" name="applicant_name" id="applicant_name" x-model="applicantName" required class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">II. Property and Residency Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="property_owner" class="block text-sm font-medium text-gray-700">Property is owned by</label>
                                        <input type="text" name="property_owner" id="property_owner" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="sitio" class="block text-sm font-medium text-gray-700">Sitio/Purok/Zone/Building No.</label>
                                        <input type="text" name="sitio" id="sitio" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                     <div>
                                        <label for="district" class="block text-sm font-medium text-gray-700">District</label>
                                        <input type="text" name="district" id="district" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">III. Residency Status</h3>
                                <p class="text-sm text-gray-600 mb-2">Based on records of this office, the above-named individual belongs to the:</p>
                                <div class="flex items-center space-x-6">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="status[]" value="low income bracket" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Low Income Bracket</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="status[]" value="informal settler" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Informal Settler</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">IV. Purpose</h3>
                                <p class="text-sm text-gray-700 bg-gray-50 p-4 rounded-md">
                                    This certification is being issued upon the request of the above-named person intended for compliance with the requirements of the <strong>iKonek ELECTRIFICATION PROGRAM OF MAYOR JERRY P. TRENAS</strong> and <strong>MORE ELECTRIC AND POWER CORP.</strong>
                                </p>
                            </div>

                             <div>
                                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">V. Issuance Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                     <div>
                                        <label for="issued_on" class="block text-sm font-medium text-gray-700">Date of Issuance</label>
                                        <input type="date" name="issued_on" id="issued_on" value="<?php
echo date('Y-m-d'); ?>" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end pt-8 border-t">
                                <a href="monitoring-of-request.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md mr-3">Cancel</a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded-md shadow-md">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit Application
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 


