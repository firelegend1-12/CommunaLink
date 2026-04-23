<?php
require_once '../partials/admin_auth.php';
/**
 * Business Permit Application — Read-Only View Template
 * Displays a pending business permit application submitted by a resident.
 * This is NOT the final permit; the official permit is generated via generate-business-permit.php after approval.
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = 'Business Permit Application';
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid transaction ID.";
    redirect_to('monitoring-of-request.php');
}
$transaction_id = (int) $_GET['id'];

try {
    $sql = "SELECT bt.*, r.first_name, r.last_name, r.address as resident_address
            FROM business_transactions bt
            JOIN residents r ON bt.resident_id = r.id
            WHERE bt.id = ?
              AND bt.transaction_type IN ('New Permit', 'Renewal')
              AND (bt.remarks IS NULL OR bt.remarks != 'Barangay Business Clearance')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        $_SESSION['error_message'] = "Business permit application not found.";
        redirect_to('monitoring-of-request.php');
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    redirect_to('monitoring-of-request.php');
}

$owner_name = htmlspecialchars(($transaction['first_name'] ?? '') . ' ' . ($transaction['last_name'] ?? ''));
$application_date = date('F j, Y', strtotime($transaction['application_date'] ?? 'now'));
$status = htmlspecialchars($transaction['status'] ?? 'Pending');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .printable-area { margin: 0; padding: 1rem; border: none; box-shadow: none; }
        }
<?php if ($is_view_only): ?>
        @media print {
            body * { visibility: hidden !important; }
            body::before {
                content: 'VIEW ONLY - Printing disabled';
                visibility: visible !important;
                display: block;
                text-align: center;
                margin-top: 30vh;
                font-size: 24px;
                font-weight: 700;
            }
        }
<?php endif; ?>
        .certificate-body {
            font-family: 'Times New Roman', Times, serif;
        }
    </style>
</head>
<body class="bg-gray-200">
    <div class="max-w-4xl mx-auto my-10 p-4">
        <div class="no-print text-center mb-4 space-x-2">
            <a href="monitoring-of-request.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md inline-block">
                <i class="fas fa-arrow-left mr-2"></i> Back to Monitoring
            </a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md inline-block">
                <i class="fas fa-print mr-2"></i> Print Application
            </button>
        </div>
<?php if ($is_view_only): ?>
        <div class="no-print text-center mb-4">
            <span class="inline-block bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded text-xs font-bold uppercase tracking-wide">Viewing purpose only</span>
        </div>
<?php endif; ?>

        <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto" style="text-align: center;">
            <?php
            $app_date_obj = new DateTime($transaction['application_date'] ?? 'now');
            $day = $app_date_obj->format('jS');
            $month = $app_date_obj->format('F');
            $punong_barangay = $_SESSION['fullname'] ?? '';
            $svg_path = '../../Barangay Business Permit.svg';
            $svg = file_get_contents($svg_path);
            if ($svg !== false) {
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 298.6829)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                    '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 298.6829)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 315.87394)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                    '<text id="field-owner" xml:space="preserve" transform="matrix(.75 0 0 .75 72 315.87394)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 333.06498)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="171.54647 180.21053',
                    '<text id="field-type" xml:space="preserve" transform="matrix(.75 0 0 .75 72 333.06498)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="171.54647 180.21053',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 350.256)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.778668" x="67.89957 79.45729',
                    '<text id="field-location" xml:space="preserve" transform="matrix(.75 0 0 .75 72 350.256)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.778668" x="67.89957 79.45729',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="188.63808 197.30214',
                    '<text id="field-name2" xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="188.63808 197.30214',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="473.09687 481.76094',
                    '<text id="field-owner2" xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="473.09687 481.76094',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="56.045576" x="16.837403 25.501465',
                    '<text id="field-location2" xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="56.045576" x="16.837403 25.501465',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475 132.71157',
                    '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475 132.71157',
                    $svg
                );
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

<script>
(function() {
    const values = {
        'field-name': <?= json_encode($transaction['business_name'] ?? '') ?>,
        'field-owner': <?= json_encode($transaction['owner_name'] ?? $owner_name) ?>,
        'field-type': <?= json_encode($transaction['business_type'] ?? '') ?>,
        'field-location': <?= json_encode($transaction['address'] ?? '') ?>,
        'field-name2': <?= json_encode($transaction['business_name'] ?? '') ?>,
        'field-owner2': <?= json_encode($transaction['owner_name'] ?? $owner_name) ?>,
        'field-location2': <?= json_encode($transaction['address'] ?? '') ?>,
        'field-day': <?= json_encode($day) ?>,
        'field-month': <?= json_encode($month) ?>
    };

    function getFieldValue(id) {
        return values[id] || '';
    }

    function recomputeLayout() {
        document.querySelectorAll('svg text').forEach(function(el) {
            if (!el.dataset.origTransform && el.hasAttribute('transform')) {
                el.dataset.origTransform = el.getAttribute('transform');
            }
        });

        Object.keys(values).forEach(function(id) {
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
            Object.keys(values).forEach(function(id) {
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

    function updateSignature(name) {
        if (!name) return;
        const svg = document.querySelector('svg');
        if (!svg) return;
        let parent = null;
        svg.querySelectorAll('text').forEach(function(t) {
            const transform = t.getAttribute('transform') || '';
            if (transform.indexOf('541.73788') !== -1) {
                if (!parent) parent = t.parentElement;
                t.style.visibility = 'hidden';
            }
        });
        if (!parent) return;
        const newText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        newText.setAttribute('transform', 'matrix(.75 0 0 .75 72 541.73788)');
        newText.setAttribute('font-size', '14.666667');
        newText.setAttribute('font-family', 'Arial');
        newText.setAttribute('font-weight', 'bold');
        const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
        tspan.setAttribute('x', '234.23886');
        tspan.setAttribute('y', '13.757161');
        tspan.textContent = name;
        newText.appendChild(tspan);
        parent.appendChild(newText);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            recomputeLayout();
            updateSignature(<?= json_encode($punong_barangay) ?>);
        }, 100);
    });
})();
</script>
    </div>
</body>
<?php if ($is_view_only): ?>
<script>
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        alert('Printing is disabled in view-only mode.');
    }
});
window.print = function() {
    alert('Printing is disabled in view-only mode.');
};
</script>
<?php endif; ?>
</html>
