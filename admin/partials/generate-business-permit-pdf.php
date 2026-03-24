<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/simple_pdf.php';

require_login();

if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: ../pages/monitoring-of-request.php?type=business');
    exit;
}

$transaction_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($transaction_id <= 0) {
    $_SESSION['error_message'] = 'Invalid transaction ID.';
    header('Location: ../pages/monitoring-of-request.php?type=business');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT bt.*, r.first_name, r.last_name, r.address as resident_address, u.fullname as approved_by_name
                           FROM business_transactions bt
                           LEFT JOIN residents r ON bt.resident_id = r.id
                           LEFT JOIN users u ON bt.approved_by = u.id
                           WHERE bt.id = ? AND bt.status = 'APPROVED' LIMIT 1");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        $_SESSION['error_message'] = 'Transaction not found or not approved.';
        header('Location: ../pages/monitoring-of-request.php?type=business');
        exit;
    }

    $permit_number = $transaction['permit_number'] ?: ('BP-' . date('Y') . '-' . str_pad((string) $transaction_id, 4, '0', STR_PAD_LEFT));
    $owner_name = trim(($transaction['first_name'] ?? '') . ' ' . ($transaction['last_name'] ?? ''));

    $lines = [
        'Barangay: Pakiad, Oton, Iloilo',
        'Permit Number: ' . $permit_number,
        'Business Name: ' . ($transaction['business_name'] ?? 'N/A'),
        'Business Type: ' . ($transaction['business_type'] ?? 'N/A'),
        'Owner Name: ' . ($owner_name ?: 'N/A'),
        'Business Address: ' . ($transaction['address'] ?? 'N/A'),
        'Resident Address: ' . ($transaction['resident_address'] ?? 'N/A'),
        'Application Date: ' . (!empty($transaction['application_date']) ? date('F j, Y', strtotime($transaction['application_date'])) : 'N/A'),
        'Approval Date: ' . (!empty($transaction['approval_date']) ? date('F j, Y', strtotime($transaction['approval_date'])) : 'N/A'),
        'Expiration Date: ' . (!empty($transaction['permit_expiration_date']) ? date('F j, Y', strtotime($transaction['permit_expiration_date'])) : 'N/A'),
        'Approved By: ' . ($transaction['approved_by_name'] ?? ($_SESSION['fullname'] ?? 'Barangay Official')),
        '',
        'This permit is valid for one (1) year from issuance, subject to barangay rules and regulations.',
        'Generated on: ' . date('F j, Y g:i A')
    ];

    $filename = 'business-permit-' . $transaction_id . '.pdf';
    output_simple_text_pdf($filename, 'Business Permit Certificate', $lines);
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Failed to generate PDF.';
    header('Location: ../pages/generate-business-permit.php?id=' . $transaction_id);
    exit;
}
