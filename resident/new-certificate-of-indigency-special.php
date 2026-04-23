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

$page_title = "Certificate of Indigency (Special) Request";
$user_id = $_SESSION['user_id'];

// Get Resident Details (the requester)
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile. Please update your profile first.";
    redirect_to('account.php');
}

$full_name = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);

require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <nav aria-label="Breadcrumb" class="mb-3 text-sm text-gray-500 flex items-center gap-2">
        <a href="dashboard.php" class="hover:text-rose-700">Dashboard</a>
        <span>/</span>
        <a href="barangay-services.php" class="hover:text-rose-700">Services</a>
        <span>/</span>
        <span class="text-gray-700 font-semibold">Cert. of Indigency (Special)</span>
    </nav>
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-rose-600 hover:text-rose-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-rose-600 to-rose-800 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Certificate of Indigency (Special)</h1>
            <p class="opacity-80 text-sm mt-1">For medical / burial assistance of a patient or deceased family member.</p>
        </div>

        <div class="p-8 md:p-10">
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-lg mb-6 text-sm">
                <i class="fas fa-info-circle mr-1"></i>
                This special certificate is issued on behalf of a <strong>patient</strong> or a <strong>deceased</strong> family member to support medical or burial assistance claims.
            </div>

            <form action="partials/submit-indigency-special.php" method="POST" id="indigency-special-form" class="space-y-8">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" name="requester_name" id="requester_name" value="<?= $full_name ?>">
                <input type="hidden" name="day_issued" id="day_issued" value="<?= date('jS') ?>">
                <input type="hidden" name="month_issued" id="month_issued" value="<?= date('F') ?>">

                <!-- Requester (auto) -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">I. <i class="fas fa-user text-rose-500 mr-2"></i>Requester's Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Name of Requester</label>
                            <input type="text" value="<?= $full_name ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Relation to Patient/Deceased <span class="text-red-500">*</span></label>
                            <input type="text" id="relation" name="relation" required placeholder="e.g. Son, Daughter, Spouse, Parent" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition">
                        </div>
                    </div>
                </div>

                <!-- Beneficiary details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">II. <i class="fas fa-user-injured text-rose-500 mr-2"></i>Patient / Deceased Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name of Patient / Deceased <span class="text-red-500">*</span></label>
                            <input type="text" id="beneficiary_name" name="beneficiary_name" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Case Type <span class="text-red-500">*</span></label>
                            <select name="case_type" required class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition">
                                <option value="">-- Select --</option>
                                <option value="Patient">Patient (Medical Assistance)</option>
                                <option value="Deceased">Deceased (Burial Assistance)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Purpose <span class="text-red-500">*</span></label>
                            <input type="text" name="purpose" required placeholder="e.g. Hospital bill assistance, Burial assistance" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Additional Remarks (Optional)</label>
                            <textarea name="remarks" rows="3" placeholder="Hospital name, diagnosis, funeral parlor, or any info that helps the barangay process your request." class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition"></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-gray-500 italic text-sm text-center pt-2">
                    Note: The issuance date and signatures will be applied by the Barangay Administration upon approval.
                </div>

                <!-- Live SVG Preview -->
                <div class="mt-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Live Preview</h3>
                    <div id="svg-preview-container" class="printable-area bg-white p-4 rounded-xl border border-gray-200 shadow-sm overflow-auto" style="max-height: 600px; text-align: center;">
                        <?php
                        $svg_path = '../Certificate of Indigency (Special).svg';
                        $svg = file_get_contents($svg_path);
                        if ($svg !== false) {
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="467.0144',
                                '<text id="field-requester" xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="467.0144',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="115.56888',
                                '<text id="field-relation" xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="115.56888',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
                                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
                                $svg
                            );
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
                                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
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
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold shadow-lg shadow-rose-500/30 transition flex items-center gap-2">
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
        'field-name': 'beneficiary_name',
        'field-requester': 'requester_name',
        'field-relation': 'relation',
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
                const suffix = tspan.dataset.origText.replace(/^[_\s]+/, '');
                tspan.textContent = String(value) + suffix;
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
                const charWidth = xCoords.length > 1 ? (xCoords[1] - xCoords[0]) : 8.33;
                const blankWidth = (lastX - firstX) + charWidth;
                let actualWidth = 0;
                try { actualWidth = tspan.getComputedTextLength(); } catch(e) {}
                const shift = blankWidth - actualWidth;
                if (shift <= 0.5) return;
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

document.getElementById('indigency-special-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(this);

    fetch('partials/submit-indigency-special.php', {
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
