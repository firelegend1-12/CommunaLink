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

$page_title = "Barangay Business Clearance Request";
$user_id = $_SESSION['user_id'];

// Get Resident Details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    $_SESSION['error_message'] = "Could not find your resident profile.";
    redirect_to('account.php');
}

$full_name = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);
$default_address = htmlspecialchars($resident['address'] ?? '');

require_once 'partials/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="barangay-services.php" class="text-teal-600 hover:text-teal-800 flex items-center gap-2 font-medium transition">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
    </div>

    <!-- Breadcrumb -->
    <nav class="text-sm text-gray-500 mb-4">
        <a href="dashboard.php" class="hover:text-teal-700">Dashboard</a>
        <span>/</span>
        <a href="barangay-services.php" class="hover:text-teal-700">Services</a>
        <span>/</span>
        <span class="text-gray-800 font-semibold">Barangay Business Clearance</span>
    </nav>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-teal-600 to-cyan-700 px-8 py-6 text-white text-center">
            <h1 class="text-2xl font-bold uppercase tracking-wide">Application for Barangay Business Clearance</h1>
            <p class="opacity-90 text-sm mt-1">Provide the business details needed for your clearance request.</p>
        </div>

        <div class="p-8 md:p-12">
            <form action="partials/submit-business-permit.php" method="POST" id="business-form" class="space-y-8">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="resident_id" value="<?= $resident['id'] ?>">
                <input type="hidden" id="day_issued" name="day_issued" value="<?= date('j') ?>">
                <input type="hidden" id="month_issued" name="month_issued" value="<?= date('F') ?>">
                <input type="hidden" id="year_issued" name="year_issued" value="<?= date('Y') ?>">

                <div class="bg-teal-50 border border-teal-200 text-teal-800 p-4 rounded-lg text-sm">
                    <i class="fas fa-info-circle mr-1"></i>
                    This resident form now follows the simplified business clearance request format instead of the old business permit application.
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">I. <i class="fas fa-user-tie text-teal-500 mr-2"></i>Owner Information</h3>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Owner / Proprietor</label>
                        <input type="text" id="owner_name" name="owner_name" value="<?= $full_name ?>" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-medium" readonly>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">II. <i class="fas fa-store text-teal-500 mr-2"></i>Business Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Business Name <span class="text-red-500">*</span></label>
                            <input type="text" id="business_name" name="business_name" required placeholder="e.g. Jeff's Sari-Sari Store" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Nature / Type of Business <span class="text-red-500">*</span></label>
                            <input type="text" id="business_type" name="business_type" required placeholder="e.g. Retail, Pharmacy, Food Service" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Business Location <span class="text-red-500">*</span></label>
                            <input type="text" id="business_address" name="business_address" value="<?= $default_address ?>" required placeholder="Where the business operates" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">III. <i class="fas fa-file-alt text-teal-500 mr-2"></i>Certificate Preview</h3>
                    <div class="printable-area bg-teal-50/60 p-4 rounded-xl border border-teal-100 shadow-inner overflow-auto" style="max-height: 600px; text-align: center;">
                        <?php
                        $svg_path = '../Barangay Business Clearance.svg';
                        $svg = file_get_contents($svg_path);
                        if ($svg !== false) {
                            // Business Name (line ~368)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="46.079755" x="188.63808',
                                '<text id="field-business-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="46.079755" x="188.63808',
                                $svg
                            );
                            // Owner Name (line ~374)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="46.079755" x="473.09687',
                                '<text id="field-owner" xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="46.079755" x="473.09687',
                                $svg
                            );
                            // Location (line ~380)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="75.97721" x="16.837403',
                                '<text id="field-location" xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="75.97721" x="16.837403',
                                $svg
                            );
                            // Day (line ~459)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 458.2318)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475',
                                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 458.2318)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475',
                                $svg
                            );
                            // Month (line ~465)
                            $svg = str_replace(
                                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 458.2318)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349',
                                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 458.2318)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349',
                                $svg
                            );
                            echo '<div style="display:inline-block;">' . $svg . '</div>';
                        } else {
                            echo '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
                        }
                        ?>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">IV. <i class="fas fa-info-circle text-teal-500 mr-2"></i>Issuance Note</h3>
                    <div class="bg-teal-50 p-5 rounded-xl border border-teal-100 text-teal-800">
                        <p class="text-sm leading-relaxed font-medium">
                            <i class="fas fa-info-circle mr-2"></i> The issuance date, OR number, and official signatures will be applied by the Barangay Administration upon approval.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 pt-6 mt-8 border-t border-gray-100">
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
        'field-business-name': 'business_name',
        'field-owner': 'owner_name',
        'field-location': 'business_address',
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
                const suffix = tspan.dataset.origText.replace(/^[\_\s]+/, '');
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
                const charWidth = xCoords.length > 1 ? (xCoords[1] - xCoords[0]) : 8.66;
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

document.getElementById('business-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(this);

    fetch('partials/submit-business-permit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
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
