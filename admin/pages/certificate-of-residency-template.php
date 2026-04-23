<?php
require_once '../partials/admin_auth.php';
/**
 * Printable Certificate of Residency Template
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';

// Check if an ID is provided in the URL
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No request ID specified.";
    header("Location: monitoring-of-request.php");
    exit();
}

$request_id = $_GET['id'];

try {
    // Fetch the document request from the database
    $stmt = $pdo->prepare("SELECT * FROM document_requests WHERE id = ? AND document_type = 'Certificate of Residency'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = "Certificate of Residency request not found.";
        header("Location: monitoring-of-request.php");
        exit();
    }

    if (!$is_view_only && (($request['payment_status'] ?? 'Unpaid') !== 'Paid')) {
        $_SESSION['error_message'] = "Printing is only allowed after payment is completed.";
        header("Location: monitoring-of-request.php");
        exit();
    }

    // Decode the JSON details
    $details = json_decode($request['details'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON details: " . json_last_error_msg());
    }

    // Format the date (prefer new fields, fallback to issued_on)
    if (!empty($details['day_issued']) && !empty($details['month_issued']) && !empty($details['year_issued'])) {
        $day = $details['day_issued'];
        $month = $details['month_issued'];
        $year = $details['year_issued'];
    } else {
        $issued_val = (!empty($details['issued_on'])) ? $details['issued_on'] : date('Y-m-d');
        $date_issued = new DateTime($issued_val);
        $day = $date_issued->format('jS');
        $month = $date_issued->format('F');
        $year = $date_issued->format('Y');
    }
    $has_new_format = !empty($details['duration']) || !empty($details['age']);

} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching the certificate data.";
    header("Location: monitoring-of-request.php");
    exit();
}

// Data completeness check
$missing_fields = [];
if ($has_new_format) {
    if (empty($details['applicant_name'])) $missing_fields[] = 'Applicant Name';
    if (empty($details['age'])) $missing_fields[] = 'Age';
    if (empty($details['duration'])) $missing_fields[] = 'Duration of Residency';
} else {
    if (empty($request['first_name']) && empty($request['last_name'])) $missing_fields[] = 'Applicant Name';
    if (empty($request['date_of_birth'])) $missing_fields[] = 'Age (Date of Birth)';
    if (empty($details['duration'])) $missing_fields[] = 'Duration of Residency';
}
if (empty($day)) $missing_fields[] = 'Day';
if (empty($month)) $missing_fields[] = 'Month';
$has_missing_data = !empty($missing_fields);

$page_title = "Print Certificate of Residency";

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
        }
<?php
if ($is_view_only): ?>
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
<?php
endif; ?>
        .certificate-body { font-family: 'Times New Roman', Times, serif; }
        .placeholder-logo {
            width: 80px;
            height: 80px;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.8rem;
            text-align: center;
        }
        .underline-dotted { border-bottom: 1px dotted #000; padding: 0 0.25rem; font-weight: bold; }
    </style>
</head>
<body class="bg-gray-200">

    <div class="max-w-4xl mx-auto my-10 p-4">
        <div class="no-print text-center mb-6">
<?php
if (!$is_view_only): ?>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md shadow-md">
                <i class="fas fa-print mr-2"></i> Print Certificate
            </button>
<?php
endif; ?>
            <a href="monitoring-of-request.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md shadow-md">
                <i class="fas fa-arrow-left mr-2"></i> Back to Monitoring
            </a>
        </div>
<?php if ($has_missing_data): ?>
        <div class="no-print text-center mb-4">
            <div class="inline-block bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded shadow max-w-2xl">
                <strong><i class="fas fa-exclamation-triangle mr-2"></i>Incomplete Data Warning:</strong> The following fields are missing in the database: <strong><?php echo implode(', ', $missing_fields); ?></strong>. Please verify the resident profile or request details before printing.
            </div>
        </div>
<?php endif; ?>
<?php
if ($is_view_only): ?>
        <div class="no-print text-center mb-4">
            <span class="inline-block bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded text-xs font-bold uppercase tracking-wide">Viewing purpose only</span>
        </div>
<?php
endif; ?>
        
        <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto" style="text-align: center;">
            <?php
            $svg_path = '../../Certificate of Residency.svg';
            $svg = file_get_contents($svg_path);
            if ($svg !== false) {
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                    '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="341.8 ',
                    '<text id="field-age" xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="341.8 ',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 348.10835)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="570.0187',
                    '<text id="field-duration" xml:space="preserve" transform="matrix(.75 0 0 .75 72 348.10835)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="570.0187',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="126.018939',
                    '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="126.018939',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="211.76277',
                    '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="211.76277',
                    $svg
                );
                $svg = str_replace(
                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="303.31086 312.94795 322.58503 332.2221">2026</tspan></text>',
                    '<text id="field-year" xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="303.31086 312.94795 322.58503 332.2221">2026</tspan></text>',
                    $svg
                );
                echo '<div style="display:inline-block;">' . $svg . '</div>';
            } else {
                echo '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
            }
            ?>
        </div>

<script>
(function() {
    const values = {
        'field-name': <?= json_encode($details['applicant_name'] ?? '') ?>,
        'field-age': <?= json_encode($details['age'] ?? '') ?>,
        'field-duration': <?= json_encode($details['duration'] ?? '') ?>,
        'field-day': <?= json_encode($day) ?>,
        'field-month': <?= json_encode($month) ?>,
        'field-year': <?= json_encode($year) ?>
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
            if (transform.indexOf('544.68417') !== -1) {
                if (!parent) parent = t.parentElement;
                t.style.visibility = 'hidden';
            }
        });
        if (!parent) return;
        const newText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        newText.setAttribute('transform', 'matrix(.75 0 0 .75 72 544.68417)');
        newText.setAttribute('font-size', '14.666667');
        newText.setAttribute('font-family', 'Arial');
        newText.setAttribute('font-weight', 'bold');
        const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
        tspan.setAttribute('x', '254.59516');
        tspan.setAttribute('y', '13.757161');
        tspan.textContent = name;
        newText.appendChild(tspan);
        parent.appendChild(newText);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            recomputeLayout();
            updateSignature(<?= json_encode($_SESSION['fullname'] ?? '') ?>);
        }, 100);
    });
})();
</script>
    </div>
</body>
<?php
if ($is_view_only): ?>
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
<?php
endif; ?>
</html> 


