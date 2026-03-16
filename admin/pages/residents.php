<?php
/**
 * Residents Management Page
 */

// Include authentication system
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
require_login();

// Page title
$page_title = "Residents - CommuniLink";

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
    <title><?php echo $page_title; ?></title>
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
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
                        <h1 class="text-2xl font-semibold text-gray-800">Manage Residents</h1>
                        
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
                <div class="bg-white rounded-lg shadow">
                    <!-- Header with Search and Add Button -->
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center">
                        <div class="flex-grow w-full sm:w-auto mb-2 sm:mb-0 sm:mr-4">
                            <form action="residents.php" method="GET">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" name="search" id="search" 
                                        class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Search by name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </form>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="add-resident.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-plus mr-2"></i>
                                Add Resident
                            </a>
                             <a href="../partials/export-residents-csv.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-file-csv mr-2"></i> Export CSV
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
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <?php if ($resident['profile_image_path'] && file_exists('../' . $resident['profile_image_path'])): ?>
                                                                <img class="h-10 w-10 rounded-full object-cover" src="<?php echo htmlspecialchars('../' . $resident['profile_image_path']); ?>" alt="Profile Image">
                                                            <?php else: ?>
                                                                <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold">
                                                                    <?php echo strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($resident['last_name'] . ', ' . $resident['first_name'] . ' ' . $resident['middle_initial']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resident['display_id_number']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resident['age']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resident['civil_status']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resident['gender']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resident['voter_status']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative">
                                                    <div class="relative inline-block text-left" x-data="{ open: false, top: 0, left: 0 }">
                                                        <button type="button" x-ref="dropdownBtn" @click="
                                                            open = !open;
                                                            if (open) {
                                                                const rect = $refs.dropdownBtn.getBoundingClientRect();
                                                                top = rect.bottom + window.scrollY;
                                                                left = rect.left + window.scrollX;
                                                            }
                                                        " class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-200 focus:outline-none" aria-haspopup="true" aria-expanded="false">
                                                            <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <circle cx="4" cy="10" r="1.5"/>
                                                                <circle cx="10" cy="10" r="1.5"/>
                                                                <circle cx="16" cy="10" r="1.5"/>
                                                            </svg>
                                                        </button>
                                                        <template x-teleport="body">
                                                            <div x-show="open" @click.away="open = false" x-cloak
                                                                 class="fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                                                 :style="'top: ' + top + 'px; left: ' + left + 'px;'">
                                                                <div class="py-1">
                                                                    <a href="edit-resident.php?id=<?php echo $resident['id']; ?>" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Edit</a>
                                                                    <button type="button" @click="generateId(<?php echo $resident['id']; ?>); open = false;" class="block w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50">Generate ID</button>
                                                                    <form action="../partials/delete-resident-handler.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this resident?');">
                                                                        <input type="hidden" name="resident_id" value="<?php echo $resident['id']; ?>">
                                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
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
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    ${resident.profile_image_path && resident.profile_image_path !== '' ?
                                        `<img class=\"h-10 w-10 rounded-full object-cover\" src=\"../${resident.profile_image_path}\" alt=\"Profile Image\">` :
                                        `<div class=\"h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold\">${(resident.first_name[0] + resident.last_name[0]).toUpperCase()}</div>`
                                    }
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">${resident.last_name}, ${resident.first_name} ${resident.middle_initial ?? ''}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${resident.display_id_number}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${resident.age}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${resident.civil_status}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${resident.gender}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${resident.voter_status}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative">
                            <div class="relative inline-block text-left" x-data="{ open: false, top: 0, left: 0 }">
                                <button type="button" x-ref="dropdownBtn" @click="
                                    open = !open;
                                    if (open) {
                                        const rect = $refs.dropdownBtn.getBoundingClientRect();
                                        top = rect.bottom + window.scrollY;
                                        left = rect.left + window.scrollX;
                                    }
                                " class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-200 focus:outline-none" aria-haspopup="true" aria-expanded="false">
                                    <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="4" cy="10" r="1.5"/>
                                        <circle cx="10" cy="10" r="1.5"/>
                                        <circle cx="16" cy="10" r="1.5"/>
                                    </svg>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open" @click.away="open = false" x-cloak
                                         class="fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                         :style="'top: ' + top + 'px; left: ' + left + 'px;'">
                                        <div class="py-1">
                                            <a href="edit-resident.php?id=${resident.id}" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Edit</a>
                                            <button type="button" onclick="window.pageData().generateId(${resident.id});" class="block w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50">Generate ID</button>
                                            <form action="../partials/delete-resident-handler.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this resident?');">
                                                <input type="hidden" name="resident_id" value="${resident.id}">
                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }

            searchInput.addEventListener('input', function() {
                const value = searchInput.value;
                if (value === lastValue) return;
                lastValue = value;
                if (controller) controller.abort();
                controller = new AbortController();
                fetch(`../partials/search-residents.php?search=${encodeURIComponent(value)}`, { signal: controller.signal })
                    .then(res => res.json())
                    .then(data => renderResidents(data))
                    .catch(() => {});
            });
        });
    </script>
</body>
</html> 