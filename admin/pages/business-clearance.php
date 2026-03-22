<?php
/**
 * Barangay Business Clearance Certificate
 */

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

require_login();

// Get business ID from URL
$business_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$business_id) {
    die("Invalid business ID.");
}

// Fetch business and owner data
$sql = "SELECT b.*, r.first_name, r.last_name, r.middle_initial 
        FROM businesses b
        JOIN residents r ON b.resident_id = r.id
        WHERE b.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$business) {
    die("Business not found.");
}

$owner_full_name = htmlspecialchars($business['first_name'] . ' ' . $business['middle_initial'] . ' ' . $business['last_name']);
$business_name = htmlspecialchars($business['business_name']);
$business_address = htmlspecialchars($business['address']);
$business_type = htmlspecialchars($business['business_type']);
$date_issued = date('F j, Y');
$valid_until = date('F j, Y', strtotime('+1 year'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Business Clearance - <?php echo $business_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
        .certificate-body {
            font-family: 'Times New Roman', Times, serif;
        }
    </style>
</head>
<body class="bg-gray-200">
    <div class="max-w-4xl mx-auto my-10 p-4">
        <div class="no-print text-center mb-4">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                <i class="fas fa-print mr-2"></i> Print Certificate
            </button>
            <a href="monitoring-of-request.php?type=business" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md">
                Back to Requests
            </a>
        </div>
        <div id="certificate" class="bg-white p-12 border-4 border-blue-800 certificate-body relative">
            <div class="text-center">
                <p class="text-lg">Republic of the Philippines</p>
                <p class="text-lg">Province of [Province]</p>
                <p class="text-lg">Municipality of [Municipality]</p>
                <h1 class="text-3xl font-bold text-blue-800 mt-2">BARANGAY [Barangay Name]</h1>
                <p class="mt-4 text-sm">OFFICE OF THE PUNONG BARANGAY</p>
            </div>
            
            <h2 class="text-center text-4xl font-bold my-10">BARANGAY BUSINESS CLEARANCE</h2>
            
            <div class="text-lg leading-relaxed">
                <p class="mb-6">This clearance is hereby granted to:</p>
                
                <div class="grid grid-cols-3 gap-x-4 mb-4">
                    <p class="font-bold">Business Name:</p>
                    <p class="col-span-2"><?php echo $business_name; ?></p>
                </div>
                <div class="grid grid-cols-3 gap-x-4 mb-4">
                    <p class="font-bold">Owner/Proprietor:</p>
                    <p class="col-span-2"><?php echo $owner_full_name; ?></p>
                </div>
                <div class="grid grid-cols-3 gap-x-4 mb-4">
                    <p class="font-bold">Business Address:</p>
                    <p class="col-span-2"><?php echo $business_address; ?></p>
                </div>
                <div class="grid grid-cols-3 gap-x-4 mb-8">
                    <p class="font-bold">Type of Business:</p>
                    <p class="col-span-2"><?php echo $business_type; ?></p>
                </div>

                <p class="mb-6">This clearance is issued upon the request of the above-named person in connection with his/her application for a Business Permit for the year <?php echo date('Y'); ?>.</p>
                
                <p class="mb-8">This certification is valid until <span class="font-bold"><?php echo $valid_until; ?></span>.</p>

                <p>Issued this <span class="font-bold"><?php echo date('jS'); ?></span> day of <span class="font-bold"><?php echo date('F Y'); ?></span> at the Office of the Punong Barangay, Barangay [Barangay Name], [Municipality], [Province].</p>
            </div>
            
            <div class="mt-20 text-right">
                <div class="inline-block text-center">
                    <p class="font-bold text-lg">[PUNONG BARANGAY NAME]</p>
                    <hr class="border-black my-1">
                    <p>Punong Barangay</p>
                </div>
            </div>
            
            <div class="absolute bottom-10 left-10 text-xs text-gray-400">
                <p>Doc Stamp:</p>
                <p>OR No.:</p>
                <p>Date Issued: <?php echo $date_issued; ?></p>
            </div>
        </div>
    </div>
     <!-- Font Awesome Icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html> 