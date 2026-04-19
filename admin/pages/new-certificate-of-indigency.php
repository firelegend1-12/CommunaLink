<?php
/**
 * New Certificate of Indigency Page
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();

$page_title = "Certificate of Indigency";

// Fetch residents for the dropdown
$residents = [];
try {
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, civil_status FROM residents ORDER BY last_name ASC");
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
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?php echo $page_title; ?></h1>
                        
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
                    <?php display_flash_messages(); ?>
                    <div class="bg-white rounded-lg shadow p-8" 
                         x-data='{ 
                             residents: <?php echo json_encode($residents); ?>, 
                             selectedResident: { id: "", full_name: "", civil_status: "" },
                             updateFields() {
                                 if (this.selectedResident.id) {
                                     const resident = this.residents.find(r => r.id == this.selectedResident.id);
                                     if (resident) {
                                        this.selectedResident.full_name = resident.full_name;
                                        this.selectedResident.civil_status = resident.civil_status;
                                     } else {
                                        this.selectedResident.full_name = "";
                                        this.selectedResident.civil_status = "";
                                     }
                                 } else {
                                     this.selectedResident.full_name = "";
                                     this.selectedResident.civil_status = "";
                                 }
                             }
                         }'>

                        <form action="../partials/new-certificate-of-indigency-handler.php" method="POST">
                            <!-- Header Section -->
                            <div class="text-center mb-8">
                                <p>REPUBLIC OF THE PHILIPPINES</p>
                                <p>Iloilo City</p>
                                <p class="font-bold">Barangay Pakiad Oton</p>
                                <h2 class="text-xl font-bold mt-2">Office of the Punong Barangay</h2>
                            </div>

                            <h1 class="text-center text-2xl font-bold uppercase my-8">CERTIFICATE OF INDIGENCY</h1>

                            <!-- Main Body -->
                            <div class="mb-6 space-y-6">
                                <p class="font-bold">TO WHOM IT MAY CONCERN:</p>
                                
                                <p class="text-justify indent-8 leading-relaxed">
                                    This is to CERTIFY that Mr./Ms. <strong x-text="selectedResident.full_name || '__________________'"></strong>, 
                                    of legal age, <strong x-text="selectedResident.civil_status || '___________'"></strong>, 
                                    Filipino Citizen and a resident of Barangay Pakiad Oton, Iloilo City,
                                    belongs to the Indigent Families of this barangay having an annual income not exceeding the Regional Poverty Threshold (RPT) of Php 169, 824.00 per anum as determined by the National Economic Development Authority (NEDA).
                                </p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="resident_id" class="block text-sm font-medium text-gray-700">Select Recipient:</label>
                                        <select id="resident_id" name="resident_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" x-model="selectedResident.id" @change="updateFields()" required>
                                            <option value="">-- Select a Resident --</option>
                                            <?php foreach ($residents as $res): ?>
                                                <option value="<?= htmlspecialchars($res['id']) ?>"><?= htmlspecialchars($res['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <input type="text" name="civil_status" x-model="selectedResident.civil_status" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" placeholder="Civil Status" readonly>
                                    </div>
                                </div>
                                <input type="hidden" name="recipient_name" x-bind:value="selectedResident.full_name">
                                
                                <p class="text-justify indent-8 leading-relaxed">
                                    This CERTIFICATION is issued upon the request of the above-mentioned individual for whatever legal purpose/s it may best serve him or her.
                                </p>
                                
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <span>ISSUED this</span>
                                    <input type="text" name="day_issued" placeholder="day" class="w-16 text-center border-b focus:border-blue-500 focus:outline-none">
                                    <span>day of</span>
                                    <input type="text" name="month_issued" placeholder="Month" class="flex-grow text-center border-b focus:border-blue-500 focus:outline-none">,
                                    <input type="number" name="year_issued" value="<?= date('Y') ?>" class="w-20 text-center border-b focus:border-blue-500 focus:outline-none">
                                    <span>at Barangay Pakiad Oton, Iloilo City.</span>
                                </div>
                            </div>

                            <!-- Approval Section -->
                            <div class="mt-16 text-right">
                                <div class="inline-block text-center">
                                    <div class="border-b-2 border-gray-800 w-64 mb-2"></div>
                                    <p class="font-bold uppercase text-sm"><?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                                    <p class="text-xs">PRINTED NAME OF PUNONG BARANGAY</p>
                                </div>
                            </div>
                            
                            <!-- Special Instructions -->
                            <div class="mt-12 border-t pt-6">
                                <h4 class="font-bold text-sm">SPECIAL NOTE ON THE DRY SEAL:</h4>
                                <ul class="list-disc list-inside text-xs text-gray-600 mt-2 space-y-1">
                                    <li>Place the Dry Seal IF AVAILABLE.</li>
                                    <li>If the Barangay has NO dry seal, then leave the lower part of the Certificate EMPTY.</li>
                                    <li>If the Certificate contains, "NOT VALID WITHOUT SEAL", then the seal MUST BE PLACED.</li>
                                    <li>If the "NOT VALID WITHOUT SEAL" is present but no dry seal has been placed then the Certificate is NOT VALID AND NOT ACCEPTED.</li>
                                </ul>
                            </div>

                            <div class="flex justify-end pt-6 border-t mt-8">
                                <a href="monitoring-of-request.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md mr-2">Cancel</a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Submit Request</button>
                            </div>
                        </form>

                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 
