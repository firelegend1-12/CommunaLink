<?php
require_once '../partials/admin_auth.php';
/**
 * New Certificate of Indigency Page
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_login();

$page_title = "Certificate of Indigency";

// Fetch residents for the dropdown
$residents = [];
try {
    $resident_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, first_name, last_name, middle_initial, gender, address, date_of_birth, place_of_birth, civil_status, occupation FROM residents ORDER BY last_name ASC");
    $residents = $resident_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($residents as &$resident_row) {
        $resident_row['full_name'] = normalize_document_text($resident_row['full_name'] ?? '');
        $resident_row['document_age'] = normalize_document_text(resident_document_age($resident_row['date_of_birth'] ?? ''));
        $resident_row['document_civil_status'] = normalize_document_text(resident_document_civil_status_label($resident_row['civil_status'] ?? ''));
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
        <?php
include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?php
echo $page_title; ?></h1>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php
echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php
echo substr($_SESSION['fullname'], 0, 1); ?>
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
                    <?php
display_flash_messages(); ?>
                    <div class="bg-white rounded-lg shadow p-8"
                         x-data='{
                             residents: <?php echo json_encode($residents); ?>,
                             selectedResidentId: null,
                             selectedResident: {},
                             formName: "",
                             formAge: "",
                             formCivilStatus: "",
                             formDay: new Date().getDate().toString(),
                             formMonth: new Date().toLocaleDateString("en-US", { month: "long" }),
                             layoutRafId: null,
                             fieldMap: {
                                 "field-name": "formName",
                                 "field-age": "formAge",
                                 "field-civil-status": "formCivilStatus",
                                 "field-day": "formDay",
                                 "field-month": "formMonth"
                             },
                             init() {
                                 this.$watch("formName", () => this.recomputeLayout());
                                 this.$watch("formAge", () => this.recomputeLayout());
                                 this.$watch("formCivilStatus", () => this.recomputeLayout());
                                 this.$watch("formDay", () => this.recomputeLayout());
                                 this.$watch("formMonth", () => this.recomputeLayout());
                                 this.$nextTick(() => this.recomputeLayout());
                             },
                             normalizeCertificateText(value) {
                                 return window.CommunaLinkDocumentSvg.normalizeText(value);
                             },
                             selectResident() {
                                 if (!this.selectedResidentId) return;
                                 const resident = this.residents.find(r => r.id == this.selectedResidentId);
                                 if (resident) {
                                     this.selectedResident = resident;
                                     this.formName = this.normalizeCertificateText(resident.full_name || "");
                                     this.formAge = this.normalizeCertificateText(resident.document_age || "");
                                     this.formCivilStatus = this.normalizeCertificateText(resident.document_civil_status || "");
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
                                if (!this.formName || !this.formName.trim() || !this.formAge || !this.formAge.trim() || !this.formDay || !this.formDay.trim() || !this.formMonth || !this.formMonth.trim()) {
                                    alert("Please fill in all required fields before printing.");
                                    return;
                                }
                                window.print();
                            }
                         }'>

                        <!-- Form Controls -->
                        <div class="mb-6 space-y-4 print:hidden">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Resident</label>
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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" x-model="formName" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Enter full name">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                                    <input type="text" x-model="formAge" required class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2" placeholder="Age">
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
                                        <i class="fas fa-print mr-2"></i> Print Certificate
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Certificate Preview -->
                        <div class="printable-area max-w-4xl mx-auto my-8 p-8 bg-white shadow-lg overflow-auto">
                            <?php
                            $svg_path = '../../Certificate of Indigency.svg';
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
                                    '<text xml:space="preserve" transform="matrix(.75 0 0 .75 72 322.21876)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="79.939579 88.60364 92.4534 102.090488 111.72757 115.57733 125.21442 130.02873 144.4632 154.10028 159.87068 165.64109 169.49085 179.12793 188.76502 193.57933 206.09316 209.94292 219.58 229.21709">single/married/widow</tspan></text>',
                                    '<text id="field-civil-status" xml:space="preserve" transform="matrix(.75 0 0 .75 72 322.21876)" font-size="17.333334" font-family="Arial"><tspan y="16.258463" x="79.939579 88.60364 92.4534 102.090488 111.72757 115.57733 125.21442 130.02873 144.4632 154.10028 159.87068 165.64109 169.49085 179.12793 188.76502 193.57933 206.09316 209.94292 219.58 229.21709">single/married/widow</tspan></text>',
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
