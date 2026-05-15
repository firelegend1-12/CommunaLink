<?php
/**
 * Shared printable document renderer for Monitoring Requests.
 *
 * Each admin/pages/*-template.php file sets $communalink_print_template before
 * including this file. The renderer keeps the existing SVG layouts and fills
 * them from the saved request/transaction record.
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$template_key = $communalink_print_template ?? '';
$auto_print = isset($_GET['auto_print']) && $_GET['auto_print'] === '1';
$close_after_print = isset($_GET['close_after_print']) && $_GET['close_after_print'] === '1';
$request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$request_id) {
    $_SESSION['error_message'] = 'Invalid request ID.';
    redirect_to('monitoring-of-request.php');
}

function print_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function print_first(array $source, array $keys, $fallback = '')
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && $source[$key] !== '') {
            return $source[$key];
        }
    }
    return $fallback;
}

function print_decode_details($json)
{
    $details = json_decode((string)$json, true);
    return is_array($details) ? $details : [];
}

function print_redirect_with_error($message, $type = '')
{
    $_SESSION['error_message'] = $message;
    redirect_to($type === 'business' ? 'monitoring-of-request.php?type=business' : 'monitoring-of-request.php');
}

function print_svg_with_ids($path, array $markers)
{
    $svg = file_get_contents($path);
    if ($svg === false) {
        return '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
    }

    foreach ($markers as $id => $needle) {
        $replacement = preg_replace('/^<text\b/', '<text id="' . $id . '"', $needle, 1);
        if ($replacement) {
            $svg = str_replace($needle, $replacement, $svg);
        }
    }

    return '<div style="display:inline-block;">' . $svg . '</div>';
}

$template_map = [
    'barangay-clearance' => [
        'title' => 'Print Barangay Clearance',
        'type' => 'document',
        'document_type' => 'Barangay Clearance',
        'svg' => '../../Barangay Forms (1) (1).svg',
        'ids' => [
            'field-name' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman"><tspan y="15.5598959" x="238.0417',
            'field-age' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="537.8542 546.1823 554.51046 562.83859">',
            'field-purpose' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 394.6116)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="0 8.328125',
            'field-gender' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="29.612015 41.640626 49.96875 54.59639 61.989229 66.61687 71.24451 78.637348 90.665958 98.99408 103.62172 111.01456">male/female,</tspan></text>',
            'field-civil-status' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="119.34268 125.82463 130.45227 138.7804 147.10852 151.73616">single</tspan></text>',
            'field-civil-status-slash' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="163.29306">/</tspan></text>',
            'field-civil-status-married' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="172.08477 184.11338 192.4415 198.92345 205.4054 210.03304 217.42588 225.754">married/</tspan></text>',
            'field-civil-status-separated' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="234.5457 241.02765 248.42049 256.7486 265.07673 271.5587 279.8868 284.51448 291.9073 300.2354">separated/</tspan></text>',
            'field-civil-status-widow' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="309.0271 320.1367 324.76435 333.09248 341.4206 352.53016 357.1578 364.55067">widow/er</tspan></text>',
            'field-locality' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="477.289 486.55244 494.88056 503.20869">Zone</tspan></text>',
            'field-locality-i' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="514.76559">I</tspan></text>',
            'field-locality-ii' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="524.4763 529.10397 534.65066">/II</tspan></text>',
            'field-locality-slash' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="544.3613">/</tspan></text>',
            'field-locality-iii' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="553.153 558.6997 564.24636 569.79299">III/</tspan></text>',
            'field-locality-iv' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 262.37284)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="37.59969" x="578.5847 584.13137">IV</tspan></text>',
            'field-day' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="72.16353 80.49165 88.81978 97.1479">',
            'field-month' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 460.731)" font-size="16.666666" font-family="Times New Roman" font-style="italic"><tspan y="15.5598959" x="154.97307 163.3012',
        ],
        'field_groups' => [
            'field-civil-status' => ['field-civil-status-slash', 'field-civil-status-married', 'field-civil-status-separated', 'field-civil-status-widow'],
            'field-locality' => ['field-locality-i', 'field-locality-ii', 'field-locality-slash', 'field-locality-iii', 'field-locality-iv'],
        ],
    ],
    'certificate-of-residency' => [
        'title' => 'Print Certificate of Residency',
        'type' => 'document',
        'document_type' => 'Certificate of Residency',
        'svg' => '../../Certificate of Residency.svg',
        'ids' => [
            'field-name' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
            'field-age' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="341.8 ',
            'field-duration' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 348.10835)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="570.0187',
            'field-civil-status' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="455.46513 464.12919 467.97895 477.61604 487.2531 491.10289 500.73997 505.55427 519.9888 529.62588 535.39627 541.1666 545.01638 554.65347 564.2905 569.10488 581.6187 585.46847 595.1055 604.7426">single/married/widow</tspan></text>',
            'field-locality' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="188.80736 199.39208 209.02916 218.66625">Zone</tspan></text>',
            'field-locality-options' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 280.83906)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="233.11765 237.93196 242.74628 247.5606 252.37491 257.1892 262.00355 266.81788 271.63218 276.44648 281.2608 291.2274">I/II/III/IV,</tspan></text>',
            'field-day' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="126.018939',
            'field-month' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 482.6469)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="211.76277',
        ],
        'field_groups' => [
            'field-locality' => ['field-locality-options'],
        ],
    ],
    'certificate-of-indigency' => [
        'title' => 'Print Certificate of Indigency',
        'type' => 'document',
        'document_type' => 'Certificate of Indigency',
        'svg' => '../../Certificate of Indigency.svg',
        'ids' => [
            'field-name' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 299.79566)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
            'field-age' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 299.79566)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="544.1788',
            'field-day' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 456.75733)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
            'field-month' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 456.75733)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
        ],
    ],
    'certificate-of-indigency-special' => [
        'title' => 'Print Certificate of Indigency (Special)',
        'type' => 'document',
        'document_type' => 'Certificate of Indigency (Special)',
        'svg' => '../../Certificate of Indigency (Special).svg',
        'ids' => [
            'field-name' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
            'field-requester' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="467.0144',
            'field-relation' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="115.56888',
            'field-day' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
            'field-month' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
        ],
    ],
    'business-clearance' => [
        'title' => 'Print Business Clearance',
        'type' => 'business',
        'svg' => '../../Barangay Business Clearance.svg',
        'ids' => [
            'field-business-name' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="46.079755" x="188.63808',
            'field-owner' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="46.079755" x="473.09687',
            'field-location' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 278.84709)" font-size="17.333334" font-family="Times New Roman"><tspan y="75.97721" x="16.837403',
            'field-day' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 458.2318)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475',
            'field-month' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 458.2318)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349',
        ],
    ],
    'business-permit-application' => [
        'title' => 'Print Business Permit',
        'type' => 'business',
        'svg' => '../../Barangay Business Permit.svg',
        'ids' => [
            'field-name' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 298.6829)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
            'field-owner' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 315.87394)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="144 152.66407',
            'field-type' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 333.06498)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.182291" x="171.54647 180.21053',
            'field-location' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 350.256)" font-size="17.333334" font-family="Times New Roman" font-weight="bold"><tspan y="16.778668" x="67.89957 79.45729',
            'field-name2' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="188.63808 197.30214',
            'field-owner2' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="36.113935" x="473.09687 481.76094',
            'field-location2' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 392.25056)" font-size="17.333334" font-family="Times New Roman"><tspan y="56.045576" x="16.837403 25.501465',
            'field-day' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="124.0475 132.71157',
            'field-month' => '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349 219.81755',
        ],
    ],
];

if (!isset($template_map[$template_key])) {
    print_redirect_with_error('Unknown print template.');
}

$template = $template_map[$template_key];
$row = null;
$details = [];
$values = [];
$back_url = $template['type'] === 'business' ? 'monitoring-of-request.php?type=business' : 'monitoring-of-request.php';

try {
    if ($template['type'] === 'document') {
        $stmt = $pdo->prepare(
            "SELECT dr.*, r.first_name, r.last_name, r.date_of_birth, r.gender, r.address, r.civil_status
             FROM document_requests dr
             LEFT JOIN residents r ON dr.resident_id = r.id
             WHERE dr.id = ? AND dr.document_type = ?"
        );
        $stmt->execute([$request_id, $template['document_type']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT bt.*, r.first_name, r.last_name
             FROM business_transactions bt
             LEFT JOIN residents r ON bt.resident_id = r.id
             WHERE bt.id = ?"
        );
        $stmt->execute([$request_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    print_redirect_with_error('Database error: ' . $e->getMessage(), $template['type']);
}

if (!$row) {
    print_redirect_with_error('Request not found for this print template.', $template['type']);
}

if ($template_key === 'business-clearance' && ($row['remarks'] ?? '') !== 'Barangay Business Clearance') {
    print_redirect_with_error('Business clearance request not found.', 'business');
}

if ($template_key === 'business-permit-application' && ($row['remarks'] ?? '') === 'Barangay Business Clearance') {
    print_redirect_with_error('Business permit request not found.', 'business');
}

if ($template['type'] === 'document' && !document_request_requires_payment($template['document_type'])) {
    // Free certificates can be printed immediately.
} elseif (($row['payment_status'] ?? 'Unpaid') !== 'Paid') {
    print_redirect_with_error('Printing is only allowed after payment is completed.', $template['type']);
}

if ($template['type'] === 'document') {
    $details = print_decode_details($row['details'] ?? '');
    $resident_name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $age = resident_document_age($row['date_of_birth'] ?? '');
    $resident_gender = resident_document_gender_label($row['gender'] ?? '');
    $resident_civil_status = resident_document_civil_status_label($row['civil_status'] ?? '');
    $resident_locality = resident_document_locality_label($row['address'] ?? '');

    if ($template_key === 'barangay-clearance') {
        $values = [
            'field-name' => print_first($details, ['applicant_name', 'full_name', 'requester_name'], $resident_name),
            'field-age' => print_first($details, ['age'], $age),
            'field-purpose' => print_first($details, ['purpose'], $row['purpose'] ?? ''),
            'field-gender' => print_first($details, ['gender'], $resident_gender),
            'field-civil-status' => print_first($details, ['civil_status'], $resident_civil_status),
            'field-locality' => print_first($details, ['locality'], $resident_locality),
            'field-day' => print_first($details, ['day_issued', 'day'], date('jS', strtotime($row['date_requested'] ?? 'now'))),
            'field-month' => print_first($details, ['month_issued', 'month'], date('F', strtotime($row['date_requested'] ?? 'now'))),
        ];
    } elseif ($template_key === 'certificate-of-residency') {
        $values = [
            'field-name' => print_first($details, ['applicant_name', 'full_name', 'requester_name'], $resident_name),
            'field-age' => print_first($details, ['age'], $age),
            'field-duration' => print_first($details, ['duration', 'years_of_residency', 'resident_since']),
            'field-civil-status' => print_first($details, ['civil_status'], $resident_civil_status),
            'field-locality' => print_first($details, ['locality'], $resident_locality),
            'field-day' => print_first($details, ['day_issued', 'day'], date('jS', strtotime($details['issued_on'] ?? ($row['date_requested'] ?? 'now')))),
            'field-month' => print_first($details, ['month_issued', 'month'], date('F', strtotime($details['issued_on'] ?? ($row['date_requested'] ?? 'now')))),
        ];
    } elseif ($template_key === 'certificate-of-indigency') {
        $values = [
            'field-name' => print_first($details, ['applicant_name', 'recipient_name', 'full_name'], $resident_name),
            'field-age' => print_first($details, ['age'], $age),
            'field-day' => print_first($details, ['day_issued', 'day'], date('jS', strtotime($row['date_requested'] ?? 'now'))),
            'field-month' => print_first($details, ['month_issued', 'month'], date('F', strtotime($row['date_requested'] ?? 'now'))),
        ];
    } elseif ($template_key === 'certificate-of-indigency-special') {
        $values = [
            'field-name' => print_first($details, ['beneficiary_name', 'patient_name', 'deceased_name'], $resident_name),
            'field-requester' => print_first($details, ['requester_name'], $resident_name),
            'field-relation' => print_first($details, ['relation', 'relationship']),
            'field-day' => print_first($details, ['day_issued', 'day'], date('jS', strtotime($row['date_requested'] ?? 'now'))),
            'field-month' => print_first($details, ['month_issued', 'month'], date('F', strtotime($row['date_requested'] ?? 'now'))),
        ];
    }
} else {
    $requested_date = $row['application_date'] ?? 'now';
    $business_name = $row['business_name'] ?? '';
    $owner_name = $row['owner_name'] ?? trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $business_type = $row['business_type'] ?? '';
    $location = $row['address'] ?? '';
    $day = date('jS', strtotime($requested_date));
    $month = date('F', strtotime($requested_date));

    if ($template_key === 'business-clearance') {
        $values = [
            'field-business-name' => $business_name,
            'field-owner' => $owner_name,
            'field-location' => $location,
            'field-day' => $day,
            'field-month' => $month,
        ];
    } else {
        $values = [
            'field-name' => $business_name,
            'field-owner' => $owner_name,
            'field-type' => $business_type,
            'field-location' => $location,
            'field-name2' => $business_name,
            'field-owner2' => $owner_name,
            'field-location2' => $location,
            'field-day' => $day,
            'field-month' => $month,
        ];
    }
}

$page_title = $template['title'];
$svg_html = print_svg_with_ids($template['svg'], $template['ids']);
$field_groups = $template['field_groups'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo print_h($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .printable-area { text-align: center; }
        .printable-area svg {
            display: block;
            margin: 0 auto;
            max-width: 100%;
            height: auto;
        }

        @page { size: A4 portrait; margin: 10mm; }

        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .page-shell {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                opacity: 1 !important;
            }
            .printable-area {
                width: 190mm !important;
                max-width: 190mm !important;
                min-height: 277mm !important;
                box-sizing: border-box !important;
                margin: 0 auto !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                overflow: visible !important;
            }
            .printable-area svg {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
            }
        }
        @media screen {
            body.auto-print-mode .page-shell {
                opacity: 0;
                pointer-events: none;
            }
        }
    </style>
</head>
<body class="bg-gray-200<?php echo $auto_print ? ' auto-print-mode' : ''; ?>">
    <div class="page-shell max-w-4xl mx-auto my-8 p-4">
        <div class="no-print text-center mb-4 space-x-2<?php echo $auto_print ? ' hidden' : ''; ?>">
            <a href="<?php echo print_h($back_url); ?>" class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-md text-sm font-bold">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
            <button type="button" onclick="window.print()" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md text-sm font-bold">
                <i class="fas fa-print mr-2"></i>Print Certificate
            </button>
        </div>

        <div class="printable-area max-w-4xl mx-auto p-8 bg-white shadow-lg overflow-auto text-center">
            <?php echo $svg_html; ?>
        </div>
    </div>

<script>
(function() {
    const values = <?php echo json_encode($values, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const fieldGroups = <?php echo json_encode($field_groups, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const autoPrint = <?php echo $auto_print ? 'true' : 'false'; ?>;
    const closeAfterPrint = <?php echo $close_after_print ? 'true' : 'false'; ?>;
    let printedOrCancelled = false;

    function getFieldValue(id) {
        return values[id] || '';
    }

    function trackedFieldIds() {
        return [...new Set(Object.keys(values).concat(...Object.values(fieldGroups)))];
    }

    function rememberFieldState(id) {
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
    }

    function resetTrackedText() {
        trackedFieldIds().forEach(function(id) {
            rememberFieldState(id);
            const el = document.getElementById(id);
            const tspan = el ? el.querySelector('tspan') : null;
            if (!tspan) return;
            tspan.textContent = tspan.dataset.origText || '';
            tspan.setAttribute('x', tspan.dataset.origX || '0');
        });
    }

    function fieldGroupIds(id) {
        return [id].concat(fieldGroups[id] || []);
    }

    function measureFieldSpan(ids) {
        let start = null;
        let end = null;
        ids.forEach(function(id) {
            const el = document.getElementById(id);
            const tspan = el ? el.querySelector('tspan') : null;
            if (!tspan) return;
            const xCoords = (tspan.dataset.origX || tspan.getAttribute('x') || '0').split(/\s+/).map(parseFloat).filter(function(n) { return !isNaN(n); });
            if (xCoords.length === 0) return;
            const firstX = xCoords[0];
            const lastX = xCoords[xCoords.length - 1];
            const charWidth = xCoords.length > 1 ? (xCoords[1] - xCoords[0]) : 8.33;
            start = start === null ? firstX : Math.min(start, firstX);
            end = end === null ? (lastX + charWidth) : Math.max(end, lastX + charWidth);
        });
        if (start === null || end === null) return null;
        return { start: start, width: end - start };
    }

    function recomputeLayout() {
        document.querySelectorAll('svg text').forEach(function(el) {
            if (!el.dataset.origTransform && el.hasAttribute('transform')) {
                el.dataset.origTransform = el.getAttribute('transform');
            }
        });
        resetTrackedText();

        Object.keys(values).forEach(function(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const tspan = el.querySelector('tspan');
            if (!tspan) return;

            const value = getFieldValue(id);
            const firstX = tspan.dataset.origX.split(/\s+/)[0];
            if (!value || String(value).trim() === '') return;

            const match = tspan.dataset.origText.match(/^([^_]*?)(_+)(.*)$/);
            const prefix = match ? match[1] : '';
            const suffix = match ? match[3] : '';
            tspan.textContent = prefix + String(value) + suffix;
            tspan.setAttribute('x', firstX);
            (fieldGroups[id] || []).forEach(function(extraId) {
                const extraEl = document.getElementById(extraId);
                const extraTspan = extraEl ? extraEl.querySelector('tspan') : null;
                if (!extraTspan) return;
                extraTspan.textContent = '';
                extraTspan.setAttribute('x', extraTspan.dataset.origX || '0');
            });
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

                const groupIds = fieldGroupIds(id);
                const span = measureFieldSpan(groupIds);
                if (!span) return;
                let actualWidth = 0;
                try { actualWidth = tspan.getComputedTextLength(); } catch(e) {}
                const shift = span.width - actualWidth;
                if (Math.abs(shift) <= 0.5) return;

                const origLineTransform = el.dataset.origTransform || el.getAttribute('transform');
                const lineY = tspan.getAttribute('y');
                const parent = el.parentElement;
                if (!parent) return;
                const groupIdSet = new Set(groupIds);

                Array.from(parent.querySelectorAll('text')).forEach(function(t) {
                    if (t === el || groupIdSet.has(t.id)) return;
                    const ts = t.querySelector('tspan');
                    if (!ts) return;
                    const tBaseTransform = t.dataset.origTransform || t.getAttribute('transform') || '';
                    if (tBaseTransform !== origLineTransform) return;
                    if (ts.getAttribute('y') !== lineY) return;
                    const tOrigX = ts.dataset.origX || ts.getAttribute('x') || '0';
                    const tFirstX = parseFloat(tOrigX.split(/\s+/)[0]);
                    if (isNaN(tFirstX) || tFirstX <= span.start) return;
                    const currentTransform = t.getAttribute('transform') || tBaseTransform;
                    t.setAttribute('transform', currentTransform + ' translate(' + (-shift) + ' 0)');
                });
            });

            if (autoPrint) {
                setTimeout(function() {
                    window.focus();
                    window.print();
                }, 250);
            }
        });
    }

    window.addEventListener('afterprint', function() {
        printedOrCancelled = true;
        if (closeAfterPrint) {
            window.close();
        }
    });

    window.addEventListener('focus', function() {
        if (!autoPrint || !closeAfterPrint || printedOrCancelled) return;
        setTimeout(function() {
            if (!printedOrCancelled) {
                printedOrCancelled = true;
                window.close();
            }
        }, 750);
    });

    document.addEventListener('DOMContentLoaded', recomputeLayout);
})();
</script>
</body>
</html>
