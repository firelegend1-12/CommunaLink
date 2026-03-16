<?php
/**
 * Business Records Management Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Business Records - CommuniLink";

try {
    // Handle search
    $search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $sql = "SELECT b.*, r.first_name, r.last_name, r.middle_initial, r.id_number 
            FROM businesses b
            JOIN residents r ON b.resident_id = r.id";
    $params = [];
            
    if (!empty($search_query)) {
        $sql .= " WHERE b.business_name LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ?";
        $search_param = "%{$search_query}%";
        $params = [$search_param, $search_param, $search_param];
    }
    $sql .= " ORDER BY b.business_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $businesses = $stmt->fetchAll();

} catch (PDOException $e) {
    $businesses = [];
    // error_log("Business records page DB error: " . $e->getMessage());
    $_SESSION['error_message'] = "A database error occurred while fetching business records.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>
        
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Registered Businesses</h1>

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
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-4 p-4 rounded bg-green-100 text-green-800 border border-green-200">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="mb-4 p-4 rounded bg-red-100 text-red-800 border border-red-200">
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center">
                        <div class="flex-grow w-full sm:w-auto mb-2 sm:mb-0 sm:mr-4">
                            <form action="business-records.php" method="GET">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-search"></i></span>
                                    <input type="text" name="search" id="search" class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search by business or owner name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </form>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="business-application-form.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-plus mr-2"></i> Transaction
                            </a>
                            <a href="../partials/export-business-csv.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-file-csv mr-2"></i> Export CSV
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner ID Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($businesses)): ?>
                                        <tr><td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No businesses found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($businesses as $biz): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($biz['id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($biz['business_code']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($biz['business_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($biz['last_name'] . ', ' . $biz['first_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($biz['id_number']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($biz['business_type']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span class="status-badge <?php echo $biz['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo htmlspecialchars($biz['status']); ?>
                                                    </span>
                                                </td>
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
                                                                    <button type="button" @click="open = false; viewBusiness(<?php echo $biz['id']; ?>)" class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">View</button>
                                                                    <a href="edit-business.php?id=<?php echo $biz['id']; ?>" class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">Edit</a>
                                                                    <a href="business-clearance.php?id=<?php echo $biz['id']; ?>" target="_blank" class="block px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50">Generate Clearance</a>
                                                                    <button type="button" @mousedown="open = false; setTimeout(() => deleteBusiness(<?php echo $biz['id']; ?>, '<?php echo htmlspecialchars($biz['business_name']); ?>'), 10);" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">Delete</button>
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

    <script>
    function viewBusiness(businessId) {
        // Show the modal
        document.getElementById('viewBusinessModal').classList.remove('hidden');
        // Show loading state
        document.getElementById('viewBusinessContent').innerHTML = `<p class='text-gray-700'>Loading business details...</p>`;
        // Fetch business details via AJAX
        fetch(`../partials/search-businesses.php?id=${businessId}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.length > 0) {
                    const biz = data[0];
                    document.getElementById('viewBusinessContent').innerHTML = `
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Business Code:</span> ${biz.business_code || ''}</div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Business Name:</span> ${biz.business_name}</div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Owner:</span> ${biz.last_name}, ${biz.first_name} ${biz.middle_initial ? biz.middle_initial + '.' : ''}</div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Owner ID Number:</span> ${biz.id_number || ''}</div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Business Type:</span> ${biz.business_type}</div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Address:</span> ${biz.address}</div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Status:</span> <span class='px-2 py-1 rounded ${biz.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}'>${biz.status}</span></div>
                        <div class='mb-2'><span class='font-semibold text-gray-800'>Date Registered:</span> ${biz.date_registered || ''}</div>
                    `;
                } else {
                    document.getElementById('viewBusinessContent').innerHTML = `<p class='text-red-600'>Business details not found.</p>`;
                }
            })
            .catch(() => {
                document.getElementById('viewBusinessContent').innerHTML = `<p class='text-red-600'>Failed to load business details.</p>`;
            });
    }

    function editBusiness(businessId) {
        // Redirect to edit business page
        window.location.href = 'edit-business.php?id=' + businessId;
    }

    // Live search for businesses
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const tableBody = document.querySelector('tbody.bg-white');
        let lastValue = searchInput.value;
        let controller = null;

        function renderBusinesses(businesses) {
            if (!businesses.length) {
                tableBody.innerHTML = `<tr><td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No businesses found.</td></tr>`;
                return;
            }
            tableBody.innerHTML = businesses.map(biz => `
                <tr>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${biz.id}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${biz.business_code}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900\">${biz.business_name}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\">${biz.last_name}, ${biz.first_name}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${biz.id_number}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${biz.business_type}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm\"><span class=\"px-2.5 py-1 inline-flex text-sm leading-5 font-semibold rounded-full ${biz.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}\">${biz.status}</span></td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm font-medium relative\">
                        <div class=\"relative inline-block text-left\" x-data=\"{ open: false, top: 0, left: 0 }\">
                            <button type=\"button\" x-ref=\"dropdownBtn\" @click=\"
                                open = !open;
                                if (open) {
                                    const rect = $refs.dropdownBtn.getBoundingClientRect();
                                    top = rect.bottom + window.scrollY;
                                    left = rect.left + window.scrollX;
                                }
                            \" class=\"flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-200 focus:outline-none\" aria-haspopup=\"true\" aria-expanded=\"false\">
                                <svg class=\"w-5 h-5 text-gray-500\" fill=\"currentColor\" viewBox=\"0 0 20 20\"><circle cx=\"4\" cy=\"10\" r=\"1.5\"/><circle cx=\"10\" cy=\"10\" r=\"1.5\"/><circle cx=\"16\" cy=\"10\" r=\"1.5\"/></svg>
                            </button>
                            <template x-teleport=\"body\">
                                <div x-show=\"open\" @click.away=\"open = false\" x-cloak class=\"fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5\" :style=\"'top: ' + top + 'px; left: ' + left + 'px;'\">
                                    <div class=\"py-1\">
                                        <button type=\"button\" @click=\"open = false; viewBusiness(${biz.id})\" class=\"block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50\">View</button>
                                        <a href=\"edit-business.php?id=${biz.id}\" class=\"block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50\">Edit</a>
                                        <a href=\"business-clearance.php?id=${biz.id}\" target=\"_blank\" class=\"block px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50\">Generate Clearance</a>
                                        <button type=\"button\" onmousedown=\"this.closest('[x-data]').__x.$data.open = false; setTimeout(() => deleteBusiness(${biz.id}, '${biz.business_name.replace(/'/g, "\\'")}'), 10);\" class=\"block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50\">Delete</button>
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
            fetch(`../partials/search-businesses.php?search=${encodeURIComponent(value)}`, { signal: controller.signal })
                .then(res => res.json())
                .then(data => renderBusinesses(data))
                .catch(() => {});
        });
    });
    </script>
    <!-- Custom Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50 hidden">
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
        <h2 class="text-lg font-semibold mb-2 text-gray-800">Delete Business</h2>
        <p class="mb-4 text-gray-700" id="deleteModalMessage"></p>
        <div class="flex justify-end space-x-2">
          <button id="cancelDeleteBtn" class="px-4 py-2 rounded bg-gray-200 text-gray-800 hover:bg-gray-300">Cancel</button>
          <button id="confirmDeleteBtn" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">Delete</button>
        </div>
      </div>
    </div>
    <!-- View Business Modal -->
    <div id="viewBusinessModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50 hidden">
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg relative">
        <button id="closeViewBusinessBtn" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
        <h2 class="text-lg font-semibold mb-4 text-gray-800">Business Details</h2>
        <div id="viewBusinessContent">
          <!-- Placeholder content, replace with AJAX-loaded details if needed -->
          <p class="text-gray-700">Loading business details...</p>
        </div>
      </div>
    </div>
    <script>
    let deleteBusinessId = null;
    let deleteBusinessName = '';

    function deleteBusiness(businessId, businessName) {
        deleteBusinessId = businessId;
        deleteBusinessName = businessName;
        document.getElementById('deleteModalMessage').textContent =
            `Are you sure you want to delete the business "${businessName}"? This action cannot be undone.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('cancelDeleteBtn').onclick = function() {
            document.getElementById('deleteModal').classList.add('hidden');
            deleteBusinessId = null;
        };
        document.getElementById('confirmDeleteBtn').onclick = function() {
            if (deleteBusinessId) {
                // Create and submit the form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../partials/delete-business-handler.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'business_id';
                input.value = deleteBusinessId;

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        };
        // Close view business modal
        document.getElementById('closeViewBusinessBtn').onclick = function() {
            document.getElementById('viewBusinessModal').classList.add('hidden');
        };
        // Optional: close modal when clicking outside the modal content
        document.getElementById('viewBusinessModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html> 