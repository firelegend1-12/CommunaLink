<?php
/**
 * New Barangay Business Clearance Page
 * - This page now contains the full Business Permit Application Form.
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_login();

$page_title = "New Business Permit - CommunaLink";

// Check for successful submission and fetch data for printing
$permit_data = null;
if (isset($_GET['success']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM business_permits WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $permit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error, maybe log it or set an error message
        $_SESSION['error_message'] = "Could not fetch permit data: " . $e->getMessage();
    }
}

// Fetch residents for the dropdown
$residents = [];
if (!$permit_data) { // Only fetch if it's a new form, not a success-redirect
    try {
        $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, address FROM residents ORDER BY last_name ASC");
        $residents = $resident_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $residents = [];
        $_SESSION['error_message'] = "A database error occurred while fetching residents.";
    }
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
    <style>
        @media print {
            body {
                background-color: white;
                font-size: 10pt; /* Adjust font size for print */
            }
            .no-print {
                display: none !important;
            }
            .printable-area {
                visibility: visible;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 1cm; /* Add some margin for printing */
                margin: 0;
            }
            .printable-area, .printable-area * {
                visibility: visible;
            }
            input, textarea, select {
                border: 1px solid #ccc !important;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            .bg-gray-200, .bg-gray-50 {
                background-color: #e5e7eb !important;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            /* Prevent page breaks inside these elements */
            .printable-area .grid > div,
            .printable-area tr,
            .printable-area fieldset > div {
                page-break-inside: avoid;
            }
            /* Prevent page breaks after these elements */
            .printable-area h3 {
                page-break-after: avoid;
            }
        }
        .form-section-title {
            background-color: #e5e7eb; /* gray-200 */
            padding: 0.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .form-input {
            margin-top: 0.25rem;
            display: block;
            width: 100%;
            border-color: #d1d5db; /* gray-300 */
            border-radius: 0.375rem;
            box-shadow: sm;
        }
        .form-label {
            font-weight: 500;
        }
        .table-cell {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10 no-print">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">New Business Permit</h1>
                        
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
                <div class="max-w-5xl mx-auto">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md no-print" role="alert">
                            <p class="font-bold">Success</p>
                            <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md no-print" role="alert">
                            <p class="font-bold">Error</p>
                            <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <div class="flex justify-end mb-4 no-print">
                        <?php if (isset($_GET['success'])): ?>
                            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center">
                                <i class="fas fa-print mr-2"></i> Print Form
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="bg-white rounded-lg shadow p-8 printable-area" id="application-form">
                        <!-- I. Header Section -->
                        <div class="text-center border-b pb-4 mb-6">
                            <p class="font-semibold">Republic of the Philippines</p>
                            <p class="font-semibold">Oton, Iloilo City</p>
                            <p class="text-lg font-bold">Office of the Barangay Captain</p>
                            <p class="font-bold">Barangay Pakiad</p>
                            <h1 class="text-2xl font-bold mt-4 uppercase">Application for Barangay Business Clearance</h1>
                        </div>
                        
                        <form action="../partials/new-business-permit-handler.php" method="POST" class="space-y-6">
                             <!-- II. Initial Information Block -->
                             <div>
                                <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Initial Information</h3>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                                    <div><label class="block text-sm font-medium text-gray-700">Date of Application:</label><input type="date" name="date_of_application" value="<?php echo $permit_data['date_of_application'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Business Account No.:</label><input type="text" name="business_account_no" value="<?php echo $permit_data['business_account_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Official Receipt No.:</label><input type="text" name="official_receipt_no" value="<?php echo $permit_data['official_receipt_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">O.R. Date:</label><input type="date" name="or_date" value="<?php echo $permit_data['or_date'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Amount Paid:</label><input type="number" name="amount_paid" step="0.01" value="<?php echo $permit_data['amount_paid'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="₱"></div>
                                </div>
                            </div>
                            
                            <!-- III. Taxpayer & Business Information -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Taxpayer & Business Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4" x-data='{ residents: <?php echo json_encode($residents); ?>, selectedId: null, taxpayerAddress: `<?php echo addslashes($permit_data['taxpayer_address'] ?? ''); ?>` }'>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Name of Taxpayers:</label>
                                        <?php if ($permit_data): ?>
                                            <input type="text" value="<?php echo htmlspecialchars($permit_data['taxpayer_name'] ?? ''); ?>" readonly class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                                        <?php else: ?>
                                            <select name="resident_id" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                    x-model="selectedId" 
                                                    @change="taxpayerAddress = residents.find(r => r.id == selectedId)?.address || ''">
                                                <option value="" disabled selected>Select a resident...</option>
                                                <?php foreach ($residents as $resident): ?>
                                                    <option value="<?php echo $resident['id']; ?>"><?php echo htmlspecialchars($resident['full_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div><label class="block text-sm font-medium text-gray-700">Telephone No.:</label><input type="tel" name="taxpayer_tel_no" value="<?php echo $permit_data['taxpayer_tel_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Fax No.:</label><input type="tel" name="taxpayer_fax_no" value="<?php echo $permit_data['taxpayer_fax_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Address:</label>
                                        <textarea name="taxpayer_address" x-model="taxpayerAddress" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" rows="2"><?php echo $permit_data['taxpayer_address'] ?? ''; ?></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div><label class="block text-sm font-medium text-gray-700">Capital:</label><input type="number" name="capital" step="0.01" value="<?php echo $permit_data['capital'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="₱"></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Barangay No.:</label><input type="text" name="taxpayer_barangay_no" value="<?php echo $permit_data['taxpayer_barangay_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    </div>
                                </div>
                                <hr class="my-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                    <div><label class="block text-sm font-medium text-gray-700">Business Trade Name:</label><input type="text" name="business_trade_name" value="<?php echo $permit_data['business_trade_name'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Telephone No. (Business):</label><input type="tel" name="business_tel_no" value="<?php echo $permit_data['business_tel_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Commercial Address:</label>
                                        <div class="grid grid-cols-2 gap-2 mt-1">
                                            <input type="text" name="comm_address_building" placeholder="Building Name" value="<?php echo $permit_data['comm_address_building'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <input type="text" name="comm_address_no" placeholder="No." value="<?php echo $permit_data['comm_address_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <input type="text" name="comm_address_street" placeholder="Street" value="<?php echo $permit_data['comm_address_street'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="col-span-2 mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <input type="text" name="comm_address_barangay_no" placeholder="Barangay No." value="<?php echo $permit_data['comm_address_barangay_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="col-span-2 mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div><label class="block text-sm font-medium text-gray-700">DTI Reg. No.:</label><input type="text" name="dti_reg_no" value="<?php echo $permit_data['dti_reg_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                            <div><label class="block text-sm font-medium text-gray-700">SEC Reg. No.:</label><input type="text" name="sec_reg_no" value="<?php echo $permit_data['sec_reg_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        </div>
                                        <div class="mt-4"><label class="block text-sm font-medium text-gray-700">No. of Employees:</label><input type="number" name="num_employees" value="<?php echo $permit_data['num_employees'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mt-4">
                                     <div><label class="block text-sm font-medium text-gray-700">Main Line of Business:</label><input type="text" name="main_line_business" value="<?php echo $permit_data['main_line_business'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                     <div><label class="block text-sm font-medium text-gray-700">Other Line of Business:</label><input type="text" name="other_line_business" value="<?php echo $permit_data['other_line_business'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                     <div><label class="block text-sm font-medium text-gray-700">Main Products / Services:</label><input type="text" name="main_products_services" value="<?php echo $permit_data['main_products_services'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                     <div><label class="block text-sm font-medium text-gray-700">Others:</label><input type="text" name="other_products_services" value="<?php echo $permit_data['other_products_services'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 mt-4" x-data="{ proof: '<?php echo $permit_data['proof_of_ownership'] ?? 'owned'; ?>' }">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Ownership Type:</label>
                                        <div class="flex space-x-4 mt-2">
                                            <label class="flex items-center"><input type="radio" name="ownership_type" value="single" <?php if($permit_data && $permit_data['ownership_type'] == 'single') echo 'checked'; ?> <?php if($permit_data) echo 'disabled'; ?> class="mr-1"> Single</label>
                                            <label class="flex items-center"><input type="radio" name="ownership_type" value="partnership" <?php if($permit_data && $permit_data['ownership_type'] == 'partnership') echo 'checked'; ?> <?php if($permit_data) echo 'disabled'; ?> class="mr-1"> Partnership</label>
                                            <label class="flex items-center"><input type="radio" name="ownership_type" value="corporation" <?php if($permit_data && $permit_data['ownership_type'] == 'corporation') echo 'checked'; ?> <?php if($permit_data) echo 'disabled'; ?> class="mr-1"> Corporation</label>
                                        </div>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700">Proof of Ownership:</label>
                                        <div class="flex space-x-4 mt-2">
                                            <label class="flex items-center"><input type="radio" name="proof_of_ownership" value="owned" x-model="proof" <?php if($permit_data) echo 'disabled'; ?> class="mr-1"> Owned</label>
                                            <label class="flex items-center"><input type="radio" name="proof_of_ownership" value="leased" x-model="proof" <?php if($permit_data) echo 'disabled'; ?> class="mr-1"> Leased</label>
                                        </div>
                                        <div x-show="proof === 'owned'" class="mt-2"><label class="block text-sm font-medium text-gray-700">Registered Name:</label><input type="text" name="proof_owned_reg_name" value="<?php echo $permit_data['proof_owned_reg_name'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                        <div x-show="proof === 'leased'" class="mt-2"><label class="block text-sm font-medium text-gray-700">Lessor's Name:</label><input type="text" name="proof_leased_lessor_name" value="<?php echo $permit_data['proof_leased_lessor_name'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 mt-4">
                                     <div><label class="block text-sm font-medium text-gray-700">Rent per Month:</label><input type="number" name="rent_per_month" step="0.01" value="<?php echo $permit_data['rent_per_month'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="₱"></div>
                                     <div><label class="block text-sm font-medium text-gray-700">Area in Sq. Meter:</label><input type="number" name="area_sq_meter" value="<?php echo $permit_data['area_sq_meter'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                     <div><label class="block text-sm font-medium text-gray-700">Real Property Tax Receipt No.:</label><input type="text" name="real_property_tax_receipt_no" value="<?php echo $permit_data['real_property_tax_receipt_no'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                </div>
                                <div class="flex space-x-6 mt-4">
                                    <label class="flex items-center"><input type="checkbox" name="has_barangay_clearance" value="1" <?php if($permit_data && $permit_data['has_barangay_clearance']) echo 'checked'; ?> <?php if($permit_data) echo 'disabled'; ?> class="mr-2 h-4 w-4"> Barangay Clearance</label>
                                    <label class="flex items-center"><input type="checkbox" name="has_public_liability_insurance" value="1" <?php if($permit_data && $permit_data['has_public_liability_insurance']) echo 'checked'; ?> <?php if($permit_data) echo 'disabled'; ?> class="mr-2 h-4 w-4"> Public Liability Insurance</label>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 mt-2">
                                    <div><label class="block text-sm font-medium text-gray-700">Insurance Company:</label><input type="text" name="insurance_company" value="<?php echo $permit_data['insurance_company'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Date of Insurance:</label><input type="date" name="insurance_date" value="<?php echo $permit_data['insurance_date'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                </div>
                            </div>
                             <!-- IV. Applicant Information -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Applicant Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div><label class="block text-sm font-medium text-gray-700">Name of Applicant:</label><input type="text" name="applicant_name" value="<?php echo $permit_data['applicant_name'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Position:</label><input type="text" name="applicant_position" value="<?php echo $permit_data['applicant_position'] ?? ''; ?>" <?php if($permit_data) echo 'readonly'; ?> class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label class="block text-sm font-medium text-gray-700">Signature:</label><div class="h-10 border-b border-gray-400 mt-1"></div></div>
                                </div>
                            </div>
                            
                            <!-- V. Office Remarks & Recommendation -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Office Remarks & Recommendation (For Internal Use)</h3>
                                <table class="w-full border-collapse text-sm">
                                    <thead>
                                        <tr class="bg-gray-200">
                                            <th class="table-cell w-1/4">OFFICE</th>
                                            <th class="table-cell w-1/2">REMARKS & RECOMMENDATION</th>
                                            <th class="table-cell w-1/8">SIGNATURE</th>
                                            <th class="table-cell w-1/8">DATE</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $offices = [
                                                "City Planning & Dev't Office", "Zoning", "City Engineer's Office", "City Fire Marshal's Office", "City Health Office",
                                                "Tourism & Cultural Office <br><small>(For Travel Agency & Tourist Inns Only)</small>",
                                                "TRAFFIC MANAGEMENT OFFICE", "CITY VETERINARIAN OFFICE <br><small>(For Mayor's Permit)</small>"
                                            ];
                                            foreach ($offices as $office):
                                        ?>
                                        <tr>
                                            <td class="table-cell font-semibold"><?php echo $office; ?></td>
                                            <td class="table-cell"><textarea class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" rows="2" readonly></textarea></td>
                                            <td class="table-cell"><div class="h-10 border-b border-gray-400"></div></td>
                                            <td class="table-cell"><input type="date" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- VI & VII -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- VI. Business Permit & License Office -->
                                <div class="border p-4 rounded-md">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">BPLO (Internal Use)</h3>
                                    <label class="block text-sm font-medium text-gray-700">Mayor's Permit received by:</label>
                                    <div class="mt-1"><label class="text-sm">Name in Print:</label><input type="text" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="mt-2"><label class="text-sm">Signature:</label><div class="h-8 border-b border-gray-400 mt-1"></div></div>
                                    <div class="mt-2"><label class="text-sm">Date:</label><input type="date" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <hr class="my-3">
                                    <label class="block text-sm font-medium text-gray-700">Business Plate No.:</label><input type="text" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <div class="mt-1"><label class="text-sm">Name in Print:</label><input type="text" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="mt-2"><label class="text-sm">Signature:</label><div class="h-8 border-b border-gray-400 mt-1"></div></div>
                                    <div class="mt-2"><label class="text-sm">Date:</label><input type="date" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                </div>

                                <!-- VII. Business Permit and Plate Claim Stub -->
                                <div class="border p-4 rounded-md bg-gray-50">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4 text-center border-b pb-2">Claim Stub: Business Permit & Plate</h3>
                                    <div class="mt-4"><label class="block text-sm font-medium text-gray-700">Business Trade Name:</label><input type="text" value="<?php echo $permit_data['business_trade_name'] ?? ''; ?>" class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                                    <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Mayor's Permit No.:</label><input type="text" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Date of Release:</label><input type="date" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Time Release:</label><input type="time" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Released By:</label><input type="text" readonly class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                </div>
                            </div>

                            <!-- VIII. Footer and Submission -->
                            <div class="flex justify-end pt-4 border-t mt-6 no-print">
                                <?php if (!$permit_data): ?>
                                    <a href="#" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md mr-2">Cancel</a>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Submit Application</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php if (isset($_GET['success'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Give a short delay for the content to render before printing
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
    <?php endif; ?>
</body>
</html> 
