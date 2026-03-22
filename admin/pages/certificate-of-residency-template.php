<?php
/**
 * Printable Certificate of Residency Template
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';

// Check if an ID is provided in the URL
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No request ID specified.";
    header("Location: monitoring-of-request.php");
    exit();
}

$request_id = $_GET['id'];

try {
    // Fetch the document request from the database
    $stmt = $pdo->prepare("SELECT * FROM document_requests WHERE id = ? AND document_type = 'Certificate of Residency'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = "Certificate of Residency request not found.";
        header("Location: monitoring-of-request.php");
        exit();
    }

    if (!$is_view_only && (($request['payment_status'] ?? 'Unpaid') !== 'Paid')) {
        $_SESSION['error_message'] = "Printing is only allowed after payment is completed.";
        header("Location: monitoring-of-request.php");
        exit();
    }

    // Decode the JSON details
    $details = json_decode($request['details'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON details: " . json_last_error_msg());
    }

    // Format the date
    $issued_val = (!empty($details['issued_on'])) ? $details['issued_on'] : date('Y-m-d');
    $date_issued = new DateTime($issued_val);
    $day = $date_issued->format('jS'); // Day with suffix (e.g., 1st, 2nd)
    $month = $date_issued->format('F');
    $year = $date_issued->format('Y');

} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching the certificate data.";
    header("Location: monitoring-of-request.php");
    exit();
}

$page_title = "Print Certificate of Residency";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
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
        .certificate-body { font-family: 'Times New Roman', Times, serif; }
        .placeholder-logo {
            width: 80px;
            height: 80px;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.8rem;
            text-align: center;
        }
        .underline-dotted { border-bottom: 1px dotted #000; padding: 0 0.25rem; font-weight: bold; }
    </style>
</head>
<body class="bg-gray-200">

    <div class="max-w-4xl mx-auto my-10 p-4">
        <div class="no-print text-center mb-6">
<?php if (!$is_view_only): ?>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md shadow-md">
                <i class="fas fa-print mr-2"></i> Print Certificate
            </button>
<?php endif; ?>
            <a href="monitoring-of-request.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md shadow-md">
                <i class="fas fa-arrow-left mr-2"></i> Back to Monitoring
            </a>
        </div>
<?php if ($is_view_only): ?>
        <div class="no-print text-center mb-4">
            <span class="inline-block bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded text-xs font-bold uppercase tracking-wide">Viewing purpose only</span>
        </div>
<?php endif; ?>
        
        <div id="certificate" class="bg-white p-12 border-4 border-gray-800 certificate-body relative">
            
            <!-- Header -->
            <div class="flex justify-between items-start mb-4">
                <div class="placeholder-logo">Seal of Philippines</div>
                <div class="text-center">
                    <p>Republic of the Philippines</p>
                    <p>City of Iloilo</p>
                    <p class="font-bold">BARANGAY PAKIAD</p>
                    <p class="text-xs mt-2">Office No. (033) ______________ / CP No. (+639) ______________</p>
                </div>
                <div class="placeholder-logo">iKonek Logo</div>
            </div>
            <p class="text-right text-sm">Tracking No. __________________</p>

            <!-- Title -->
            <h1 class="text-center text-3xl font-bold my-10">CERTIFICATE OF RESIDENCY</h1>

            <!-- Main Content -->
            <div class="text-lg leading-loose">
                <p class="mb-6 indent-8">
                    This is to certify that <span class="underline-dotted"><?php echo htmlspecialchars($details['applicant_name']); ?></span> of legal age, single, Filipino citizen, is the <strong>present occupant</strong> of <span class="underline-dotted"><?php echo htmlspecialchars($details['sitio']); ?></span>, Brgy. Pakiad, <span class="underline-dotted"><?php echo htmlspecialchars($details['district']); ?></span>, Iloilo City, which property is owned by <span class="underline-dotted"><?php echo htmlspecialchars($details['property_owner']); ?></span>.
                </p>
                <p class="mb-6 indent-8">
                    Based on records of this office, the above-named individual belongs to the:
                    <div class="pl-16 mt-2 flex space-x-8">
                        <span class="flex items-center">
                            <div class="w-5 h-5 border border-black mr-2 flex items-center justify-center">
                                <?php if (in_array('low income bracket', $details['status'])): ?>&#10003;<?php endif; ?>
                            </div>
                            low income bracket
                        </span>
                        <span class="flex items-center">
                             <div class="w-5 h-5 border border-black mr-2 flex items-center justify-center">
                                <?php if (in_array('informal settler', $details['status'])): ?>&#10003;<?php endif; ?>
                            </div>
                            informal settler
                        </span>
                    </div>
                    Further, he/she has no derogatory or criminal records filed in this barangay.
                </p>
                <p class="mb-8 indent-8">
                    This certification is being issued upon the request of the above-named person intended for compliance with the requirements of the <strong>iKonek ELECTRIFICATION PROGRAM OF MAYOR JERRY P. TRENAS</strong> and <strong>MORE ELECTRIC AND POWER CORP.</strong>
                </p>
                <p class="indent-8">
                    Issued this <span class="underline-dotted"><?php echo $day; ?></span> day of <span class="underline-dotted"><?php echo $month; ?></span>, <span class="underline-dotted"><?php echo $year; ?></span>, Barangay Pakiad, District of <span class="underline-dotted"><?php echo htmlspecialchars($details['district']); ?></span>, Iloilo City.
                </p>
            </div>

            <!-- Signature -->
            <div class="mt-24 text-right">
                <div class="inline-block text-center">
                    <p class="font-bold text-lg border-b-2 border-black px-12"><?php echo htmlspecialchars($_SESSION['fullname'] ?? '[PUNONG BARANGAY NAME]'); ?></p>
                    <p>Punong Barangay</p>
                </div>
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