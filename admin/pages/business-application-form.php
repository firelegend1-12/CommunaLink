<?php
/**
 * Business Application Form Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
require_login();

// Page title
$page_title = "New Business Application - CommuniLink";

try {
    // Fetch residents to populate the owner dropdown
    $stmt = $pdo->query("SELECT id, first_name, last_name, middle_initial FROM residents ORDER BY last_name ASC");
    $residents = $stmt->fetchAll();
} catch (PDOException $e) {
    $residents = [];
    $_SESSION['error_message'] = "Could not fetch residents list.";
    // Optionally log the error: error_log($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">New Business Application</h1>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20 divide-y divide-gray-200">
                                
                                <div class="py-1">
                                    <a href="account.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user-circle mr-2 text-gray-500"></i>
                                        My Account
                                    </a>
                                </div>
                                <div class="py-1">
                                    <a href="../../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2 text-gray-500"></i>
                                        Sign Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <form action="../partials/business-application-handler.php" method="POST" class="space-y-6">
                        
                        <!-- Owner Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Owner Information</h3>
                            <div class="grid grid-cols-1">
                                <div>
                                    <label for="resident_id" class="block text-sm font-medium text-gray-700">Select Business Owner (Resident)</label>
                                    <select name="resident_id" id="resident_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="">-- Select a Resident --</option>
                                        <?php foreach ($residents as $resident): ?>
                                            <option value="<?php echo $resident['id']; ?>">
                                                <?php echo htmlspecialchars($resident['last_name'] . ', ' . $resident['first_name'] . ' ' . $resident['middle_initial']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Business Details Section -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Business Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label for="business_name" class="block text-sm font-medium text-gray-700">Business Name</label>
                                    <input type="text" name="business_name" id="business_name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="business_type" class="block text-sm font-medium text-gray-700">Type of Business</label>
                                    <input type="text" name="business_type" id="business_type" required placeholder="e.g., Sari-sari Store, Eatery, etc." class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                     <label for="transaction_type" class="block text-sm font-medium text-gray-700">Transaction Type</label>
                                     <select name="transaction_type" id="transaction_type" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                         <option value="New Permit">New Permit</option>
                                         <option value="Renewal">Renewal</option>
                                     </select>
                                 </div>
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Business Address</label>
                                    <textarea name="address" id="address" rows="3" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end pt-4 border-t mt-6">
                            <a href="monitoring-of-request.php?type=business" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md mr-2">Cancel</a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Submit Application</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 