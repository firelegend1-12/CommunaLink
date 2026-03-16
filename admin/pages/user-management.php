<?php
/**
 * User Management Page
 */

// Include authentication system
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/permission_checker.php';

// Check if user is logged in
require_login();

// Check if user is an admin or barangay official, otherwise redirect
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'])) {
    redirect_to('../index.php');
}

// Handle delete request
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if ($user_id) {
        // Don't allow deleting your own account
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['error_message'] = "You cannot delete your own account.";
        } else {
            try {
                // First check if this user is associated with a resident
                $stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $resident = $stmt->fetch();
                
                if ($resident) {
                    $_SESSION['error_message'] = "This user is associated with a resident profile and cannot be deleted.";
                } else {
                    // Get user details for logging (before deletion)
                    $stmt = $pdo->prepare("SELECT username, fullname, email, role FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_to_delete = $stmt->fetch();
                    
                    if (!$user_to_delete) {
                        $_SESSION['error_message'] = "Failed to delete user. User not found.";
                    } else {
                        // Prepare user details for logging
                        $user_details = "Username: {$user_to_delete['username']}, Full Name: {$user_to_delete['fullname']}, Email: {$user_to_delete['email']}, Role: {$user_to_delete['role']}";
                        
                        // Delete the user
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            // Log the deletion
                            log_activity_db(
                                $pdo,
                                'delete',
                                'user',
                                $user_id,
                                "Deleted user: {$user_to_delete['fullname']} ({$user_to_delete['username']})",
                                $user_details,
                                null
                            );
                            $_SESSION['success_message'] = "User has been deleted successfully.";
                        } else {
                            $_SESSION['error_message'] = "Failed to delete user. User not found.";
                        }
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error occurred while deleting user.";
                // For debugging: error_log($e->getMessage());
            }
        }
    } else {
        $_SESSION['error_message'] = "Invalid user ID.";
    }
    
    // Redirect to refresh the page
    redirect_to('user-management.php');
}

// Page title
$page_title = "User Management - CommuniLink";

