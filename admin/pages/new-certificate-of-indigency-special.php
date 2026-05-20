<?php
require_once '../partials/admin_auth.php';
/**
 * New Certificate of Indigency (Special) Page
 * For deceased persons or patients needing assistance.
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();

$page_title = "Certificate of Indigency (Special)";

// Fetch residents for the dropdown
$residents = [];
try {
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, first_name, last_name, middle_initial, gender, address, date_of_birth, place_of_birth, civil_status, occupation FROM residents ORDER BY last_name ASC");
    $residents = $resident_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($residents as &$resident_row) {
        $resident_row['full_name'] = normalize_document_text($resident_row['full_name'] ?? '');
        $resident_row['document_locality'] = resident_document_locality_label($resident_row['address'] ?? '');
        $resident_row['document_locality'] = normalize_document_text($resident_row['document_locality'] ?? '');
    }
    unset($resident_row);
} catch (PDOException $e) {
    $residents = [];
    $_SESSION['error_message'] = "A database error occurred while fetching residents.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="../../assets/js/document-svg-layout.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Always center the SVG inside its container */
        .printable-area { text-align: center; }
        .printable-area svg { display: block; margin: 0 auto; max-width: 100%; height: auto; }

        @page { size: A4 portrait; margin: 10mm; }

        @media print {
            .print\:hidden { display: none !important; }
            html, body { background: white !important; margin: 0 !important; padding: 0 !important; }
            .no-print, header, .sidebar, nav, .flex.h-screen > :first-child { display: none !important; }
            .flex.h-screen { display: block !important; height: auto !important; overflow: visible !important; }
            .flex-col.flex-1 { display: block !important; overflow: visible !important; }
            main { padding: 0 !important; overflow: visible !important; }
            .max-w-4xl { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .bg-white.rounded-lg { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .printable-area {
                width: 190mm !important;
                max-width: 190mm !important;
                min-height: 277mm !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                margin: 0 auto !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            .printable-area svg { width: 100% !important; max-width: 100% !important; height: auto !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?php echo $page_title; ?></h1>

                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
                    <?php display_flash_messages(); ?>
                    <div class="bg-white rounded-lg shadow p-8"
                         x-data='{
                             residents: <?php echo json_encode($residents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                             selectedResidentId: null,
                             selectedResident: {},
                             formName: "",
                             formRequester: "",
                             formRelation: "",
                             formLocality: "",
                             formDay: new Date().getDate().toString(),
                             formMonth: new Date().toLocaleDateString("en-US", { month: "long" }),
                             layoutRafId: null,
                             fieldMap: {
                                 "field-name": "formName",
                                 "field-requester": "formRequester",
                                 "field-relation": "formRelation",
                                 "field-locality": "formLocality",
                                 "field-day": "formDay",
                                 "field-month": "formMonth"
                             },
                             fieldGroups: {
                                 "field-locality": ["field-locality-options"]
                             },
                             init() {
                                 this.$watch("formName", () => this.recomputeLayout());
                                 this.$watch("formRequester", () => this.recomputeLayout());
                                 this.$watch("formRelation", () => this.recomputeLayout());
                                 this.$watch("formLocality", () => this.recomputeLayout());
                                 this.$watch("formDay", () => this.recomputeLayout());
                                 this.$watch("formMonth", () => this.recomputeLayout());
                                 this.$nextTick(() => this.recomputeLayout());
                             },
                             trackedFieldIds() {
                                 return [...new Set(Object.keys(this.fieldMap).concat(...Object.values(this.fieldGroups)))];
                             },
                             normalizeCertificateText(value) {
                                 return window.CommunaLinkDocumentSvg.normalizeText(value);
                             },
                             rememberFieldState(id) {
                                 const el = document.getElementById(id);
                                 if (!el) return;
                                 const tspan = el.querySelector("tspan");
                                 if (!tspan) return;
                                 if (!tspan.dataset.origX) {
                                     tspan.dataset.origX = tspan.getAttribute("x") || "0";
                                 }
                                 if (!tspan.dataset.origText) {
                                     tspan.dataset.origText = tspan.textContent;
                                 }
                             },
                             resetTrackedText() {
                                 this.trackedFieldIds().forEach(id => {
                                     this.rememberFieldState(id);
                                     const el = document.getElementById(id);
                                     const tspan = el ? el.querySelector("tspan") : null;
                                     if (!tspan) return;
                                     tspan.textContent = tspan.dataset.origText || "";
                                     tspan.setAttribute("x", tspan.dataset.origX || "0");
                                 });
                             },
                             fieldGroupIds(id) {
                                 return [id].concat(this.fieldGroups[id] || []);
                             },
                             measureFieldSpan(ids) {
                                 let start = null;
                                 let end = null;
                                 ids.forEach(id => {
                                     const el = document.getElementById(id);
                                     const tspan = el ? el.querySelector("tspan") : null;
                                     if (!tspan) return;
                                     const xCoords = (tspan.dataset.origX || tspan.getAttribute("x") || "0").split(/\s+/).map(parseFloat).filter(n => !isNaN(n));
                                     if (xCoords.length === 0) return;
                                     const firstX = xCoords[0];
                                     const lastX = xCoords[xCoords.length - 1];
                                     const charWidth = xCoords.length > 1 ? (xCoords[1] - xCoords[0]) : 8.33;
                                     start = start === null ? firstX : Math.min(start, firstX);
                                     end = end === null ? (lastX + charWidth) : Math.max(end, lastX + charWidth);
                                 });
                                 if (start === null || end === null) return null;
                                 return { start: start, width: end - start };
                             },
                             selectResident() {
                                 if (!this.selectedResidentId) {
                                     this.selectedResident = {};
                                     this.formName = "";
                                     this.formRequester = "";
                                     this.formLocality = "";
                                     this.$nextTick(() => this.recomputeLayout());
                                     return;
                                 }
                                 const resident = this.residents.find(r => r.id == this.selectedResidentId);
                                 if (resident) {
                                     this.selectedResident = resident;
                                     this.formName = this.normalizeCertificateText(resident.full_name || "");
                                     this.formRequester = this.normalizeCertificateText(resident.full_name || "");
                                     this.formLocality = this.normalizeCertificateText(resident.document_locality || "");
                                 }
                                 this.$nextTick(() => this.recomputeLayout());
                             },
                             getFieldValue(id) {
                                 const key = this.fieldMap[id];
                                 return key ? this.normalizeCertificateText(this[key]) : "";
                             },
                             recomputeLayout() {
                                 this.layoutRafId = window.CommunaLinkDocumentSvg.syncLayout({
                                     fieldIds: Object.keys(this.fieldMap),
                                     fieldGroups: this.fieldGroups || {},
                                     getFieldValue: id => this.getFieldValue(id)
                                 });
                             },
                             printCertificate() {
                                if (!this.formName || !this.formName.trim() || !this.formRequester || !this.formRequester.trim() || !this.formRelation || !this.formRelation.trim() || !this.formDay || !this.formDay.trim() || !this.formMonth || !this.formMonth.trim()) {
                                    alert("Please fill in all required fields before printing.");
                                    return;
                                }
                                window.print();
                            }
                         }'>

                        <!-- Form Controls -->
                        <div class="mb-6 space-y-4 print:hidden">
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                This certificate is for <strong>deceased persons</strong> or <strong>patients needing medical assistance</strong>.
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Resident (Patient / Deceased)</label>
                                <select x-model="selectedResidentId" @change="selectResident()"
                                        class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2">
                                    <option value="">-- Choose a resident --</option>
                                    <template x-for="resident in residents" :key="resident.id">
                                        <option :value="resident.id" x-text="resident.full_name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient / Deceased Name</label>
                                    <input type="text" x-model="formName" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Full name of patient / deceased">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Requester Name</label>
                                    <input type="text" x-model="formRequester" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Name of person requesting">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Relation to Patient / Deceased</label>
                                    <input type="text" x-model="formRelation" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="e.g. son, daughter, spouse">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                                    <input type="text" x-model="formDay" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Day">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                    <input type="text" x-model="formMonth" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Month">
                                </div>
                                <div class="flex items-end">
                                    <button @click="printCertificate()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md shadow-sm transition">
                                        <i class="fas fa-print mr-2"></i> Print Certificate
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Certificate Preview -->
                        <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto">
                            <?php
                            $svg_path = '../../Certificate of Indigency (Special).svg';
                            $svg = file_get_contents($svg_path);
                            if ($svg !== false) {
                                // Patient / deceased name (line ~346)
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                    '<text id="field-name" xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="206.88924',
                                    $svg
                                );
                                // Requester name (line ~408)
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="467.0144',
                                    '<text id="field-requester" xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="467.0144',
                                    $svg
                                );
                                // Relation (line ~418)
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="115.56888',
                                    '<text id="field-relation" xml:space="preserve" transform="matrix(.75 0 0 .75 72 396.32997)" font-size="17.333334" font-family="Arial"><tspan y="46.155923" x="115.56888',
                                    $svg
                                );
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="508.46488 519.04959 528.68667 538.3237">Zone</tspan></text>',
                                    '<text id="field-locality" xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="508.46488 519.04959 528.68667 538.3237">Zone</tspan></text>',
                                    $svg
                                );
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="552.77517 557.5895 562.4038 567.2181 572.0324 576.84677 581.661 586.47537 591.2897 596.104 600.91836 610.8849">I/II/III/IV,</tspan></text>',
                                    '<text id="field-locality-options" xml:space="preserve" transform="matrix(.75 0 0 .75 72 306.63758)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="552.77517 557.5895 562.4038 567.2181 572.0324 576.84677 581.661 586.47537 591.2897 596.104 600.91836 610.8849">I/II/III/IV,</tspan></text>',
                                    $svg
                                );
                                // Day (line ~442)
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
                                    '<text id="field-day" xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="135.64756',
                                    $svg
                                );
                                // Month (line ~448)
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
                                    '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 486.02235)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="221.39139',
                                    $svg
                                );
                                echo $svg;
                            } else {
                                echo '<p class="text-red-500 text-center py-8">Error: Could not load certificate template.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
