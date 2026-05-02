<?php
require_once '../partials/admin_auth.php';
/**
 * New Barangay Business Permit Page
 * SVG-embedded print preview using the same layout-shift logic as
 * the barangay clearance page. Multiple SVG text nodes share single
 * form inputs (business name / owner / location appear both in the
 * header block and the paragraph body).
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();

$page_title = "Application for Barangay Business Permit";

// Fetch residents for the dropdown
$residents = [];
try {
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, address FROM residents ORDER BY last_name ASC");
    $residents = $resident_stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
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
        <?php include '../partials/sidebar.php'; ?>

        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?php echo $page_title; ?></h1>

                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
                    <?php display_flash_messages(); ?>
                    <div class="bg-white rounded-lg shadow p-8"
                         x-data='{
                             residents: <?php echo json_encode($residents); ?>,
                             selectedResidentId: null,
                             selectedResident: {},
                             formBusinessName: "",
                             formOwner: "",
                             formType: "",
                             formLocation: "",
                             formDay: new Date().getDate().toString(),
                             formMonth: new Date().toLocaleDateString("en-US", { month: "long" }),
                             layoutRafId: null,
                             // Each SVG field maps to one of the form vars above.
                             fieldMap: {
                                 "field-name": "formBusinessName",
                                 "field-name2": "formBusinessName",
                                 "field-owner": "formOwner",
                                 "field-owner2": "formOwner",
                                 "field-type": "formType",
                                 "field-location": "formLocation",
                                 "field-location2": "formLocation",
                                 "field-day": "formDay",
                                 "field-month": "formMonth"
                             },
                             init() {
                                 ["formBusinessName","formOwner","formType","formLocation","formDay","formMonth"].forEach(key => {
                                     this.$watch(key, () => this.recomputeLayout());
                                 });
                                 this.$nextTick(() => this.recomputeLayout());
                             },
                             selectResident() {
                                 if (!this.selectedResidentId) return;
                                 const resident = this.residents.find(r => r.id == this.selectedResidentId);
                                 if (resident) {
                                     this.selectedResident = resident;
                                     this.formOwner = resident.full_name || "";
                                     if (resident.address && !this.formLocation) {
                                         this.formLocation = resident.address;
                                     }
                                 }
                                 this.$nextTick(() => this.recomputeLayout());
                             },
                             getFieldValue(id) {
                                 const key = this.fieldMap[id];
                                 return key ? this[key] : "";
                             },
                             recomputeLayout() {
                                 if (this.layoutRafId) {
                                     cancelAnimationFrame(this.layoutRafId);
                                     this.layoutRafId = null;
                                 }
                                 document.querySelectorAll("svg text").forEach(el => {
                                     if (!el.dataset.origTransform && el.hasAttribute("transform")) {
                                         el.dataset.origTransform = el.getAttribute("transform");
                                     }
                                 });
                                 const fieldIds = Object.keys(this.fieldMap);
                                 // Step 1: set text + reset x, preserving any prefix/suffix around the blank
                                 fieldIds.forEach(id => {
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
                                     const value = this.getFieldValue(id);
                                     const firstX = tspan.dataset.origX.split(/\s+/)[0];
                                     if (!value || String(value).trim() === "") {
                                         tspan.textContent = tspan.dataset.origText;
                                         tspan.setAttribute("x", tspan.dataset.origX);
                                     } else {
                                         // Detect prefix label + blank + suffix punctuation
                                         const m = tspan.dataset.origText.match(/^([^_]*?)(_+)(.*)$/);
                                         const prefix = m ? m[1] : "";
                                         const suffix = m ? m[3] : "";
                                         tspan.textContent = prefix + String(value) + suffix;
                                         tspan.setAttribute("x", firstX);
                                     }
                                 });
                                 // Step 2: reset every shifted text element back to original transform
                                 document.querySelectorAll("svg text[data-orig-transform]").forEach(el => {
                                     el.setAttribute("transform", el.dataset.origTransform);
                                 });
                                 // Step 3: measure and shift siblings after the browser renders
                                 this.layoutRafId = requestAnimationFrame(() => {
                                     fieldIds.forEach(id => {
                                         const el = document.getElementById(id);
                                         if (!el) return;
                                         const tspan = el.querySelector("tspan");
                                         if (!tspan || !tspan.dataset.origX) return;
                                         const value = this.getFieldValue(id);
                                         if (!value || String(value).trim() === "") return;
                                         const xCoords = tspan.dataset.origX.split(/\s+/).map(parseFloat).filter(n => !isNaN(n));
                                         if (xCoords.length === 0) return;
                                         const firstX = xCoords[0];
                                         const lastX = xCoords[xCoords.length - 1];
                                         const charWidth = xCoords.length > 1 ? (xCoords[1] - xCoords[0]) : 8.66;
                                         const blankWidth = (lastX - firstX) + charWidth;
                                         let actualWidth = 0;
                                         try { actualWidth = tspan.getComputedTextLength(); } catch(e) {}
                                         const shift = blankWidth - actualWidth;
                                         if (Math.abs(shift) <= 0.5) return;
                                         const origLineTransform = el.dataset.origTransform || el.getAttribute("transform");
                                         const lineY = tspan.getAttribute("y");
                                         const parent = el.parentElement;
                                         if (!parent) return;
                                         Array.from(parent.querySelectorAll("text")).forEach(t => {
                                             if (t === el) return;
                                             const ts = t.querySelector("tspan");
                                             if (!ts) return;
                                             const tBaseTransform = t.dataset.origTransform || t.getAttribute("transform") || "";
                                             if (tBaseTransform !== origLineTransform) return;
                                             if (ts.getAttribute("y") !== lineY) return;
                                             const tOrigX = ts.dataset.origX || ts.getAttribute("x") || "0";
                                             const tFirstX = parseFloat(tOrigX.split(/\s+/)[0]);
                                             if (isNaN(tFirstX) || tFirstX <= firstX) return;
                                             const currentTransform = t.getAttribute("transform") || tBaseTransform;
                                             t.setAttribute("transform", currentTransform + " translate(" + (-shift) + " 0)");
                                         });
                                     });
                                     this.layoutRafId = null;
                                 });
                             },
                             printCertificate() {
                                if (!this.formBusinessName || !this.formBusinessName.trim() || !this.formOwner || !this.formOwner.trim() || !this.formType || !this.formType.trim() || !this.formLocation || !this.formLocation.trim() || !this.formDay || !this.formDay.trim() || !this.formMonth || !this.formMonth.trim()) {
                                    alert("Please fill in all required fields before printing.");
                                    return;
                                }
                                window.print();
                            } 
                         }'>

                        <!-- Form Controls -->
                        <div class="mb-6 space-y-4 print:hidden">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Owner (Resident)</label>
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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name of Business</label>
                                    <input type="text" x-model="formBusinessName" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="e.g. Juan's Sari-Sari Store">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name of Owner</label>
                                    <input type="text" x-model="formOwner" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Full name of owner">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type / Line of Business</label>
                                    <input type="text" x-model="formType" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="e.g. Retail / Sari-Sari Store">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Location</label>
                                    <input type="text" x-model="formLocation" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Zone / Street / Purok">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                                        <i class="fas fa-print mr-2"></i> Print Permit
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Permit Preview -->
                        <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto">
                            <?php
                            $svg_path = '../../Barangay Business Permit.svg';
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
                                // Header: Business Location (line ~291, has "Location:" prefix baked into tspan)
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
                                // Paragraph: location again (line ~346, trailing comma)
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
                                // Footer: Month (line ~431, trailing comma)
                                $svg = str_replace(
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349 219.81755',
                                    '<text id="field-month" xml:space="preserve" transform="matrix(.75 0 0 .75 72 511.8404)" font-size="17.333334" font-family="Times New Roman"><tspan y="16.182291" x="211.15349 219.81755',
                                    $svg
                                );
                                echo $svg;
                            } else {
                                echo '<p class="text-red-500 text-center py-8">Error: Could not load permit template.</p>';
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
