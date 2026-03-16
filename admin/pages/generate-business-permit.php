<?php
/**
 * Generate Business Permit Page
 * - Generates official business permits with permit numbers and expiration dates
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = "Generate Business Permit - CommuniLink";

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transaction_id) {
    $_SESSION['error_message'] = "No transaction ID provided.";
    header("Location: business-transactions.php");
    exit;
}

try {
    // Fetch transaction details
    $stmt = $pdo->prepare("
        SELECT bt.*, r.first_name, r.last_name, r.address as resident_address, 
               u.fullname as approved_by_name
        FROM business_transactions bt
        LEFT JOIN residents r ON bt.resident_id = r.id
        LEFT JOIN users u ON bt.approved_by = u.id
        WHERE bt.id = ? AND bt.status = 'APPROVED'
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        $_SESSION['error_message'] = "Transaction not found or not approved.";
        header("Location: business-transactions.php");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: business-transactions.php");
    exit;
}

// Handle permit generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_permit'])) {
    try {
        // Generate permit number if not exists
        if (empty($transaction['permit_number'])) {
            $permit_number = 'BP-' . date('Y') . '-' . str_pad($transaction_id, 4, '0', STR_PAD_LEFT);
            $expiration_date = date('Y-m-d', strtotime('+1 year'));
            
            // Update transaction with permit details
            $update_stmt = $pdo->prepare("
                UPDATE business_transactions 
                SET permit_number = ?, permit_expiration_date = ?, approval_date = NOW(), approved_by = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$permit_number, $expiration_date, $_SESSION['user_id'], $transaction_id]);
            
            // Update or create business record
            $business_stmt = $pdo->prepare("
                INSERT INTO businesses (resident_id, business_name, business_type, address, status, 
                                      permit_number, permit_expiration_date, approval_date, approved_by)
                VALUES (?, ?, ?, ?, 'Active', ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE 
                permit_number = VALUES(permit_number),
                permit_expiration_date = VALUES(permit_expiration_date),
                approval_date = VALUES(approval_date),
                approved_by = VALUES(approved_by),
                status = 'Active'
            ");
            $business_stmt->execute([
                $transaction['resident_id'],
                $transaction['business_name'],
                $transaction['business_type'],
                $transaction['address'],
                $permit_number,
                $expiration_date,
                $_SESSION['user_id']
            ]);
            
            $transaction['permit_number'] = $permit_number;
            $transaction['permit_expiration_date'] = $expiration_date;
        }
        
        // Log the permit generation
        log_activity_db(
            $pdo,
            'generate_permit',
            'business_transaction',
            $transaction_id,
            "Business Permit generated: {$transaction['permit_number']} for {$transaction['business_name']}",
            null,
            null
        );
        
        $_SESSION['success_message'] = "Business permit generated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error generating permit: " . $e->getMessage();
    }
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
        @media print {
            .no-print { display: none !important; }
            .permit-container { 
                border: 2px solid #000; 
                padding: 20px; 
                margin: 20px;
                background: white;
            }
            .permit-header { 
                border-bottom: 2px solid #000; 
                padding-bottom: 10px; 
                margin-bottom: 20px;
            }
        }
        .permit-container {
            border: 2px solid #333;
            padding: 30px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            position: relative;
        }
        .permit-header {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        .permit-number {
            background: #1e40af;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin: 10px 0;
        }
        .expiration-badge {
            background: #dc2626;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .qr-placeholder {
            width: 100px;
            height: 100px;
            border: 2px dashed #666;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            margin: 10px auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1">
            <header class="bg-white shadow-sm z-10 no-print">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Generate Business Permit</h1>
                        
                        <div class="flex items-center space-x-4">
                            <a href="business-transactions.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
                            </a>
                            
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                    <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                    <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                        <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                    </div>
                                </button>
                                <div x-show="open" @click.away="open = false" x-cloak
                                     class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                    <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                    <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md no-print">
                            <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md no-print">
                            <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center mb-6 no-print">
                        <div class="flex space-x-4">
                            <?php if (empty($transaction['permit_number'])): ?>
                                <form method="POST" class="inline">
                                    <button type="submit" name="generate_permit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                        <i class="fas fa-certificate mr-2"></i> Generate Permit
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-800 px-4 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-check mr-2"></i> Permit Generated
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                                <i class="fas fa-print mr-2"></i> Print Permit
                            </button>
                            <button onclick="downloadPDF()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm">
                                <i class="fas fa-download mr-2"></i> Download PDF
                            </button>
                        </div>
                    </div>

                    <!-- Business Permit Document -->
                    <div class="permit-container" id="permit-document">
                        <!-- Header -->
                        <div class="permit-header">
                            <div class="flex justify-between items-start">
                                <div class="text-left">
                                    <img src="../../assets/images/barangay-logo.png" alt="Barangay Logo" class="h-16 w-auto">
                                </div>
                                <div class="text-center flex-1">
                                    <h1 class="text-2xl font-bold text-gray-900">Republic of the Philippines</h1>
                                    <h2 class="text-xl font-semibold text-gray-800">Province of Iloilo</h2>
                                    <h3 class="text-lg font-semibold text-gray-700">Municipality of Oton</h3>
                                    <h4 class="text-lg font-bold text-gray-900">BARANGAY PAKIAD</h4>
                                    <h5 class="text-xl font-bold text-blue-800 mt-2">BUSINESS PERMIT</h5>
                                </div>
                                <div class="text-right">
                                    <div class="qr-placeholder">
                                        <span class="text-xs text-gray-500">QR Code</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Permit Number and Status -->
                        <div class="text-center mb-6">
                            <?php if (!empty($transaction['permit_number'])): ?>
                                <div class="permit-number">
                                    Permit No: <?php echo htmlspecialchars($transaction['permit_number']); ?>
                                </div>
                                <div class="expiration-badge mt-2">
                                    Valid Until: <?php echo date('M d, Y', strtotime($transaction['permit_expiration_date'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-gray-500 italic">Permit number will be generated when approved</div>
                            <?php endif; ?>
                        </div>

                        <!-- Business Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Business Information</h3>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Business Name:</label>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($transaction['business_name']); ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Business Type:</label>
                                        <p class="text-gray-900"><?php echo htmlspecialchars($transaction['business_type']); ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Business Address:</label>
                                        <p class="text-gray-900"><?php echo htmlspecialchars($transaction['address']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Owner Information</h3>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Owner Name:</label>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Residential Address:</label>
                                        <p class="text-gray-900"><?php echo htmlspecialchars($transaction['resident_address']); ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Application Date:</label>
                                        <p class="text-gray-900"><?php echo date('F j, Y', strtotime($transaction['application_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b pb-2">Terms and Conditions</h3>
                            <div class="text-sm text-gray-700 space-y-2">
                                <p>1. This permit is valid for one (1) year from the date of issuance.</p>
                                <p>2. The business must comply with all local ordinances and regulations.</p>
                                <p>3. Regular inspections may be conducted to ensure compliance.</p>
                                <p>4. This permit must be displayed prominently at the business location.</p>
                                <p>5. Any changes in business operations must be reported to the barangay office.</p>
                                <p>6. Renewal must be applied for at least 30 days before expiration.</p>
                            </div>
                        </div>

                        <!-- Signatures -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                            <div class="text-center">
                                <div class="border-t-2 border-gray-400 pt-2 mb-2" style="width: 200px; margin: 0 auto;"></div>
                                <p class="text-sm font-medium text-gray-900">Business Owner</p>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></p>
                            </div>
                            
                            <div class="text-center">
                                <div class="border-t-2 border-gray-400 pt-2 mb-2" style="width: 200px; margin: 0 auto;"></div>
                                <p class="text-sm font-medium text-gray-900">Barangay Captain</p>
                                <p class="text-xs text-gray-600">Barangay Pakiad</p>
                                <p class="text-xs text-gray-600">Official Seal</p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-8 pt-4 border-t border-gray-300">
                            <p class="text-xs text-gray-600">
                                This permit is issued by the authority of the Barangay Council of Pakiad, 
                                Municipality of Oton, Province of Iloilo, Philippines.
                            </p>
                            <p class="text-xs text-gray-500 mt-2">
                                Generated on: <?php echo date('F j, Y \a\t g:i A'); ?> | 
                                Generated by: <?php echo htmlspecialchars($_SESSION['fullname']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // This would integrate with a PDF generation library
            alert('PDF download feature will be implemented with a PDF library like jsPDF or server-side PDF generation.');
        }
    </script>
</body>
</html> 