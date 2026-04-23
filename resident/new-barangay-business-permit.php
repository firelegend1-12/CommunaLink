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

$page_title = "Barangay Business Permit Request";
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile. Please update your profile first.";
    redirect_to('account.php');
}

$full_name = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);
$default_address = htmlspecialchars($resident['address'] ?? '');

require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <nav aria-label="Breadcrumb" class="mb-3 text-sm text-gray-500 flex items-center gap-2">
        <a href="dashboard.php" class="hover:text-amber-700">Dashboard</a>
        <span>/</span>
        <a href="barangay-services.php" class="hover:text-amber-700">Services</a>
        <span>/</span>
        <span class="text-gray-700 font-semibold">Barangay Business Permit</span>
    </nav>
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-amber-700 hover:text-amber-900 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Application for Barangay Business Permit</h1>
            <p class="opacity-90 text-sm mt-1">Tell us about your business. The barangay will review and issue your permit.</p>
        </div>

        <div class="p-8 md:p-10">
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-lg mb-6 text-sm">
                <i class="fas fa-info-circle mr-1"></i>
                Use this form if you are applying for an <strong>official Barangay Business Permit</strong>. For the simpler Business Clearance, please use the <a href="new-barangay-business-clearance.php" class="underline font-semibold">Business Clearance form</a>.
            </div>

            <form action="partials/submit-business-permit-request.php" method="POST" id="business-permit-form" class="space-y-8">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" id="day_issued" name="day_issued" value="<?= date('j') ?>">
                <input type="hidden" id="month_issued" name="month_issued" value="<?= date('F') ?>">
                <input type="hidden" id="year_issued" name="year_issued" value="<?= date('Y') ?>">

                <!-- Applicant (auto-filled) -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">I. <i class="fas fa-user-tie text-amber-500 mr-2"></i>Business Owner</h3>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Owner's Name</label>
                        <input type="text" id="owner_name" name="owner_name" value="<?= $full_name ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium" readonly>
                        <p class="text-xs text-gray-500 mt-1">Pulled from your resident profile.</p>
                    </div>
                </div>

                <!-- Business Details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">II. <i class="fas fa-store text-amber-500 mr-2"></i>Business Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Business Name <span class="text-red-500">*</span></label>
                            <input type="text" id="business_name" name="business_name" required placeholder="e.g. Juan's Sari-Sari Store" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Type / Line of Business <span class="text-red-500">*</span></label>
                            <input type="text" id="business_type" name="business_type" required placeholder="e.g. Retail, Food, Services" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Application Type <span class="text-red-500">*</span></label>
                            <div class="flex gap-4 mt-1">
                                <label class="flex items-center gap-2 cursor-pointer bg-amber-50 px-4 py-2 rounded-lg border border-amber-100 flex-1">
                                    <input type="radio" name="application_type" value="New" checked class="text-amber-600 focus:ring-amber-500 h-4 w-4">
                                    <span class="font-medium text-amber-800">New</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer bg-amber-50 px-4 py-2 rounded-lg border border-amber-100 flex-1">
                                    <input type="radio" name="application_type" value="Renewal" class="text-amber-600 focus:ring-amber-500 h-4 w-4">
                                    <span class="font-medium text-amber-800">Renewal</span>
                                </label>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Business Location / Address <span class="text-red-500">*</span></label>
                            <input type="text" id="business_address" name="business_address" value="<?= $default_address ?>" required placeholder="Complete address where the business operates" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Additional Remarks (Optional)</label>
                            <textarea name="remarks" rows="2" placeholder="Anything else the barangay should know (capital, number of employees, etc.)" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Certificate Preview -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">III. <i class="fas fa-file-alt text-amber-500 mr-2"></i>Permit Preview</h3>
                    <div class="printable-area bg-amber-50/60 p-4 rounded-xl border border-amber-100 shadow-inner overflow-auto" style="max-height: 600px; text-align: center;">
                        <?php
                        $svg_path = '../Barangay Business Permit.svg';
                        $svg = file_get_contents($svg_path);
                        if ($svg !== false) {
                            // Header: Name of Business (line ~271)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 298.6829)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                                '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 298.6829)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                                $svg
                            );
                            // Header: Name of Owner (line ~279)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 315.87394)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                                '<text id="field-owner" xml:space="preserve" transform="matrix(.75 0 0 .75 72 315.87394)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                                $svg
                            );
                            // Header: Type/Line of Business (line ~287)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 333.06498)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="171.54647 180.21053',
                                '<text id="field-type" xml:space="preserve" transform="matrix(.75 0 0 .75 72 333.06498)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="171.54647 180.21053',
                                $svg
                            );
                            // Header: Business Location (line ~291)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 350.256)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.778668" x="67.89957 79.45729',
                                '<text id="field-location" xml:space="preserve" transform="matrix(.75 0 0 .75 72 350.256)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.778668" x="67.89957 79.45729',
                                $svg
                            );
                            // Paragraph: business name again (line ~334)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="188.63808 197.30214',
                                '<text id="field-name2" xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="188.63808 197.30214',
                                $svg
                            );
                            // Paragraph: owner again (line ~340)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="473.09687 481.76094',
                                '<text id="field-owner2" xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="473.09687 481.76094',
                                $svg
                            );
                            // Paragraph: location again (line ~346)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="56.045576" x="16.837403 25.501465',
                                '<text id="field-location2" xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="56.045576" x="16.837403 25.501465',
                                $svg
                            );
                            // Footer: Day (line ~425)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475 132.71157',
                                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475 132.71157',
                                $svg
                            );
                            // Footer: Month (line ~431)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349 219.81755',
                                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349 219.81755',
                                $svg
                            );
                            echo '<div style="display:inline-block;">' . $svg . '</div>';
                        } else {
                            echo '<p class="text-red-500 text-center py-8">Error: Could not load permit template.</p>';
                        }
                        ?>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">IV. <i class="fas fa-info-circle text-amber-500 mr-2"></i>Issuance Note</h3>
                    <div class="bg-amber-50 p-5 rounded-xl border border-amber-100 text-amber-800">
                        <p class="text-sm leading-relaxed font-medium">
                            <i class="fas fa-info-circle mr-2"></i> Issuance date, official receipt, and signatures will be applied by the Barangay Administration upon approval.
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-4 pt-6 mt-4 border-t border-gray-100">
                    <a href="barangay-services.php" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl font-semibold transition">Cancel</a>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold shadow-lg shadow-amber-500/30 transition flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const fieldMap = {
        'field-name': 'business_name',
        'field-name2': 'business_name',
        'field-owner': 'owner_name',
        'field-owner2': 'owner_name',
        'field-type': 'business_type',
        'field-location': 'business_address',
        'field-location2': 'business_address',
        'field-day': 'day_issued',
        'field-month': 'month_issued'
    };

    function getFieldValue(id) {
        const inputId = fieldMap[id];
        if (!inputId) return '';
        const el = document.getElementById(inputId);
        return el ? el.value : '';
    }

    function recomputeLayout() {
        document.querySelectorAll('svg text').forEach(function(el) {
            if (!el.dataset.origTransform && el.hasAttribute('transform')) {
                el.dataset.origTransform = el.getAttribute('transform');
            }
        });

        Object.keys(fieldMap).forEach(function(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const tspan = el.querySelector('tspan');
            if (!tspan) return;
            if (!tspan.dataset.origText) {
                tspan.dataset.origText = tspan.textContent;
            }
            if (!tspan.dataset.origX) {
                tspan.dataset.origX = tspan.getAttribute('x') || '0';
            }
            const value = getFieldValue(id);
            const firstX = tspan.dataset.origX.split(/\s+/)[0];
            if (!value || String(value).trim() === '') {
                tspan.textContent = tspan.dataset.origText;
                tspan.setAttribute('x', tspan.dataset.origX);
            } else {
                const m = tspan.dataset.origText.match(/^([^_]*?)(_+)(.*)$/);
                const prefix = m ? m[1] : '';
                const suffix = m ? m[3] : '';
                tspan.textContent = prefix + String(value) + suffix;
                tspan.setAttribute('x', firstX);
            }
        });

        document.querySelectorAll('svg text[data-orig-transform]').forEach(function(el) {
            el.setAttribute('transform', el.dataset.origTransform);
        });

        requestAnimationFrame(function() {
            Object.keys(fieldMap).forEach(function(id) {
                const el = document.getElementById(id);
                if (!el) return;
                const tspan = el.querySelector('tspan');
                if (!tspan || !tspan.dataset.origX) return;
                const value = getFieldValue(id);
                if (!value || String(value).trim() === '') return;
                const xCoords = tspan.dataset.origX.split(/\s+/).map(parseFloat).filter(function(n) { return !isNaN(n); });
                if (xCoords.length === 0) return;
                const firstX = xCoords[0];
                const lastX = xCoords[xCoords.length - 1];
                const charWidth = xCoords.length > 1 ? (xCoords[1] - xCoords[0]) : 8.66;
                const blankWidth = (lastX - firstX) + charWidth;
                let actualWidth = 0;
                try { actualWidth = tspan.getComputedTextLength(); } catch(e) {}
                const shift = blankWidth - actualWidth;
                if (Math.abs(shift) <= 0.5) return;
                const origLineTransform = el.dataset.origTransform || el.getAttribute('transform');
                const lineY = tspan.getAttribute('y');
                const parent = el.parentElement;
                if (!parent) return;
                Array.from(parent.querySelectorAll('text')).forEach(function(t) {
                    if (t === el) return;
                    const ts = t.querySelector('tspan');
                    if (!ts) return;
                    const tBaseTransform = t.dataset.origTransform || t.getAttribute('transform') || '';
                    if (tBaseTransform !== origLineTransform) return;
                    if (ts.getAttribute('y') !== lineY) return;
                    const tOrigX = ts.dataset.origX || ts.getAttribute('x') || '0';
                    const tFirstX = parseFloat(tOrigX.split(/\s+/)[0]);
                    if (isNaN(tFirstX) || tFirstX <= firstX) return;
                    const currentTransform = t.getAttribute('transform') || tBaseTransform;
                    t.setAttribute('transform', currentTransform + ' translate(' + (-shift) + ' 0)');
                });
            });
        });
    }

    function initPreview() {
        recomputeLayout();
        Object.keys(fieldMap).forEach(function(id) {
            const input = document.getElementById(fieldMap[id]);
            if (input) {
                input.addEventListener('input', recomputeLayout);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initPreview, 100);
    });
})();

document.getElementById('business-permit-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(this);

    fetch('partials/submit-business-permit-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'my-requests.php?success=1';
        } else {
            residentShowToast("Error: " + data.error, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
        }
    })
    .catch(err => {
        console.error(err);
        residentShowToast('A network error occurred.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
    });
});
</script>

<?php require_once 'partials/footer.php'; ?>
