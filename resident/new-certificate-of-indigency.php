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

$page_title = "Certificate of Indigency Request";
$user_id = $_SESSION['user_id'];

// Get Resident Details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile. Please update your profile first.";
    redirect_to('account.php');
}

// Calculate age
$age = '';
if (!empty($resident['date_of_birth'])) {
    $birthDate = new DateTime($resident['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

$full_name = htmlspecialchars(normalize_document_text($resident['first_name'] . ' ' . $resident['last_name']));
$document_civil_status = resident_document_civil_status_label($resident['civil_status'] ?? '');

$document_print_layout = true;
require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <nav aria-label="Breadcrumb" class="mb-3 text-sm text-gray-500 flex items-center gap-2">
        <a href="dashboard.php" class="hover:text-emerald-700">Dashboard</a>
        <span>/</span>
        <a href="barangay-services.php" class="hover:text-emerald-700">Services</a>
        <span>/</span>
        <span class="text-gray-700 font-semibold">Certificate of Indigency</span>
    </nav>
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-emerald-600 hover:text-emerald-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Certificate of Indigency</h1>
            <p class="opacity-80 text-sm mt-1">Request your official certificate of indigency.</p>
        </div>

        <div class="p-8">
            <form action="partials/submit-indigency.php" method="POST" id="indigency-form" class="space-y-8">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" name="day_issued" id="day_issued" value="<?= date('jS') ?>">
                <input type="hidden" name="month_issued" id="month_issued" value="<?= date('F') ?>">
                <input type="hidden" name="civil_status" id="document_civil_status" value="<?= htmlspecialchars($document_civil_status, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Applicant Details (Pre-Filled) -->
                <div class="bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">I. Applicant Details</h3>
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

                <div class="text-gray-500 italic text-sm text-center pt-2">
                    Note: The issuance date and official signatures will be applied by the Barangay Administration upon approval.
                </div>

                <!-- Live SVG Preview -->
                <div class="mt-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Live Preview</h3>
                    <div id="svg-preview-container" class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto">
                        <?php
                        $svg_path = '../Certificate of Indigency.svg';
                        $svg = file_get_contents($svg_path);
                        if ($svg !== false) {
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 299.79566)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 299.79566)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 299.79566)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="544.1788',
                                '<text id="field-age" xml:space="preserve" transform="matrix(.75 0 0 .75 72 299.79566)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="544.1788',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 322.21876)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="79.939579 88.60364 92.4534 102.090488 111.72757 115.57733 125.21442 130.02873 144.4632 154.10028 159.87068 165.64109 169.49085 179.12793 188.76502 193.57933 206.09316 209.94292 219.58 229.21709">single/married/widow</tspan></text>',
                                '<text id="field-civil-status" xml:space="preserve" transform="matrix(.75 0 0 .75 72 322.21876)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="79.939579 88.60364 92.4534 102.090488 111.72757 115.57733 125.21442 130.02873 144.4632 154.10028 159.87068 165.64109 169.49085 179.12793 188.76502 193.57933 206.09316 209.94292 219.58 229.21709">single/married/widow</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 456.75733)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
                                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 456.75733)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 456.75733)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
                                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 456.75733)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
                                $svg
                            );
                            echo '<div style="display:inline-block;">' . $svg . '</div>';
                        } else {
                            echo '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-4 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-bold shadow-lg shadow-teal-500/30 transition flex items-center gap-2">
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
        'field-civil-status': 'document_civil_status',
        'field-day': 'day_issued',
        'field-month': 'month_issued'
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
            fieldGroups: {},
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

document.getElementById('indigency-form').addEventListener('submit', function(e) {
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
    
    fetch('partials/submit-indigency.php', {
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
