<?php
/**
 * Handler for the New Business Permit Application Form
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitization and data retrieval
    $data = [];
    foreach ($_POST as $key => $value) {
        // Handle empty strings for optional fields, converting them to NULL
        $data[$key] = ($value === '') ? null : htmlspecialchars($value);
    }

    // Handle checkboxes - they are not sent if unchecked
    $data['has_barangay_clearance'] = isset($_POST['has_barangay_clearance']) ? 1 : 0;
    $data['has_public_liability_insurance'] = isset($_POST['has_public_liability_insurance']) ? 1 : 0;

    $pdo->beginTransaction();

    try {
        // --- 1. Get Resident Info ---
        $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
        if (empty($resident_id)) {
            throw new Exception("A resident must be selected.");
        }

        $stmt_find = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name, address FROM residents WHERE id = ?");
        $stmt_find->execute([$resident_id]);
        $resident = $stmt_find->fetch(PDO::FETCH_ASSOC);

        if (!$resident) {
            throw new Exception("Selected resident not found in the database.");
        }

        // Add resident's full name to the data array for the business_permits table
        $data['taxpayer_name'] = $resident['full_name'];

        // --- 2. Save the full permit application ---
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
        
        // PDOStatement::execute expects an array of values, not keys. Let's create an indexed array.
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

        // --- 3. Create a corresponding transaction for monitoring ---
        $trans_stmt = $pdo->prepare(
            "INSERT INTO business_transactions (resident_id, business_name, business_type, owner_name, address, transaction_type, status) VALUES (?, ?, ?, ?, ?, 'New Permit', 'Pending')"
        );
        $trans_stmt->execute([
            $resident_id,
            $data['business_trade_name'] ?? 'N/A',
            $data['main_line_business'] ?? 'N/A',
            $resident['full_name'],
            $data['taxpayer_address'] ?? $resident['address']
        ]);

        $pdo->commit();

        $_SESSION['success_message'] = "Business permit application saved successfully! It is now pending in Monitoring.";
        header("Location: ../pages/new-barangay-business-clearance.php?success=true&id=" . $last_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        // Redirect back to the form with an error
        header("Location: ../pages/new-barangay-business-clearance.php?error=true");
        exit();
    }

} else {
    // Not a POST request
    header("Location: ../pages/new-barangay-business-clearance.php");
    exit();
} 