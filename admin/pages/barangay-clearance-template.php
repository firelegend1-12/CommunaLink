<?php
require_once '../partials/admin_auth.php';
/**
 * Barangay Clearance Printable Template (Form Replica)
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = "Print Barangay Clearance";
$is_view_only = isset($_GET['view_only']) && $_GET['view_only'] === '1';

// Validate Request ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid request ID.";
    redirect_to('monitoring-of-request.php');
}
$request_id = $_GET['id'];

try {
    // Fetch request data along with resident info
    $sql = "SELECT dr.*, r.first_name, r.last_name, r.middle_initial, r.gender, r.address, r.date_of_birth, r.place_of_birth, r.civil_status, r.occupation 
            FROM document_requests dr
            JOIN residents r ON dr.resident_id = r.id
            WHERE dr.id = ? AND dr.document_type = 'Barangay Clearance'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = "Barangay Clearance request not found.";
        redirect_to('monitoring-of-request.php');
    }

    if (!$is_view_only && (($request['payment_status'] ?? 'Unpaid') !== 'Paid')) {
        $_SESSION['error_message'] = "Printing is only allowed after payment is completed.";
        redirect_to('monitoring-of-request.php');
    }

    $details = json_decode($request['details'], true);
    
    // Calculate age
    $age = 'N/A';
    if (!empty($request['date_of_birth'])) {
        $birthDate = new DateTime($request['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    redirect_to('monitoring-of-request.php');
}

$punong_barangay = $_SESSION['fullname'] ?? '[PUNONG BARANGAY NAME]';

$details = json_decode($request['details'], true) ?? [];

// Support both old (detailed application form) and new (simplified certificate) formats
$has_new_format = isset($details['applicant_name']);

if ($has_new_format) {
    $applicant_name = htmlspecialchars($details['applicant_name'] ?? '');
    $age = htmlspecialchars($details['age'] ?? '');
    $purpose = htmlspecialchars($details['purpose'] ?? '');
    $day = htmlspecialchars($details['day_issued'] ?? date('jS'));
    $month = htmlspecialchars($details['month_issued'] ?? date('F'));
    $year = htmlspecialchars($details['year_issued'] ?? date('Y'));
} else {
    // Fallback for old-format requests
    $applicant_name = htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
    $birthDate = !empty($request['date_of_birth']) ? new DateTime($request['date_of_birth']) : null;
    $age = $birthDate ? (new DateTime())->diff($birthDate)->y : 'N/A';
    $purpose = htmlspecialchars($request['purpose'] ?? '');
    $day = date('jS');
    $month = date('F');
    $year = date('Y');
}

// Data completeness check
$missing_fields = [];
if (empty($applicant_name)) $missing_fields[] = 'Applicant Name';
if (empty($age) || $age === 'N/A') $missing_fields[] = 'Age';
if (empty($purpose)) $missing_fields[] = 'Purpose';
if (empty($day)) $missing_fields[] = 'Day';
if (empty($month)) $missing_fields[] = 'Month';
$has_missing_data = !empty($missing_fields);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .printable-area { margin: 0; padding: 2rem; border: none; box-shadow: none; }
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
        input[readonly], textarea[readonly] {
            background-color: #f3f4f6; /* Tailwind's gray-100 */
        }
    </style>
</head>
<body class="bg-gray-200">

    <div class="no-print fixed top-4 left-4 z-50 flex space-x-2">
        <a href="monitoring-of-request.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow">
            Back
        </a>
<?php
if (!$is_view_only): ?>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow">
            Print Application
        </button>
<?php
endif; ?>
    </div>

<?php if ($has_missing_data): ?>
    <div class="no-print fixed top-16 left-4 right-4 z-40 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded shadow max-w-4xl mx-auto">
        <strong><i class="fas fa-exclamation-triangle mr-2"></i>Incomplete Data Warning:</strong> The following fields are missing in the database: <strong><?php echo implode(', ', $missing_fields); ?></strong>. Please verify the resident profile or request details before printing.
    </div>
<?php endif; ?>

<?php
if ($is_view_only): ?>
    <div class="no-print fixed top-4 right-4 z-50 bg-yellow-100 border border-yellow-300 text-yellow-900 px-3 py-2 rounded shadow text-xs font-bold uppercase tracking-wide">
        Viewing purpose only
    </div>
<?php
endif; ?>

    <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto" style="text-align: center;">
        <?php
        $svg_path = '../../Barangay Forms (1) (1).svg';
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
                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="72.16353 80.49165 88.81978 97.1479">',
                '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="72.16353 80.49165 88.81978 97.1479">',
                $svg
            );
            $svg = str_replace(
                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="154.97307 163.3012',
                '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="154.97307 163.3012',
                $svg
            );
            $svg = str_replace(
                '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="220.26506 228.59319 236.9213 245.24942">2026</tspan></text>',
                '<text id="field-year" xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="220.26506 228.59319 236.9213 245.24942">2026</tspan></text>',
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
        'field-name': <?= json_encode($applicant_name) ?>,
        'field-age': <?= json_encode($age) ?>,
        'field-purpose': <?= json_encode($purpose) ?>,
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
            updateSignature(<?= json_encode($punong_barangay) ?>);
        }, 100);
    });
})();
</script>

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


