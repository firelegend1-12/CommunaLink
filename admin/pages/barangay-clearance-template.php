<?php
/**
 * Barangay Clearance Printable Template (Form Replica)
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = "Print Barangay Clearance";
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';

// Validate Request ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid request ID.";
    redirect_to('monitoring-of-request.php');
}
$request_id = $_GET['id'];

try {
    // Fetch request data along with resident info
    $sql = "SELECT dr.*, r.first_name, r.last_name, r.middle_initial, r.gender, r.address, r.date_of_birth, r.place_of_birth, r.civil_status, r.occupation 
            FROM document_requests dr
            JOIN residents r ON dr.resident_id = r.id
            WHERE dr.id = ? AND dr.document_type = 'Barangay Clearance'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = "Barangay Clearance request not found.";
        redirect_to('monitoring-of-request.php');
    }

    if (!$is_view_only && (($request['payment_status'] ?? 'Unpaid') !== 'Paid')) {
        $_SESSION['error_message'] = "Printing is only allowed after payment is completed.";
        redirect_to('monitoring-of-request.php');
    }

    $details = json_decode($request['details'], true);
    
    // Calculate age
    $age = 'N/A';
    if (!empty($request['date_of_birth'])) {
        $birthDate = new DateTime($request['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    redirect_to('monitoring-of-request.php');
}

$punong_barangay = $_SESSION['fullname'] ?? '_________________________';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        input[readonly], textarea[readonly] {
            background-color: #f3f4f6; /* Tailwind's gray-100 */
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
            Print Application
        </button>
<?php endif; ?>
    </div>

<?php if ($is_view_only): ?>
    <div class="no-print fixed top-4 right-4 z-50 bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded shadow text-xs font-bold uppercase tracking-wide">
        Viewing purpose only
    </div>
<?php endif; ?>

    <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg">
        <div class="text-center border-b pb-4 mb-6">
            <h2 class="text-lg font-bold">Barangay Pakiad Oton</h2>
            <p class="font-semibold">Iloilo City</p>
            <p class="font-semibold">OFFICE OF THE PUNONG BARANGAY</p>
            <h1 class="text-2xl font-bold mt-4 uppercase">APPLICATION FOR BARANGAY CLEARANCE (INDIVIDUAL)</h1>
        </div>
        
        <form>
            <!-- Application Type & Initial Details -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                <div class="col-span-2 flex items-center space-x-4">
                    <label class="font-medium">Application Type:</label>
                    <label class="flex items-center"><input type="radio" name="application_type" value="New" class="mr-1" <?= ($details['application_type'] ?? '') === 'New' ? 'checked' : '' ?> disabled> NEW</label>
                    <label class="flex items-center"><input type="radio" name="application_type" value="Renewal" class="mr-1" <?= ($details['application_type'] ?? '') === 'Renewal' ? 'checked' : '' ?> disabled> RENEWAL</label>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">No.:</label><input type="text" value="<?= htmlspecialchars($details['clearance_no'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                <div><label class="block text-sm font-medium text-gray-700">Date:</label><input type="date" value="<?= htmlspecialchars($details['clearance_date'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
            </div>

            <!-- Applicant's Personal Information -->
            <fieldset class="border p-4 rounded-md mb-6">
                <legend class="font-semibold px-2">Applicant's Personal Information</legend>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div><label class="block text-sm font-medium text-gray-700">Last Name (Apelyido):</label><input type="text" value="<?= htmlspecialchars($request['last_name']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">First Name (Pangalan):</label><input type="text" value="<?= htmlspecialchars($request['first_name']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">Middle Name (Gitnang Pangalan):</label><input type="text" value="<?= htmlspecialchars($request['middle_initial']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    
                    <div><label class="block text-sm font-medium text-gray-700">Sex:</label><input type="text" value="<?= htmlspecialchars($request['gender']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div class="col-span-2"><label class="block text-sm font-medium text-gray-700">Address:</label><textarea class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" rows="1" readonly><?= htmlspecialchars($request['address']) ?></textarea></div>

                    <div><label class="block text-sm font-medium text-gray-700">Date of Birth:</label><input type="date" value="<?= htmlspecialchars($request['date_of_birth']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">Age:</label><input type="number" value="<?= $age ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">Place of Birth:</label><input type="text" value="<?= htmlspecialchars($request['place_of_birth']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    
                    <div><label class="block text-sm font-medium text-gray-700">Occupation:</label><input type="text" value="<?= htmlspecialchars($request['occupation']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">Civil Status:</label><input type="text" value="<?= htmlspecialchars($request['civil_status']) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">Precinct No.:</label><input type="text" value="<?= htmlspecialchars($details['precinct_no'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>

                    <div><label class="block text-sm font-medium text-gray-700">Resident Since:</label><input type="text" value="<?= htmlspecialchars($details['resident_since'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div class="col-span-2"><label class="block text-sm font-medium text-gray-700">If employed, Name of Company:</label><input type="text" value="<?= htmlspecialchars($details['company_name'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    
                    <div class="col-span-3"><label class="block text-sm font-medium text-gray-700">Purpose of Clearance:</label><textarea class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly rows="2"><?= htmlspecialchars($request['purpose'] ?? '') ?></textarea></div>
                </div>
            </fieldset>

            <!-- References -->
            <fieldset class="border p-4 rounded-md mb-6">
                <legend class="font-semibold px-2">References</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="block text-sm font-medium text-gray-700">Reference (1):</label><input type="text" value="<?= htmlspecialchars($details['references'][0]['name'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div><label class="block text-sm font-medium text-gray-700">Reference (2):</label><input type="text" value="<?= htmlspecialchars($details['references'][1]['name'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    <div class="col-span-2"><label class="block text-sm font-medium text-gray-700">Tel. No.:</label><input type="tel" value="<?= htmlspecialchars($details['reference_tel_no'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                </div>
            </fieldset>
            
            <!-- CTC and Fees -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <fieldset class="border p-4 rounded-md">
                    <legend class="font-semibold px-2">Community Tax Certificate</legend>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700">CTC No.:</label><input type="text" value="<?= htmlspecialchars($details['ctc']['no'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                        <div><label class="block text-sm font-medium text-gray-700">Issued At:</label><input type="text" value="<?= htmlspecialchars($details['ctc']['issued_at'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                        <div><label class="block text-sm font-medium text-gray-700">Issued On:</label><input type="date" value="<?= htmlspecialchars($details['ctc']['issued_on'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                    </div>
                </fieldset>
                <fieldset class="border p-4 rounded-md">
                    <legend class="font-semibold px-2">Official Use / Fees</legend>
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700">Clearance Fee:</label><input type="text" value="<?= htmlspecialchars(number_format($details['fees']['clearance_fee'] ?? 0, 2)) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                        <div><label class="block text-sm font-medium text-gray-700">O.R. No.:</label><input type="text" value="<?= htmlspecialchars($details['fees']['or_no'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                        <div><label class="block text-sm font-medium text-gray-700">Date:</label><input type="date" value="<?= htmlspecialchars($details['fees']['or_date'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" readonly></div>
                        <div><label class="block text-sm font-medium text-gray-700">Remarks:</label><textarea class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" rows="1" readonly><?= htmlspecialchars($details['remarks'] ?? '') ?></textarea></div>
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
                <p class="font-bold uppercase"><?= htmlspecialchars($punong_barangay) ?></p>
                <p>PUNONG BARANGAY</p>
            </div>
        </form>
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