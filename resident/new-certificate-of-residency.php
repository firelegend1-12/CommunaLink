<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}
require_role('resident');

$page_title = "Certificate of Residency Request";
$user_id = $_SESSION['user_id'];

// Get Resident Details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile.";
    redirect_to('account.php');
}

$full_name = htmlspecialchars(normalize_document_text($resident['first_name'] . ' ' . $resident['last_name']));
$age = '';
if (!empty($resident['date_of_birth'])) {
    $birthDate = new DateTime($resident['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}
$document_civil_status = resident_document_civil_status_label($resident['civil_status'] ?? '');
$document_locality = resident_document_locality_label($resident['address'] ?? '');

// Auto-fill issuance date
$day_issued = date('j');
$month_issued = date('F');
$year_issued = date('Y');

$document_print_layout = true;
require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-purple-600 hover:text-purple-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <!-- Breadcrumb -->
    <nav class="text-sm text-gray-500 mb-4">
        <a href="dashboard.php" class="hover:text-purple-700">Dashboard</a>
        <span>/</span>
        <a href="barangay-services.php" class="hover:text-purple-700">Services</a>
        <span>/</span>
        <span class="text-gray-800 font-semibold">Certificate of Residency</span>
    </nav>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-purple-700 to-purple-900 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Certificate of Residency</h1>
            <p class="opacity-80 text-sm mt-1">Review the certificate details below before submitting your request.</p>
        </div>

        <div class="p-8 md:p-12">
            <form action="partials/submit-residency.php" method="POST" id="residency-form" class="space-y-8">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" id="day_issued" name="day_issued" value="<?= $day_issued ?>">
                <input type="hidden" id="month_issued" name="month_issued" value="<?= $month_issued ?>">
                <input type="hidden" id="year_issued" name="year_issued" value="<?= $year_issued ?>">
                <input type="hidden" id="document_civil_status" name="document_civil_status" value="<?= htmlspecialchars($document_civil_status, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="document_locality" name="document_locality" value="<?= htmlspecialchars($document_locality, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Applicant Details -->
                <div class="bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">I. <i class="fas fa-user text-purple-500 mr-2"></i>Applicant Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Name of Applicant</label>
                            <input type="text" id="applicant_name" name="applicant_name" value="<?= $full_name ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Age</label>
                            <input type="text" id="applicant_age" name="age" value="<?= htmlspecialchars((string) $age) ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium" readonly>
                        </div>
                    </div>
                </div>

                <!-- Residency Details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">II. <i class="fas fa-home text-purple-500 mr-2"></i>Residency Details <span class="text-red-500">*</span></h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Years of Residency / Since <span class="text-red-500">*</span></label>
                            <input type="text" id="duration" name="duration" required placeholder="e.g. 10 years or Since 2014" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Certificate Preview -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">III. <i class="fas fa-file-alt text-purple-500 mr-2"></i>Certificate Preview</h3>
                    <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto">
                        <?php
                        $svg_path = '../Certificate of Residency.svg';
                        $svg = file_get_contents($svg_path);
                        if ($svg !== false) {
                            // Name (line ~345)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                $svg
                            );
                            // Age (line ~347)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="341.8 ',
                                '<text id="field-age" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="341.8 ',
                                $svg
                            );
                            // Duration (line ~408)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 348.10835)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="570.0187',
                                '<text id="field-duration" xml:space="preserve" transform="matrix(.75 0 0 .75 72 348.10835)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="570.0187',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="455.46513 464.12919 467.97895 477.61604 487.2531 491.10289 500.73997 505.55427 519.9888 529.62588 535.39627 541.1666 545.01638 554.65347 564.2905 569.10488 581.6187 585.46847 595.1055 604.7426">single/married/widow</tspan></text>',
                                '<text id="field-civil-status" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="455.46513 464.12919 467.97895 477.61604 487.2531 491.10289 500.73997 505.55427 519.9888 529.62588 535.39627 541.1666 545.01638 554.65347 564.2905 569.10488 581.6187 585.46847 595.1055 604.7426">single/married/widow</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="188.80736 199.39208 209.02916 218.66625">Zone</tspan></text>',
                                '<text id="field-locality" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="188.80736 199.39208 209.02916 218.66625">Zone</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="233.11765 237.93196 242.74628 247.5606 252.37491 257.1892 262.00355 266.81788 271.63218 276.44648 281.2608 291.2274">I/II/III/IV,</tspan></text>',
                                '<text id="field-locality-options" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="233.11765 237.93196 242.74628 247.5606 252.37491 257.1892 262.00355 266.81788 271.63218 276.44648 281.2608 291.2274">I/II/III/IV,</tspan></text>',
                                $svg
                            );
                            // Day (line ~462)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="126.018939',
                                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="126.018939',
                                $svg
                            );
                            // Month (line ~468)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="211.76277',
                                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="211.76277',
                                $svg
                            );
                            echo '<div style="display:inline-block;">' . $svg . '</div>';
                        } else {
                            echo '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Issuance Note -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">IV. <i class="fas fa-info-circle text-purple-500 mr-2"></i>Issuance Note</h3>
                    <div class="bg-purple-50 p-5 rounded-xl border border-purple-100 text-purple-800">
                        <p class="text-sm leading-relaxed font-medium">
                            <i class="fas fa-info-circle mr-2"></i> The issuance date and official signatures will be applied by the Barangay Administration upon approval.
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-8 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold shadow-lg shadow-purple-500/30 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const fieldMap = {
        'field-name': 'applicant_name',
        'field-age': 'applicant_age',
        'field-duration': 'duration',
        'field-civil-status': 'document_civil_status',
        'field-locality': 'document_locality',
        'field-day': 'day_issued',
        'field-month': 'month_issued'
    };
    const fieldGroups = {
        'field-locality': ['field-locality-options']
    };

    function getFieldValue(id) {
        const inputId = fieldMap[id];
        if (!inputId) return '';
        const el = document.getElementById(inputId);
        return el ? window.CommunaLinkDocumentSvg.normalizeText(el.value) : '';
    }

    function recomputeLayout() {
        window.CommunaLinkDocumentSvg.syncLayout({
            fieldIds: Object.keys(fieldMap),
            fieldGroups: fieldGroups,
            getFieldValue: getFieldValue
        });
    }

    function initPreview() {
        recomputeLayout();
        Object.keys(fieldMap).forEach(function(id) {
            const input = document.getElementById(fieldMap[id]);
            if (input) {
                input.addEventListener('input', recomputeLayout);
                input.addEventListener('change', recomputeLayout);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initPreview, 100);
    });
})();

document.getElementById('residency-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');

    // Client-side validation
    const requiredFields = this.querySelectorAll('input[required], select[required], textarea[required]');
    let firstInvalid = null;
    requiredFields.forEach(function(field) {
        if (!field.value || field.value.trim() === '') {
            field.classList.add('border-red-500');
            if (!firstInvalid) firstInvalid = field;
        } else {
            field.classList.remove('border-red-500');
        }
    });
    if (firstInvalid) {
        firstInvalid.focus();
        residentShowToast('Please fill in all required fields.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(this);

    fetch('partials/submit-residency.php', {
        method: 'POST',
        body: formData
    })
    .then(response => residentParseJsonResponse(response))
    .then(data => {
        if(data.success) {
            window.location.href = 'my-document-requests.php?success=1';
        } else {
            residentShowToast(residentRequestErrorMessage(data, 'Unable to submit request.'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
        }
    })
    .catch(err => {
        console.error(err);
        residentShowToast('A network error occurred.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
