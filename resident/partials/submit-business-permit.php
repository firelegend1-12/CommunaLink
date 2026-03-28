<?php
session_start();
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!csrf_validate()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

// Emulate the exact structure used by admin's new-business-permit-handler.php
$data = [];
foreach ($_POST as $key => $value) {
    $data[$key] = ($value === '') ? null : htmlspecialchars($value);
}

// Handle Checkboxes
$data['has_barangay_clearance'] = isset($_POST['has_barangay_clearance']) ? 1 : 0;
$data['has_public_liability_insurance'] = isset($_POST['has_public_liability_insurance']) ? 1 : 0;

// Admin only fields that are left null/empty on resident creation:
$data['date_of_application'] = date('Y-m-d');
$data['business_account_no'] = null;
$data['official_receipt_no'] = null;
$data['or_date'] = null;
$data['amount_paid'] = null;

$pdo->beginTransaction();

try {
    $posted_resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);

    $resident_lookup_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_lookup_stmt->execute([$_SESSION['user_id']]);
    $resolved_resident_id = (int) ($resident_lookup_stmt->fetchColumn() ?: 0);

    if ($resolved_resident_id <= 0) {
        throw new Exception("Resident profile not found for the logged-in user.");
    }

    if (!empty($posted_resident_id) && (int) $posted_resident_id !== $resolved_resident_id) {
        throw new Exception("Unauthorized submission profile mismatch.");
    }

    $resident_id = $resolved_resident_id;

    $sql = "INSERT INTO business_permits (
        date_of_application, business_account_no, official_receipt_no, or_date, amount_paid,
        taxpayer_name, taxpayer_tel_no, taxpayer_fax_no, taxpayer_address, capital, taxpayer_barangay_no,
        business_trade_name, business_tel_no,
        comm_address_building, comm_address_no, comm_address_street, comm_address_barangay_no,
        dti_reg_no, sec_reg_no, num_employees,
        main_line_business, other_line_business, main_products_services, other_products_services,
        ownership_type, proof_of_ownership, proof_owned_reg_name, proof_leased_lessor_name,
        rent_per_month, area_sq_meter, real_property_tax_receipt_no,
        has_barangay_clearance, has_public_liability_insurance, insurance_company, insurance_date,
        applicant_name, applicant_position
    ) VALUES (
        :date_of_application, :business_account_no, :official_receipt_no, :or_date, :amount_paid,
        :taxpayer_name, :taxpayer_tel_no, :taxpayer_fax_no, :taxpayer_address, :capital, :taxpayer_barangay_no,
        :business_trade_name, :business_tel_no,
        :comm_address_building, :comm_address_no, :comm_address_street, :comm_address_barangay_no,
        :dti_reg_no, :sec_reg_no, :num_employees,
        :main_line_business, :other_line_business, :main_products_services, :other_products_services,
        :ownership_type, :proof_of_ownership, :proof_owned_reg_name, :proof_leased_lessor_name,
        :rent_per_month, :area_sq_meter, :real_property_tax_receipt_no,
        :has_barangay_clearance, :has_public_liability_insurance, :insurance_company, :insurance_date,
        :applicant_name, :applicant_position
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        'date_of_application' => $data['date_of_application'],
        'business_account_no' => $data['business_account_no'],
        'official_receipt_no' => $data['official_receipt_no'],
        'or_date' => $data['or_date'],
        'amount_paid' => $data['amount_paid'],
        'taxpayer_name' => $data['taxpayer_name'],
        'taxpayer_tel_no' => $data['taxpayer_tel_no'],
        'taxpayer_fax_no' => $data['taxpayer_fax_no'],
        'taxpayer_address' => $data['taxpayer_address'],
        'capital' => $data['capital'],
        'taxpayer_barangay_no' => $data['taxpayer_barangay_no'],
        'business_trade_name' => $data['business_trade_name'],
        'business_tel_no' => $data['business_tel_no'],
        'comm_address_building' => $data['comm_address_building'],
        'comm_address_no' => $data['comm_address_no'],
        'comm_address_street' => $data['comm_address_street'],
        'comm_address_barangay_no' => $data['comm_address_barangay_no'],
        'dti_reg_no' => $data['dti_reg_no'],
        'sec_reg_no' => $data['sec_reg_no'],
        'num_employees' => $data['num_employees'],
        'main_line_business' => $data['main_line_business'],
        'other_line_business' => $data['other_line_business'],
        'main_products_services' => $data['main_products_services'],
        'other_products_services' => $data['other_products_services'],
        'ownership_type' => $data['ownership_type'],
        'proof_of_ownership' => $data['proof_of_ownership'],
        'proof_owned_reg_name' => $data['proof_owned_reg_name'],
        'proof_leased_lessor_name' => $data['proof_leased_lessor_name'],
        'rent_per_month' => $data['rent_per_month'],
        'area_sq_meter' => $data['area_sq_meter'],
        'real_property_tax_receipt_no' => $data['real_property_tax_receipt_no'],
        'has_barangay_clearance' => $data['has_barangay_clearance'],
        'has_public_liability_insurance' => $data['has_public_liability_insurance'],
        'insurance_company' => $data['insurance_company'],
        'insurance_date' => $data['insurance_date'],
        'applicant_name' => $data['applicant_name'],
        'applicant_position' => $data['applicant_position'],
    ];

    $stmt->execute($params);
    $last_id = $pdo->lastInsertId();

    // Transactions table insertion
    $trans_stmt = $pdo->prepare(
        "INSERT INTO business_transactions (resident_id, permit_id, business_name, business_type, owner_name, address, transaction_type, status) VALUES (?, ?, ?, ?, ?, ?, 'New Permit', 'Pending')"
    );
    $trans_stmt->execute([
        $resident_id,
        $last_id,
        $data['business_trade_name'] ?? 'N/A',
        $data['main_line_business'] ?? 'N/A',
        $data['taxpayer_name'],
        $data['taxpayer_address']
    ]);

    $pdo->commit();
    log_activity('Document Request', "New Business Permit requested natively by resident.", $_SESSION['user_id']);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Business Permit Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}
