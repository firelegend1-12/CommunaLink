<?php
/**
 * Residents Management Page
 */

// Include admin authentication and session management
require_once '../partials/admin_auth.php';

// Page-specific requirements
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Page title
$page_title = "Residents - CommunaLink";

try {
    // Handle search
    $search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $sql = "SELECT *, 
            CASE 
                WHEN id_number IS NOT NULL AND id_number != '' THEN id_number
                ELSE CONCAT('BR-', YEAR(created_at), '-', LPAD(id, 4, '0'))
            END AS display_id_number
            FROM residents";
    $params = [];

    if (!empty($search_query)) {
        $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?";
        $search_param = "%{$search_query}%";
        $params = [$search_param, $search_param, $search_param];
    }
    $sql .= " ORDER BY last_name, first_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $residents = $stmt->fetchAll();

} catch (PDOException $e) {
    $residents = [];
    // error_log("Residents page DB error: " . $e->getMessage());
    $_SESSION['error_message'] = "A database error occurred while fetching residents.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }

        .id-card {
            width: 3.375in;
            height: 2.125in;
            background-color: white;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 324 204"><path fill="%23F7FAFC" d="M0 0h324v204H0z"/><path fill-opacity="0.1" fill="%23EDF2F7" d="M162 0c-44.183 0-80 35.817-80 80 0 44.183 35.817 80 80 80s80-35.817 80-80c0-44.183-35.817-80-80-80z"/><path fill-opacity="0.1" fill="%23E2E8F0" d="M162 20c-33.137 0-60 26.863-60 60s26.863 60 60 60 60-26.863 60-60-26.863-60-60-60z"/></svg>');
            background-size: cover;
            font-family: Arial, sans-serif;
            font-size: 7px;
            color: #2D3748; /* gray-800 */
        }
        .philippine-map {
            background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/1/14/Blank_map_of_the_Philippines.svg/512px-Blank_map_of_the_Philippines.svg.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.1;
        }
        .text-xxs { font-size: 6px; }

        @media print {
            body * {
                visibility: hidden;
            }
            .id-modal-content, .id-modal-content * {
                visibility: visible;
            }
            .id-modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                transform: none; /* Reset on-screen scaling for print */
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .id-card {
                box-shadow: none;
                margin: 0; /* Let flexbox handle centering */
                transform: scale(1.1); /* Slightly enlarge for better printing */
                border: 1px solid #000; /* Add border for printing */
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen" x-data="pageData()">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center gap-4">
                            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Residents</h1>
                            <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase"><?php echo count($residents); ?> Total</span>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-3 text-sm text-gray-700 hover:text-gray-900 focus:outline-none transition group">
                                <span class="font-medium group-hover:text-blue-600"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white text-sm font-bold shadow-sm group-hover:shadow-md transition">
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
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-xl shadow-xl py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                
                                <a href="account.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-user-circle mr-2 text-gray-400"></i> My Account</a>
                                <div class="border-t border-gray-100 mt-1"></div>
                                <a href="../../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt mr-2"></i> Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div id="residents-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                    echo '<p>' . htmlspecialchars($_SESSION['success_message']) . '</p>';
                    echo '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo display_error($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                }
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Header with Search and Add Button -->
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-wrap justify-between items-center bg-gray-50/50">
                        <div class="flex-grow w-full sm:w-auto mb-2 sm:mb-0 sm:mr-6">
                            <form action="residents.php" method="GET">
                                <div class="relative group">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 group-focus-within:text-blue-500 transition">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" name="search" id="search" 
                                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm"
                                        placeholder="Search by name, ID number..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </form>
                        </div>
                        <div class="flex items-center space-x-3">
                             <div class="flex bg-gray-100 p-1 rounded-xl border border-gray-200" x-data="{ voterLocal: 'All' }" @filter-voters.window="voterLocal = $event.detail">
                                <button @click="$dispatch('filter-voters', 'All')" :class="voterLocal === 'All' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition uppercase">All</button>
                                <button @click="$dispatch('filter-voters', 'Yes')" :class="voterLocal === 'Yes' ? 'bg-white text-green-600 shadow-sm' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition uppercase">Voters</button>
                                <button @click="$dispatch('filter-voters', 'No')" :class="voterLocal === 'No' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition uppercase">Non-Voters</button>
                            </div>
                            <a href="../partials/export-residents-csv.php" class="bg-white hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-semibold border border-gray-200 flex items-center transition shadow-sm">
                                <i class="fas fa-file-csv mr-2 text-green-500"></i> Export CSV
                            </a>
                            <a href="add-resident.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center transition shadow-md shadow-blue-500/20">
                                <i class="fas fa-plus mr-2 text-xs"></i> Add Resident
                            </a>
                        </div>
                    </div>
                    
                    <!-- Residents Table -->
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Number</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Civil Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voter Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="residents-table-body">
                                    <?php if (empty($residents)): ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No residents found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($residents as $resident): ?>
                                            <tr class="hover:bg-blue-50/30 transition group">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <?php if (!empty($resident['profile_image_path'])): ?>
                                                                <img class="h-10 w-10 rounded-xl object-cover shadow-sm" src="<?php echo htmlspecialchars('../' . $resident['profile_image_path']); ?>" alt="Profile Image" onerror="this.onerror=null; this.outerHTML='<div class=\'h-10 w-10 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 text-sm font-bold shadow-sm\'><?php echo strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1)); ?></div>';">
                                                            <?php else: ?>
                                                                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 text-sm font-bold shadow-sm">
                                                                    <?php echo strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-bold text-gray-900 group-hover:text-blue-700 transition"><?php echo htmlspecialchars($resident['last_name'] . ', ' . $resident['first_name'] . ' ' . $resident['middle_initial']); ?></div>
                                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($resident['gender']); ?> / <?php echo htmlspecialchars($resident['age']); ?> yrs</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2.5 py-1 text-xs font-mono font-bold bg-gray-100 text-gray-700 rounded-lg"><?php echo htmlspecialchars($resident['display_id_number']); ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($resident['age']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($resident['civil_status']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($resident['gender']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($resident['voter_status'] === 'Yes'): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <i class="fas fa-check-circle mr-1"></i> Registered
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            Non-voter
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="openView(<?php echo $resident['id']; ?>)" class="text-blue-600 hover:bg-blue-100 p-2 rounded-lg transition" title="Quick View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Quick View Slide-over Panel -->
    <div x-show="showView" 
         class="fixed inset-0 overflow-hidden z-50" 
         x-cloak
         aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <div class="absolute inset-0 overflow-hidden">
            <!-- Background backdrop -->
            <div x-show="showView" 
                 x-transition:enter="ease-in-out duration-500" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in-out duration-500" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="absolute inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="showView = false" aria-hidden="true"></div>

            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div x-show="showView" 
                     x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700" 
                     x-transition:enter-start="translate-x-full" 
                     x-transition:enter-end="translate-x-0" 
                     x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700" 
                     x-transition:leave-start="translate-x-0" 
                     x-transition:leave-end="translate-x-full" 
                     class="pointer-events-auto w-screen max-w-2xl">
                    <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl">
                        <!-- Head -->
                        <div class="bg-blue-700 px-6 py-8 sm:px-8">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-4">
                                    <template x-if="residentData && residentData.profile">
                                        <div class="h-20 w-20 rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center overflow-hidden shadow-lg">
                                            <template x-if="residentData.profile.profile_image_path">
                                                <img :src="'../' + residentData.profile.profile_image_path" class="h-full w-full object-cover">
                                            </template>
                                            <template x-if="!residentData.profile.profile_image_path">
                                                <span class="text-white text-3xl font-bold" x-text="residentData.profile.first_name[0] + residentData.profile.last_name[0]"></span>
                                            </template>
                                        </div>
                                    </template>
                                    <div>
                                        <h2 class="text-2xl font-bold text-white leading-tight" id="slide-over-title" x-text="residentData ? residentData.profile.first_name + ' ' + residentData.profile.last_name : 'Loading...'"></h2>
                                        <p class="text-blue-100 text-sm font-medium mt-1 uppercase tracking-wider" x-text="residentData ? residentData.profile.display_id_number : ''"></p>
                                    </div>
                                </div>
                                <div class="ml-3 flex h-7 items-center">
                                    <button @click="showView = false" class="rounded-lg bg-blue-800 text-blue-200 hover:text-white hover:bg-blue-600 transition p-2 focus:outline-none">
                                        <span class="sr-only">Close panel</span>
                                        <i class="fas fa-times text-xl"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="relative flex-1 px-6 py-8 sm:px-8">
                            <template x-if="loadingResident">
                                <div class="flex flex-col items-center justify-center h-64">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-700 mb-4"></div>
                                    <p class="text-gray-500 font-medium tracking-wide italic">Fetching resident profile...</p>
                                </div>
                            </template>

                            <template x-if="!loadingResident && residentData">
                                <div class="space-y-10">
                                    <!-- Stats Cards -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-blue-50 border border-blue-100 p-4 rounded-2xl">
                                            <div class="text-blue-700 text-2xl font-black" x-text="residentData.stats.total_requests"></div>
                                            <div class="text-blue-600 text-xs font-bold uppercase tracking-wider">Document Requests</div>
                                        </div>
                                        <div class="bg-red-50 border border-red-100 p-4 rounded-2xl">
                                            <div class="text-red-700 text-2xl font-black" x-text="residentData.stats.total_incidents"></div>
                                            <div class="text-red-600 text-xs font-bold uppercase tracking-wider">Incidents Reported</div>
                                        </div>
                                    </div>

                                    <!-- Personal Details -->
                                    <div>
                                        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-3 mb-5 flex items-center">
                                            <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                                            Personal Details
                                        </h3>
                                        <div class="grid grid-cols-2 gap-y-6 gap-x-4">
                                            <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Civil Status</p>
                                                <p class="text-sm font-bold text-gray-800" x-text="residentData.profile.civil_status"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Gender</p>
                                                <p class="text-sm font-bold text-gray-800" x-text="residentData.profile.gender"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Date of Birth</p>
                                                <p class="text-sm font-bold text-gray-800" x-text="residentData.profile.date_of_birth"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Citizenship</p>
                                                <p class="text-sm font-bold text-gray-800" x-text="residentData.profile.citizenship"></p>
                                            </div>
                                            <div class="col-span-2">
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Address</p>
                                                <p class="text-sm font-bold text-gray-800 leading-relaxed" x-text="residentData.profile.address"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Contact No.</p>
                                                <p class="text-sm font-bold text-gray-800" x-text="residentData.profile.contact_no"></p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter mb-1">Email</p>
                                                <p class="text-sm font-bold text-blue-600 truncate" x-text="residentData.profile.user_email || 'N/A'"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recent Activity -->
                                    <div class="grid grid-cols-1 gap-8">
                                        <!-- Document History -->
                                        <div>
                                            <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-3 mb-5 flex items-center">
                                                <i class="fas fa-file-alt mr-3 text-blue-500"></i>
                                                Recent Requests
                                            </h3>
                                            <div class="space-y-3">
                                                <template x-for="req in residentData.history.requests" :key="req.id">
                                                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 border border-gray-100">
                                                        <div>
                                                            <div class="text-sm font-bold text-gray-900" x-text="req.document_type"></div>
                                                            <div class="text-xs text-gray-500" x-text="new Date(req.requested_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})"></div>
                                                        </div>
                                                        <span :class="{
                                                            'px-2 py-1 text-[10px] font-black uppercase rounded-lg': true,
                                                            'bg-yellow-100 text-yellow-700': req.status === 'Pending',
                                                            'bg-blue-100 text-blue-700': req.status === 'Processing',
                                                            'bg-green-100 text-green-700': req.status === 'Ready' || req.status === 'Completed',
                                                            'bg-gray-100 text-gray-700': req.status === 'Cancelled'
                                                        }" x-text="req.status"></span>
                                                    </div>
                                                </template>
                                                <template x-if="residentData.history.requests.length === 0">
                                                    <p class="text-sm text-gray-400 italic text-center py-4 bg-gray-50 rounded-xl">No document requests found.</p>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Incident History -->
                                        <div>
                                            <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-3 mb-5 flex items-center">
                                                <i class="fas fa-bullhorn mr-3 text-red-500"></i>
                                                Recent Incidents
                                            </h3>
                                            <div class="space-y-3">
                                                <template x-for="inc in residentData.history.incidents" :key="inc.id">
                                                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 border border-gray-100">
                                                        <div>
                                                            <div class="text-sm font-bold text-gray-900" x-text="inc.type"></div>
                                                            <div class="text-xs text-gray-500" x-text="new Date(inc.reported_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})"></div>
                                                        </div>
                                                        <span :class="{
                                                            'px-2 py-1 text-[10px] font-black uppercase rounded-lg': true,
                                                            'bg-yellow-100 text-yellow-700': inc.status === 'Pending',
                                                            'bg-blue-100 text-blue-700': inc.status === 'Under Review',
                                                            'bg-green-100 text-green-700': inc.status === 'Resolved',
                                                            'bg-red-100 text-red-700': inc.status === 'Rejected'
                                                        }" x-text="inc.status"></span>
                                                    </div>
                                                </template>
                                                <template x-if="residentData.history.incidents.length === 0">
                                                    <p class="text-sm text-gray-400 italic text-center py-4 bg-gray-50 rounded-xl">No incidents reported found.</p>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-gray-100 px-6 py-6 sm:px-8 bg-gray-50/50 space-y-3">
                             <template x-if="residentData">
                                <button @click="generateId(residentData.profile.id)" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-3 rounded-xl text-sm font-bold text-center transition shadow-md">
                                    <i class="fas fa-id-card mr-2"></i> Generate ID
                                </button>
                             </template>
                            <div class="flex justify-between gap-4">
                                <template x-if="residentData">
                                    <a :href="'edit-resident.php?id=' + residentData.profile.id" class="flex-1 bg-white hover:bg-gray-50 text-gray-700 px-4 py-3 rounded-xl text-sm font-bold border border-gray-200 text-center transition shadow-sm">
                                        <i class="fas fa-edit mr-2"></i> Edit Profile
                                    </a>
                                </template>
                                <button @click="showView = false" class="flex-1 bg-gray-800 hover:bg-gray-900 text-white px-4 py-3 rounded-xl text-sm font-bold text-center transition shadow-lg">
                                    Close Panel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ID Card Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @keydown.escape.window="showModal = false">
        <div class="bg-white rounded-lg shadow-xl" @click.away="showModal = false">
            <div class="px-24 py-16">
                <div class="id-modal-content transform scale-150" x-html="modalContent">
                    <!-- Resident ID will be loaded here -->
                </div>
            </div>
            <div class="px-8 pb-8 flex justify-end space-x-2">
                <button @click="showModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Close</button>
                <button @click="printId()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                    <i class="fas fa-print mr-2"></i>
                    Print ID
                </button>
            </div>
        </div>
    </div>
    <script>
        // Prevent accidental click-through right after navigation/render.
        (function() {
            const suppressUntil = Date.now() + 450;
            document.addEventListener('click', function(e) {
                if (Date.now() < suppressUntil) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('residents-success-alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 3000);
            }
        });

        function pageData() {
            return {
                showModal: false,
                modalContent: '',
                showView: false,
                loadingResident: false,
                residentData: null,
                init() {
                    window.addEventListener('resident-open-view', (e) => {
                        const id = parseInt(e.detail, 10);
                        if (!Number.isNaN(id)) {
                            this.openView(id);
                        }
                    });

                    window.addEventListener('resident-generate-id', (e) => {
                        const id = parseInt(e.detail, 10);
                        if (!Number.isNaN(id)) {
                            this.generateId(id);
                        }
                    });
                },
                
                openView(id) {
                    this.showView = true;
                    this.loadingResident = true;
                    this.residentData = null;
                    
                    fetch(`../partials/get-resident-details.php?id=${id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.residentData = data.data;
                            } else {
                                alert(data.error);
                                this.showView = false;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Failed to fetch resident details.");
                            this.showView = false;
                        })
                        .finally(() => {
                            this.loadingResident = false;
                        });
                },

                generateId(id) {
                    this.showModal = true;
                    this.modalContent = `<div class="id-card flex items-center justify-center" style="width: 3.375in; height: 2.125in;"><i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i></div>`;
                    fetch(`resident-id.php?id=${id}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.text();
                        })
                        .then(html => {
                            this.modalContent = html;
                        })
                        .catch(error => {
                            this.modalContent = `<div class="p-4 text-red-600">Failed to load resident ID. Please try again later.</div>`;
                            console.error('There has been a problem with your fetch operation:', error);
                        });
                },
                printId() {
                    window.print();
                }
            }
        }

        // Live search for residents
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const tableBody = document.getElementById('residents-table-body');
            let lastValue = searchInput.value;
            let controller = null;

            function renderResidents(residents) {
                if (!residents.length) {
                    tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No residents found.</td></tr>`;
                    return;
                }
                tableBody.innerHTML = residents.map(resident => `
                    <tr class="hover:bg-blue-50/30 transition group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    ${resident.profile_image_path && resident.profile_image_path !== '' ?
                                        `<img class=\"h-10 w-10 rounded-xl object-cover shadow-sm\" src=\"../${resident.profile_image_path}\" alt=\"Profile Image\" onerror=\"this.onerror=null; this.outerHTML='<div class=\\\'h-10 w-10 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 text-sm font-bold shadow-sm\\\'>${(resident.first_name[0] + resident.last_name[0]).toUpperCase()}</div>';\">` :
                                        `<div class=\"h-10 w-10 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 text-sm font-bold shadow-sm\">${(resident.first_name[0] + resident.last_name[0]).toUpperCase()}</div>`
                                    }
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900 group-hover:text-blue-700 transition">${resident.last_name}, ${resident.first_name} ${resident.middle_initial ?? ''}</div>
                                    <div class="text-xs text-gray-500">${resident.gender || ''} / ${resident.age || ''} yrs</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-xs">
                             <span class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg font-bold">${resident.display_id_number}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${resident.age}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${resident.civil_status}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${resident.gender}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${resident.voter_status === 'Yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                ${resident.voter_status === 'Yes' ? '<i class=\"fas fa-check-circle mr-1\"></i> Registered' : 'Non-voter'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="window.dispatchEvent(new CustomEvent('resident-open-view', { detail: ${resident.id} }))" class="text-blue-600 hover:bg-blue-100 p-2 rounded-lg transition" title="Quick View">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }

            let currentVoterFilter = 'All';

            function triggerSearch() {
                const value = searchInput.value;
                if (controller) controller.abort();
                controller = new AbortController();
                fetch(`../partials/search-residents.php?search=${encodeURIComponent(value)}&voter_status=${currentVoterFilter}`, { signal: controller.signal })
                    .then(res => res.json())
                    .then(data => renderResidents(data))
                    .catch(() => {});
            }

            // Listen for filter events from Alpine
            window.addEventListener('filter-voters', (e) => {
                currentVoterFilter = e.detail;
                triggerSearch();
            });

            searchInput.addEventListener('input', function() {
                const value = searchInput.value;
                if (value === lastValue) return;
                lastValue = value;
                triggerSearch();
            });
        });
    </script>
</body>
</html> 
