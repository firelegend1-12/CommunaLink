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

$page_title = "Barangay Clearance Request";
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
$document_gender = resident_document_gender_label($resident['gender'] ?? '');
$document_civil_status = resident_document_civil_status_label($resident['civil_status'] ?? '');
$document_locality = resident_document_locality_label($resident['address'] ?? '');

$document_print_layout = true;
require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <nav aria-label="Breadcrumb" class="mb-3 text-sm text-gray-500 flex items-center gap-2">
        <a href="dashboard.php" class="hover:text-blue-700">Dashboard</a>
        <span>/</span>
        <a href="barangay-services.php" class="hover:text-blue-700">Services</a>
        <span>/</span>
        <span class="text-gray-700 font-semibold">Barangay Clearance</span>
    </nav>
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Barangay Clearance</h1>
            <p class="opacity-80 text-sm mt-1">Request your official barangay clearance certificate.</p>
        </div>

        <div class="p-8">
            <form action="partials/submit-clearance.php" method="POST" id="clearance-form" class="space-y-8">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" name="day_issued" id="day_issued" value="<?= date('jS') ?>">
                <input type="hidden" name="month_issued" id="month_issued" value="<?= date('F') ?>">
                <input type="hidden" name="document_gender" id="document_gender" value="<?= htmlspecialchars($document_gender, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="document_civil_status" id="document_civil_status" value="<?= htmlspecialchars($document_civil_status, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="document_locality" id="document_locality" value="<?= htmlspecialchars($document_locality, ENT_QUOTES, 'UTF-8') ?>">

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

                <!-- Certificate Details -->
                <div class="bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">II. Certificate Details</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Purpose <span class="text-red-500">*</span></label>
                            <input type="text" id="purpose" name="purpose" required placeholder="e.g. Employment, Business Permit, School Requirement" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Live SVG Preview -->
                <div class="mt-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Live Preview</h3>
                    <div id="svg-preview-container" class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto">
                        <?php
                        $svg_path = '../Barangay Forms (1) (1).svg';
                        $svg = file_get_contents($svg_path);
                        if ($svg !== false) {
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman"><tspan y="15.5598959" x="238.0417',
                                '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman"><tspan y="15.5598959" x="238.0417',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="537.8542 546.1823 554.51046 562.83859">',
                                '<text id="field-age" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="537.8542 546.1823 554.51046 562.83859">',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 394.6116)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="0 8.328125',
                                '<text id="field-purpose" xml:space="preserve" transform="matrix(.75 0 0 .75 72 394.6116)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="0 8.328125',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="29.612015 41.640626 49.96875 54.59639 61.989229 66.61687 71.24451 78.637348 90.665958 98.99408 103.62172 111.01456">male/female,</tspan></text>',
                                '<text id="field-gender" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="29.612015 41.640626 49.96875 54.59639 61.989229 66.61687 71.24451 78.637348 90.665958 98.99408 103.62172 111.01456">male/female,</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="119.34268 125.82463 130.45227 138.7804 147.10852 151.73616">single</tspan></text>',
                                '<text id="field-civil-status" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="119.34268 125.82463 130.45227 138.7804 147.10852 151.73616">single</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="163.29306">/</tspan></text>',
                                '<text id="field-civil-status-slash" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="163.29306">/</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="172.08477 184.11338 192.4415 198.92345 205.4054 210.03304 217.42588 225.754">married/</tspan></text>',
                                '<text id="field-civil-status-married" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="172.08477 184.11338 192.4415 198.92345 205.4054 210.03304 217.42588 225.754">married/</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="234.5457 241.02765 248.42049 256.7486 265.07673 271.5587 279.8868 284.51448 291.9073 300.2354">separated/</tspan></text>',
                                '<text id="field-civil-status-separated" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="234.5457 241.02765 248.42049 256.7486 265.07673 271.5587 279.8868 284.51448 291.9073 300.2354">separated/</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="309.0271 320.1367 324.76435 333.09248 341.4206 352.53016 357.1578 364.55067">widow/er</tspan></text>',
                                '<text id="field-civil-status-widow" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="309.0271 320.1367 324.76435 333.09248 341.4206 352.53016 357.1578 364.55067">widow/er</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="477.289 486.55244 494.88056 503.20869">Zone</tspan></text>',
                                '<text id="field-locality" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="477.289 486.55244 494.88056 503.20869">Zone</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="514.76559">I</tspan></text>',
                                '<text id="field-locality-i" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="514.76559">I</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="524.4763 529.10397 534.65066">/II</tspan></text>',
                                '<text id="field-locality-ii" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="524.4763 529.10397 534.65066">/II</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="544.3613">/</tspan></text>',
                                '<text id="field-locality-slash" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="544.3613">/</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="553.153 558.6997 564.24636 569.79299">III/</tspan></text>',
                                '<text id="field-locality-iii" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="553.153 558.6997 564.24636 569.79299">III/</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="578.5847 584.13137">IV</tspan></text>',
                                '<text id="field-locality-iv" xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="578.5847 584.13137">IV</tspan></text>',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="72.16353 80.49165 88.81978 97.1479">',
                                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="72.16353 80.49165 88.81978 97.1479">',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="154.97307 163.3012',
                                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="154.97307 163.3012',
                                $svg
                            );
                            echo '<div style="display:inline-block;">' . $svg . '</div>';
                        } else {
                            echo '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
                        }
                        ?>
                    </div>
                </div>

                <div class="text-gray-500 italic text-sm text-center pt-2">
                    Note: The issuance date and signatures will be applied by the Barangay Administration upon approval.
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-4 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 transition flex items-center gap-2">
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
        'field-purpose': 'purpose',
        'field-gender': 'document_gender',
        'field-civil-status': 'document_civil_status',
        'field-locality': 'document_locality',
        'field-day': 'day_issued',
        'field-month': 'month_issued'
    };
    const fieldGroups = {
        'field-civil-status': ['field-civil-status-slash', 'field-civil-status-married', 'field-civil-status-separated', 'field-civil-status-widow'],
        'field-locality': ['field-locality-i', 'field-locality-ii', 'field-locality-slash', 'field-locality-iii', 'field-locality-iv']
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

        // Attach live listeners
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

document.getElementById('clearance-form').addEventListener('submit', function(e) {
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
    
    fetch('partials/submit-clearance.php', {
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
