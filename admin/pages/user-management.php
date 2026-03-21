<?php
/**
 * User Management - Modernized
 */
require_once '../partials/admin_auth.php';
require_once '../../includes/functions.php';

// Note: Advanced User Management is typically restricted to the 'admin' role
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "User Management";

try {
    // Get stats
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $total_stmt->fetchColumn();
    
    $official_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('admin', 'resident')");
    $total_officials = $official_stmt->fetchColumn();
    
    $resident_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident'");
    $total_residents = $resident_stmt->fetchColumn();

    // Get filter
    $role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
    
    // Build query
    $sql = "SELECT id, username, fullname, email, role, created_at, last_login FROM users WHERE 1=1";
    $params = [];
    
    if ($role_filter) {
        if ($role_filter === 'official') {
            $sql .= " AND role NOT IN ('admin', 'resident')";
        } else {
            $sql .= " AND role = ?";
            $params[] = $role_filter;
        }
    }
    
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $users = [];
    $total_users = 0; $total_officials = 0; $total_residents = 0;
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - CommuniLink</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .user-row:hover .user-actions { opacity: 1; }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        search: '',
        currentRole: '<?= $role_filter ?>'
    }">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white/80 backdrop-blur-md shadow-sm z-10 border-b border-slate-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center gap-4">
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">User Administration</h1>
                            <span class="bg-slate-100 text-slate-600 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-slate-200">System Access</span>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <a href="add-user.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center transition shadow-md shadow-indigo-500/20">
                                <i class="fas fa-user-plus mr-2"></i> CREATE ACCOUNT
                            </a>
                            
                            <div class="h-8 w-px bg-slate-200 mx-2"></div>
                            
                            <?php include '../partials/user-dropdown.php'; ?>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-8">
                <!-- Summary Row -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 group hover:border-indigo-200 transition">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl"><i class="fas fa-users"></i></div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Accounts</p>
                                <h3 class="text-2xl font-black text-slate-900"><?= $total_users ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 group hover:border-emerald-200 transition">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl"><i class="fas fa-award"></i></div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Officials</p>
                                <h3 class="text-2xl font-black text-emerald-600"><?= $total_officials ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 group hover:border-blue-200 transition">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl"><i class="fas fa-house-user"></i></div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Residents</p>
                                <h3 class="text-2xl font-black text-blue-600"><?= $total_residents ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-indigo-500 bg-indigo-600 text-white relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 opacity-10 rotate-12"><i class="fas fa-shield-alt text-7xl"></i></div>
                        <p class="text-[10px] font-black text-indigo-200 uppercase tracking-widest mb-1">System Status</p>
                        <h3 class="text-xl font-black">All Systems Online</h3>
                        <p class="text-[10px] text-indigo-100 font-bold opacity-75 mt-2">ACCESS AUDIT LOGS ➔</p>
                    </div>
                </div>

                <!-- Main Table Area -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Filters/Search Toolbar -->
                    <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap justify-between items-center bg-slate-50/30 gap-4">
                        <div class="relative flex-1 max-w-md">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" x-model="search" placeholder="Search by name, email, or username..." 
                                   class="w-full bg-white border border-slate-200 rounded-xl pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200">
                                <a href="user-management.php" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition <?php echo empty($role_filter) ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-indigo-600'; ?>">ALL</a>
                                <a href="user-management.php?role=admin" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition <?php echo $role_filter === 'admin' ? 'bg-white text-rose-600 shadow-sm' : 'text-slate-500 hover:text-rose-600'; ?>">ADMINS</a>
                                <a href="user-management.php?role=official" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition <?php echo $role_filter === 'official' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-emerald-600'; ?>">OFFICIALS</a>
                                <a href="user-management.php?role=resident" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition <?php echo $role_filter === 'resident' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500 hover:text-blue-600'; ?>">RESIDENTS</a>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">User Profile</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Role Type</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Account Details</th>
                                    <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Activity</th>
                                    <th class="px-6 py-4 text-right text-[10px] font-black text-slate-500 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic font-medium">No users found match your criteria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): 
                                        $display_role = get_role_display_name($user['role']);
                                        $role_class = '';
                                        if ($user['role'] === 'admin') $role_class = 'bg-rose-50 text-rose-700 border-rose-100';
                                        elseif ($user['role'] === 'resident') $role_class = 'bg-blue-50 text-blue-700 border-blue-100';
                                        else $role_class = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                    ?>
                                        <tr class="user-row hover:bg-indigo-50/30 transition group" 
                                            x-show="search === '' || 
                                                   '<?= strtolower(addslashes($user['fullname'])) ?>'.includes(search.toLowerCase()) || 
                                                   '<?= strtolower(addslashes($user['username'])) ?>'.includes(search.toLowerCase()) || 
                                                   '<?= strtolower(addslashes($user['email'])) ?>'.includes(search.toLowerCase())">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="h-10 w-10 flex-shrink-0 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 border border-slate-200 group-hover:border-indigo-200 transition">
                                                        <i class="fas fa-user text-lg"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($user['fullname']) ?></div>
                                                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">@<?= htmlspecialchars($user['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg border <?= $role_class ?>">
                                                    <?= $display_role ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-xs font-bold text-slate-600"><?= htmlspecialchars($user['email']) ?></div>
                                                <div class="text-[10px] text-slate-400 mt-0.5">Created: <?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($user['last_login']): ?>
                                                    <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-tighter">
                                                        <i class="fas fa-clock mr-1"></i> ACTIVE LOGIN
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5"><?= date('M d, h:i A', strtotime($user['last_login'])) ?></div>
                                                <?php else: ?>
                                                    <div class="text-[10px] font-bold text-slate-300 uppercase tracking-tighter">NEVER LOGGED IN</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="user-actions opacity-0 flex items-center justify-end gap-2 transition duration-300">
                                                    <a href="edit-user.php?id=<?= $user['id'] ?>" class="text-indigo-600 bg-white hover:bg-indigo-600 hover:text-white p-2 rounded-xl transition shadow-sm border border-slate-200 hover:border-indigo-600" title="Manage Account">
                                                        <i class="fas fa-cog"></i>
                                                    </a>
                                                    
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <form onsubmit="return confirm('WARNING: Are you sure you want to delete this user? This action is irreversible.');" 
                                                              action="../partials/delete-user-handler.php" method="POST" class="inline">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" class="text-rose-500 bg-white hover:bg-rose-500 hover:text-white p-2 rounded-xl transition shadow-sm border border-slate-200 hover:border-rose-500" title="Delete User">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>