try {
    // Handle search
    $search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $sql = "SELECT id, username, fullname, email, role, created_at, last_login FROM users";
    $params = [];

    if (!empty($search_query)) {
        $sql .= " WHERE username LIKE ? OR fullname LIKE ? OR email LIKE ?";
        $search_param = "%{$search_query}%";
        $params = [$search_param, $search_param, $search_param];
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    $users = [];
    $_SESSION['error_message'] = "A database error occurred while fetching users.";
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
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen" x-data="{ showDeleteModal: false, userToDelete: null }">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Manage Users</h1>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
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
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div id="user-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
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
                    <!-- Header with Search -->
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center">
                        <div class="flex-grow w-full sm:w-auto mb-2 sm:mb-0 sm:mr-4">
                            <form action="user-management.php" method="GET">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" name="search" id="search" 
                                        class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Search by user..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </form>
                        </div>
                        <div class="flex items-center space-x-2">
                             <a href="add-user.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-plus mr-2"></i>
                                Add Account
                            </a>
                            <a href="logs.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300">
                                <i class="fas fa-list mr-2"></i>
                                View Logs
                            </a>
                        </div>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No users found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge 
                                                        <?php 
                                                        switch($user['role']) {
                                                            case 'admin':
                                                                echo 'bg-purple-100 text-purple-800';
                                                                break;
                                                            case 'barangay-captain':
                                                                echo 'bg-red-100 text-red-800';
                                                                break;
                                                            case 'kagawad':
                                                                echo 'bg-orange-100 text-orange-800';
                                                                break;
                                                            case 'barangay-secretary':
                                                                echo 'bg-blue-100 text-blue-800';
                                                                break;
                                                            case 'barangay-treasurer':
                                                                echo 'bg-green-100 text-green-800';
                                                                break;
                                                            case 'barangay-tanod':
                                                                echo 'bg-yellow-100 text-yellow-800';
                                                                break;
                                                            case 'resident':
                                                                echo 'bg-blue-100 text-blue-800';
                                                                break;
                                                            default:
                                                                echo 'bg-gray-100 text-gray-800';
                                                        }
                                                        ?>">
                                                        <?php echo htmlspecialchars(get_role_display_name($user['role'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?>
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
                                                                 class="fixed z-50 w-32 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                                                 :style="'top: ' + top + 'px; left: ' + left + 'px;'">
                                                                <div class="py-1">
                                                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Edit</a>
                                                                    <button type="button" @click="showDeleteModal = true; userToDelete = {id: <?php echo $user['id']; ?>, name: '<?php echo htmlspecialchars($user['fullname']); ?>'}; open = false;" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
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

    <!-- Delete Confirmation Modal -->
    <div 
        x-show="showDeleteModal" 
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto" 
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div 
                x-show="showDeleteModal" 
                x-transition:enter="ease-out duration-300" 
                x-transition:enter-start="opacity-0" 
                x-transition:enter-end="opacity-100" 
                x-transition:leave="ease-in duration-200" 
                x-transition:leave-start="opacity-100" 
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                aria-hidden="true"
                @click="showDeleteModal = false"
            ></div>

            <!-- Modal panel -->
            <div 
                x-show="showDeleteModal" 
                x-transition:enter="ease-out duration-300" 
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                x-transition:leave="ease-in duration-200" 
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
            >
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Delete User
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" x-text="'Are you sure you want to delete ' + userToDelete?.name + '? This action cannot be undone.'"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <form method="POST" action="user-management.php">
                        <input type="hidden" name="user_id" x-bind:value="userToDelete?.id">
                        <button 
                            type="submit" 
                            name="delete_user" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Delete
                        </button>
                    </form>
                    <button 
                        type="button" 
                        @click="showDeleteModal = false" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Function to get role display name
    function getRoleDisplayName(role) {
        const roleNames = {
            'admin': 'Administrator',
            'barangay-captain': 'Barangay Captain',
            'kagawad': 'Kagawad',
            'barangay-secretary': 'Barangay Secretary',
            'barangay-treasurer': 'Barangay Treasurer',
            'barangay-tanod': 'Barangay Tanod',
            'resident': 'Resident'
        };
        return roleNames[role] || role.charAt(0).toUpperCase() + role.slice(1);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('user-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });

    // Live search for users
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const tableBody = document.querySelector('tbody.bg-white');
        let lastValue = searchInput.value;
        let controller = null;

        function renderUsers(users) {
            if (!users.length) {
                tableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No users found.</td></tr>`;
                return;
            }
            tableBody.innerHTML = users.map(user => `
                <tr>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${user.id}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap\">
                        <div class=\"text-sm font-medium text-gray-900\">${user.fullname}</div>
                        <div class=\"text-sm text-gray-500\">${user.username}</div>
                    </td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${user.email}</td>
                    <td class=\"px-6 py-4 whitespace-nowrap\"><span class=\"status-badge ${user.role === 'admin' ? 'bg-purple-100 text-purple-800' : (user.role === 'barangay-captain' ? 'bg-red-100 text-red-800' : (user.role === 'kagawad' ? 'bg-orange-100 text-orange-800' : (user.role === 'barangay-secretary' ? 'bg-blue-100 text-blue-800' : (user.role === 'barangay-treasurer' ? 'bg-green-100 text-green-800' : (user.role === 'barangay-tanod' ? 'bg-yellow-100 text-yellow-800' : (user.role === 'resident' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800'))))))}\">${getRoleDisplayName(user.role)}</span></td>
                    <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${user.last_login ? new Date(user.last_login).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }) : 'Never'}</td>
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
                                <div x-show=\"open\" @click.away=\"open = false\" x-cloak class=\"fixed z-50 w-32 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5\" :style=\"'top: ' + top + 'px; left: ' + left + 'px;'\">
                                    <div class=\"py-1\">
                                        <a href=\"edit-user.php?id=${user.id}\" class=\"block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50\">Edit</a>
                                        <button type=\"button\" onclick=\"window.showDeleteModal && (window.showDeleteModal = true, window.userToDelete = {id: ${user.id}, name: '${user.fullname}'});\" class=\"block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100\">Delete</button>
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
            fetch(`../partials/search-users.php?search=${encodeURIComponent(value)}`, { signal: controller.signal })
                .then(res => res.json())
                .then(data => renderUsers(data))
                .catch(() => {});
        });
    });
    </script>
</body>
</html> 