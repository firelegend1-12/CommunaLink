<?php
/**
 * Barangay Business Clearance Certificate Template
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = 'Barangay Business Clearance';
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';
$transaction = null;
$error = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $request_id = $_GET['id'];
    try {
        $sql = "SELECT bt.*, r.first_name, r.last_name, bp.official_receipt_no, bp.or_date 
                FROM business_transactions bt
                JOIN residents r ON bt.resident_id = r.id
                LEFT JOIN business_permits bp ON bt.permit_id = bp.id
                WHERE bt.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$request_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            $error = "Business transaction not found.";
        }

        if ($transaction && !$is_view_only && (($transaction['payment_status'] ?? 'Unpaid') !== 'Paid')) {
            $error = "Printing is only allowed after payment is completed.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        log_activity('ERROR', $error, $_SESSION['user_id'] ?? null);
    }
} else {
    // This allows the page to be viewed as a blank template for printing empty copies.
    $transaction = [
        'business_name' => '_________________________',
        'owner_name' => '_________________________',
        'address' => '_________________________',
        'business_type' => '_________________________',
    ];
}

if ($error) {
    $_SESSION['error_message'] = $error;
    redirect_to('monitoring-of-request.php');
}

$punong_barangay = $_SESSION['fullname'] ?? '_________________________';
$year = date('Y');
$valid_until = 'December 31, ' . $year;
$day_issued = date('jS');
$month_issued = date('F');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .printable-area { margin: 0; padding: 1rem; border: none; box-shadow: none; }
        }
<?php if ($is_view_only): ?>
        @media print {
            body * { visibility: hidden !important; }
            body::before {
                content: 'VIEW ONLY - Printing disabled';
                visibility: visible !important;
                display: block;
                text-align: center;
                margin-top: 30vh;
                font-size: 24px;
                font-weight: 700;
            }
        }
<?php endif; ?>
        .certificate-body {
            font-family: 'Times New Roman', Times, serif;
        }
        .placeholder {
            border-bottom: 1px dotted #999;
            padding: 0 4px;
            display: inline-block;
            min-width: 200px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-200">
    <div class="max-w-4xl mx-auto my-10 p-4">
        <div class="no-print text-center mb-4">
            <a href="monitoring-of-request.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md">
                <i class="fas fa-arrow-left mr-2"></i> Back to Monitoring
            </a>
<?php if (!$is_view_only): ?>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                <i class="fas fa-print mr-2"></i> Print Certificate
            </button>
<?php endif; ?>
        </div>
<?php if ($is_view_only): ?>
        <div class="no-print text-center mb-4">
            <span class="inline-block bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded text-xs font-bold uppercase tracking-wide">Viewing purpose only</span>
        </div>
<?php endif; ?>
        <div id="certificate" class="printable-area bg-white p-12 border-4 border-blue-800 certificate-body relative">
            <div class="text-center">
                <p class="text-lg">Republic of the Philippines</p>
                <p class="text-lg">Province of Iloilo</p>
                <p class="text-lg">Municipality of Oton</p>
                <h1 class="text-3xl font-bold text-blue-800 mt-2">BARANGAY PAKIAD</h1>
                <p class="mt-4 text-sm">OFFICE OF THE PUNONG BARANGAY</p>
            </div>
            
            <h2 class="text-center text-4xl font-bold my-10">BARANGAY BUSINESS CLEARANCE</h2>
            
            <div class="text-lg leading-relaxed">
                <p class="mb-6">This clearance is hereby granted to:</p>
                
                <div class="grid grid-cols-3 gap-x-4 mb-4">
                    <p class="font-bold">Business Name:</p>
                    <p class="col-span-2"><span class="placeholder"><?= htmlspecialchars($transaction['business_name']) ?></span></p>
                </div>
                <div class="grid grid-cols-3 gap-x-4 mb-4">
                    <p class="font-bold">Owner/Proprietor:</p>
                    <p class="col-span-2"><span class="placeholder"><?= htmlspecialchars($transaction['owner_name']) ?></span></p>
                </div>
                <div class="grid grid-cols-3 gap-x-4 mb-4">
                    <p class="font-bold">Business Address:</p>
                    <p class="col-span-2"><span class="placeholder"><?= htmlspecialchars($transaction['address']) ?></span></p>
                </div>
                <div class="grid grid-cols-3 gap-x-4 mb-8">
                    <p class="font-bold">Type of Business:</p>
                    <p class="col-span-2"><span class="placeholder"><?= htmlspecialchars($transaction['business_type']) ?></span></p>
                </div>

                <p class="mb-6">This clearance is issued upon the request of the above-named person in connection with his/her application for a Business Permit for the year <span class="font-bold"><?= $year ?></span>.</p>
                
                <p class="mb-8">This certification is valid until <span class="font-bold placeholder"><?= $valid_until ?></span>.</p>

                <p>Issued this <span class="font-bold placeholder"><?= $day_issued ?></span> day of <span class="font-bold placeholder"><?= $month_issued ?></span> at the Office of the Punong Barangay, Barangay Pakiad, Oton, Iloilo.</p>
            </div>
            
            <div class="mt-20 text-right">
                <div class="inline-block text-center">
                    <p class="font-bold text-lg uppercase border-b-2 border-black pb-1 px-8"><?= htmlspecialchars($punong_barangay) ?></p>
                    <p class="pt-1">Punong Barangay</p>
                </div>
            </div>
            
            <div class="absolute bottom-10 left-10 text-xs text-gray-400">
                <p>Doc Stamp: <span class="placeholder"></span></p>
                <p>OR No.: <span class="placeholder"><?= htmlspecialchars($transaction['official_receipt_no'] ?? '') ?></span></p>
                <p>Date Issued: <span class="placeholder"><?= date('m/d/Y') ?></span></p>
            </div>
        </div>
    </div>
</body>
<?php if ($is_view_only): ?>
<script>
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        alert('Printing is disabled in view-only mode.');
    }
});

window.print = function() {
    alert('Printing is disabled in view-only mode.');
};
</script>
<?php endif; ?>
</html> 