<?php
/**
 * New Barangay Clearance Application Page
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();

$page_title = "Application for Barangay Clearance (Individual)";

// Fetch residents for the dropdown
$residents = [];
try {
    // Fetch residents with all necessary info for auto-filling
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, first_name, last_name, middle_initial, gender, address, date_of_birth, place_of_birth, civil_status, occupation FROM residents ORDER BY last_name ASC");
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
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* The custom style will be removed, and Tailwind classes will be used instead. */
    </style>
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
                             selectedResident: {},
                             age: null,
                             updateFields() {
                                 if (this.selectedResident.id) {
                                     const resident = this.residents.find(r => r.id == this.selectedResident.id);
                                     this.selectedResident = { ...this.selectedResident, ...resident };
                                     if (this.selectedResident.date_of_birth) {
                                        const birthDate = new Date(this.selectedResident.date_of_birth);
                                        this.age = new Date().getFullYear() - birthDate.getFullYear();
                                     }
                                 }
                             }
                         }'>

                        <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg">
                            <div class="text-center border-b pb-4 mb-6">
                                <h2 class="text-lg font-bold">Barangay Pakiad Oton</h2>
                                <p class="font-semibold">Iloilo City</p>
                                <p class="font-semibold">OFFICE OF THE PUNONG BARANGAY</p>
                                <h1 class="text-2xl font-bold mt-4 uppercase">APPLICATION FOR BARANGAY CLEARANCE (INDIVIDUAL)</h1>
                            </div>
                            
                            <form action="../partials/new-barangay-clearance-handler.php" method="POST">
                                <!-- Application Type & Initial Details -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                                    <div class="col-span-2 flex items-center space-x-4">
                                        <label class="font-medium">Application Type:</label>
                                        <label class="flex items-center"><input type="radio" name="application_type" value="New" class="mr-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"> NEW</label>
                                        <label class="flex items-center"><input type="radio" name="application_type" value="Renewal" class="mr-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"> RENEWAL</label>
                                    </div>
                                    <div><label class="block text-sm font-medium text-gray-700">No.:</label><input type="text" name="clearance_no" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Date:</label><input type="date" name="clearance_date" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                </div>
                                
                                <!-- Applicant's Personal Information -->
                                <fieldset class="border p-4 rounded-md mb-6">
                                    <legend class="font-semibold px-2">Applicant's Personal Information</legend>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div class="md:col-span-3">
                                            <label for="resident_id" class="block text-sm font-medium text-gray-700">Select Applicant Name:</label>
                                            <select id="resident_id" name="resident_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" x-model="selectedResident.id" @change="updateFields()" required>
                                                <option value="">-- Select a Resident --</option>
                                                <?php foreach ($residents as $res): ?>
                                                    <option value="<?= $res['id'] ?>"><?= htmlspecialchars($res['full_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div><label class="block text-sm font-medium text-gray-700">Last Name (Apelyido):</label><input type="text" x-model="selectedResident.last_name" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div><label class="block text-sm font-medium text-gray-700">First Name (Pangalan):</label><input type="text" x-model="selectedResident.first_name" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Middle Name (Gitnang Pangalan):</label><input type="text" x-model="selectedResident.middle_initial" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        
                                        <div><label class="block text-sm font-medium text-gray-700">Sex:</label><input type="text" x-model="selectedResident.gender" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div class="col-span-2"><label class="block text-sm font-medium text-gray-700">Address:</label><textarea x-model="selectedResident.address" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" rows="1" readonly></textarea></div>

                                        <div><label class="block text-sm font-medium text-gray-700">Date of Birth:</label><input type="date" x-model="selectedResident.date_of_birth" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Age:</label><input type="number" x-model="age" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Place of Birth:</label><input type="text" x-model="selectedResident.place_of_birth" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        
                                        <div><label class="block text-sm font-medium text-gray-700">Occupation:</label><input type="text" x-model="selectedResident.occupation" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Civil Status:</label><input type="text" x-model="selectedResident.civil_status" class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Precinct No.:</label><input type="text" name="precinct_no" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>

                                        <div><label class="block text-sm font-medium text-gray-700">Resident Since:</label><input type="text" name="resident_since" placeholder="Year" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        <div class="col-span-2"><label class="block text-sm font-medium text-gray-700">If employed, Name of Company:</label><input type="text" name="company_name" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        
                                        <div class="col-span-3"><label class="block text-sm font-medium text-gray-700">Purpose of Clearance:</label><textarea name="purpose" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required rows="2"></textarea></div>
                                    </div>
                                </fieldset>

                                <!-- References -->
                                <fieldset class="border p-4 rounded-md mb-6">
                                    <legend class="font-semibold px-2">References</legend>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div><label class="block text-sm font-medium text-gray-700">Reference (1) (not your relative):</label><input type="text" name="reference_1" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Reference (2) (not your relative):</label><input type="text" name="reference_2" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        <div class="col-span-2"><label class="block text-sm font-medium text-gray-700">Tel. No.:</label><input type="tel" name="reference_tel_no" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    </div>
                                </fieldset>
                                
                                <!-- CTC and Fees -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <fieldset class="border p-4 rounded-md">
                                        <legend class="font-semibold px-2">Community Tax Certificate</legend>
                                        <div class="space-y-4">
                                            <div><label class="block text-sm font-medium text-gray-700">CTC No.:</label><input type="text" name="ctc_no" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                            <div><label class="block text-sm font-medium text-gray-700">Issued At:</label><input type="text" name="ctc_issued_at" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                            <div><label class="block text-sm font-medium text-gray-700">Issued On:</label><input type="date" name="ctc_issued_on" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        </div>
                                    </fieldset>
                                    <fieldset class="border p-4 rounded-md">
                                        <legend class="font-semibold px-2">Official Use / Fees</legend>
                                        <div class="space-y-4">
                                            <div><label class="block text-sm font-medium text-gray-700">Clearance Fee:</label><input type="number" name="clearance_fee" step="0.01" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                            <div><label class="block text-sm font-medium text-gray-700">O.R. No.:</label><input type="text" name="or_no" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                            <div><label class="block text-sm font-medium text-gray-700">Date:</label><input type="date" name="or_date" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                            <div><label class="block text-sm font-medium text-gray-700">Remarks:</label><textarea name="remarks" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" rows="1"></textarea></div>
                                        </div>
                                    </fieldset>
                                    </div>
                                
                                <!-- Signatures -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label class="block text-sm font-medium">Signature of Applicant:</label>
                                        <div class="mt-1 p-4 border-2 border-dashed rounded-md h-24"></div>
                                    </div>
                                <div>
                                        <label class="block text-sm font-medium">Right Thumbmark:</label>
                                        <div class="mt-1 p-4 border-2 border-dashed rounded-md h-24"></div>
                                    </div>
                                </div>
                                
                                <!-- Approval -->
                                <div class="text-right">
                                    <p class="mb-8">______________________________________</p>
                                    <p class="font-bold uppercase"><?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                                    <p>PUNONG BARANGAY</p>
                                </div>

                                <div class="flex justify-end pt-4 border-t mt-6">
                                    <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md mr-2">Cancel</a>
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Submit Application</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 