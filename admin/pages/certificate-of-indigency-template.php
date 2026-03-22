<?php
/**
 * Certificate of Indigency Printable Template
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = "Print Certificate of Indigency";
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid request ID.";
    redirect_to('monitoring-of-request.php');
}
$request_id = $_GET['id'];

try {
    $sql = "SELECT dr.*, r.first_name, r.last_name, r.address, r.civil_status, r.middle_initial 
            FROM document_requests dr
            JOIN residents r ON dr.resident_id = r.id
            WHERE dr.id = ? AND dr.document_type = 'Certificate of Indigency'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = "Certificate request not found.";
        redirect_to('monitoring-of-request.php');
    }

    if (!$is_view_only && (($request['payment_status'] ?? 'Unpaid') !== 'Paid')) {
        $_SESSION['error_message'] = "Printing is only allowed after payment is completed.";
        redirect_to('monitoring-of-request.php');
    }

    $details = json_decode($request['details'], true);

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    redirect_to('monitoring-of-request.php');
}

$punong_barangay = $_SESSION['fullname'] ?? '[PUNONG BARANGAY NAME]';
$recipient_name = $request['first_name'] . ($request['middle_initial'] ? ' ' . $request['middle_initial'] . '.' : '') . ' ' . $request['last_name'];
$civil_status = $request['civil_status'] ?? 'N/A';
$day_issued = date('d');
$month_issued = date('F');
$year_issued = date('Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .printable-area { margin: 0; padding: 2rem; border: none; box-shadow: none; }
            .page-break { page-break-after: always; }
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
    </style>
</head>
<body class="bg-gray-200">

    <div class="no-print fixed top-4 left-4 z-50 flex space-x-2">
        <a href="monitoring-of-request.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow">
            Back
        </a>
<?php if (!$is_view_only): ?>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
            Print Certificate
        </button>
<?php endif; ?>
    </div>

<?php if ($is_view_only): ?>
    <div class="no-print fixed top-4 right-4 z-50 bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded shadow text-xs font-bold uppercase tracking-wide">
        Viewing purpose only
    </div>
<?php endif; ?>

    <div class="printable-area max-w-4xl mx-auto my-8 p-16 bg-white shadow-lg certificate-body text-black flex flex-col justify-between" style="min-height: 10in;">
        
        <div>
            <div class="text-center mb-16">
                <p class="text-sm">REPUBLIC OF THE PHILIPPINES</p>
                <p class="text-sm">Iloilo City</p>
                <p class="font-bold text-md">Barangay Pakiad Oton</p>
                <h2 class="text-xl font-bold mt-4">Office of the Punong Barangay</h2>
            </div>

            <h1 class="text-center text-4xl font-bold uppercase my-16 tracking-widest">CERTIFICATE OF INDIGENCY</h1>

            <div class="mt-8 space-y-6 text-lg">
                <p class="font-bold">TO WHOM IT MAY CONCERN:</p>
                
                <p class="indent-16 leading-relaxed text-justify">
                    This is to CERTIFY that Mr./Ms. <strong class="font-bold underline px-2"><?= htmlspecialchars($recipient_name) ?></strong>, 
                    of legal age, <strong class="font-bold underline px-2"><?= htmlspecialchars($civil_status) ?></strong>, 
                    Filipino Citizen and a resident of Barangay Pakiad Oton, Iloilo City,
                    belongs to the Indigent Families of this barangay having an annual income not exceeding the Regional Poverty Threshold (RPT) of Php 169, 824.00 per anum as determined by the National Economic Development Authority (NEDA).
                </p>
                
                <p class="indent-16 leading-relaxed text-justify">
                    This CERTIFICATION is issued upon the request of the above-mentioned individual for whatever legal purpose/s it may best serve him or her.
                </p>
                
                <p class="indent-16 leading-relaxed">
                    ISSUED this <strong class="font-bold underline px-2"><?= $day_issued ?></strong> day of 
                    <strong class="font-bold underline px-2"><?= $month_issued ?></strong>, 
                    <strong class="font-bold underline px-2"><?= $year_issued ?></strong>
                    at Barangay Pakiad Oton, Iloilo City.
                </p>
            </div>
        </div>

        <div class="flex-grow"></div>

        <div>
            <div class="flex justify-end mb-16">
                <div class="text-center w-80">
                    <p class="font-bold uppercase text-lg border-b-2 border-black pb-1"><?= htmlspecialchars($punong_barangay) ?></p>
                    <p class="text-sm pt-1">Punong Barangay</p>
                </div>
            </div>

            <div class="text-xs text-gray-600">
                <h4 class="font-bold">SPECIAL NOTE ON THE DRY SEAL:</h4>
                <ul class="list-disc list-inside mt-1">
                    <li>Place the Dry Seal IF AVAILABLE.</li>
                    <li>If the "NOT VALID WITHOUT SEAL" is present but no dry seal has been placed then the Certificate is NOT VALID AND NOT ACCEPTED.</li>
                </ul>
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