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

$full_name = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);

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
                    <div id="svg-preview-container" class="printable-area bg-white p-4 rounded-xl border border-gray-200 shadow-sm overflow-auto" style="max-height: 600px; text-align: center;">
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

document.getElementById('indigency-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const formData = new FormData(this);
    
    fetch('partials/submit-indigency.php', {
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
