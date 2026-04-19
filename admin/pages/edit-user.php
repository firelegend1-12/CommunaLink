<?php
/**
 * Edit User Page - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect_to('../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $_SESSION['error_message'] = 'Invalid security token. Please refresh the page and try again.';
        $redirect_id = (int) ($_POST['user_id'] ?? 0);
        redirect_to('edit-user.php?id=' . $redirect_id);
    }

    $target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $fullname = sanitize_input($_POST['fullname'] ?? '');
    $username = sanitize_input($_POST['username'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $role = sanitize_input($_POST['role'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (!$target_user_id || !$fullname || !$username || !$email || !$role) {
        $_SESSION['error_message'] = 'Please complete all required fields with valid values.';
        redirect_to('edit-user.php?id=' . (int) $target_user_id);
    }

    if (!in_array($role, ['admin', 'resident', 'official'], true)) {
        $_SESSION['error_message'] = 'Invalid role specified.';
        redirect_to('edit-user.php?id=' . (int) $target_user_id);
    }

    if ($role === 'official') {
        $official_position = sanitize_input($_POST['official_position'] ?? '');
        if (!in_array($official_position, ['barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'], true)) {
            $_SESSION['error_message'] = 'Please select a valid official position.';
            redirect_to('edit-user.php?id=' . (int) $target_user_id);
        }
        $final_role = $official_position;
    } else {
        $final_role = $role;
    }

    if (!in_array($final_role, ['admin', 'resident', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'], true)) {
        $_SESSION['error_message'] = 'Invalid role specified.';
        redirect_to('edit-user.php?id=' . (int) $target_user_id);
    }

    $admin_cap = get_admin_user_cap();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$target_user_id]);
        $existing_user = $stmt->fetch();

        if (!$existing_user) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'User not found.';
            redirect_to('user-management.php');
        }

        // Enforce max admin limit on promotions.
        if ($final_role === 'admin' && $existing_user['role'] !== 'admin') {
            $admin_count = count_admin_users($pdo, true);
            if ($admin_count >= $admin_cap) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Admin account limit reached ({$admin_cap}).";
                redirect_to('edit-user.php?id=' . (int) $target_user_id);
            }
        }

        // Keep at least one admin in the system.
        if ($existing_user['role'] === 'admin' && $final_role !== 'admin') {
            $admin_count = count_admin_users($pdo, true);
            if ($admin_count <= 1) {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'At least one admin account must remain in the system.';
                redirect_to('edit-user.php?id=' . (int) $target_user_id);
            }
        }

        $dup_stmt = $pdo->prepare('SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ? LIMIT 1');
        $dup_stmt->execute([$email, $username, $target_user_id]);
        if ($dup_stmt->fetch()) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Email or username is already used by another account.';
            redirect_to('edit-user.php?id=' . (int) $target_user_id);
        }

        $update_fields = 'fullname = ?, username = ?, email = ?, role = ?';
        $update_params = [$fullname, $username, $email, $final_role];

        if (!empty($new_password)) {
            $passwordValidation = PasswordSecurity::validatePassword($new_password);
            if (!$passwordValidation['valid']) {
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Password does not meet security requirements: ' . implode(' ', $passwordValidation['errors']);
                redirect_to('edit-user.php?id=' . (int) $target_user_id);
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_fields .= ', password = ?';
            $update_params[] = $hashed_password;
        }

        $update_params[] = $target_user_id;
        $update_stmt = $pdo->prepare("UPDATE users SET {$update_fields} WHERE id = ?");
        $update_stmt->execute($update_params);

        $pdo->commit();

        $old_snapshot = sprintf('fullname=%s; username=%s; email=%s; role=%s', $existing_user['fullname'], $existing_user['username'], $existing_user['email'], $existing_user['role']);
        $new_snapshot = sprintf('fullname=%s; username=%s; email=%s; role=%s', $fullname, $username, $email, $final_role);
        log_activity_db(
            $pdo,
            'edit',
            'user',
            $target_user_id,
            'User account updated',
            $old_snapshot,
            $new_snapshot
        );

        $_SESSION['success_message'] = 'User updated successfully.';
        redirect_to('user-management.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Failed to update user due to a database error.';
        error_log('Edit user failed: ' . $e->getMessage());
        redirect_to('edit-user.php?id=' . (int) $target_user_id);
    }
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user = null;
$error_message = '';

if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error_message = "User account not discovered in the registry.";
        }
    } catch (PDOException $e) {
        $error_message = "Database synchronization failed.";
    }
}

$page_title = "Manage Account Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
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
        .form-input {
            transition: all 0.2s ease;
        }
        .form-input:focus {
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        role: '<?= $user ? (in_array($user['role'], ['admin', 'resident']) ? $user['role'] : 'official') : '' ?>',
        officialPosition: '<?= $user && !in_array($user['role'], ['admin', 'resident']) ? $user['role'] : '' ?>',
        showPassword: false
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
                            <a href="user-management.php" class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                <i class="fas fa-arrow-left text-sm"></i>
                            </a>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Modify Account</h1>
                        </div>
                        
                        <?php include '../partials/user-dropdown.php'; ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-12">
                <div class="max-w-2xl mx-auto">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-6 mb-8 rounded-r-2xl shadow-sm">
                            <i class="fas fa-check-circle mr-3"></i>
                            <span class="font-bold uppercase tracking-widest text-xs"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                        </div>
                    <?php unset($_SESSION['success_message']); endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-6 mb-8 rounded-r-2xl shadow-sm">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <span class="font-bold uppercase tracking-widest text-xs"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                        </div>
                    <?php unset($_SESSION['error_message']); endif; ?>

                    <?php if ($error_message): ?>
                        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-6 mb-8 rounded-r-2xl shadow-sm">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <span class="font-bold uppercase tracking-widest text-xs"><?= $error_message ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($user): ?>
                        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/40 border border-slate-200 overflow-hidden">
                            <div class="px-10 py-10 bg-gradient-to-br from-indigo-600 to-indigo-800 text-white relative">
                                <div class="absolute right-10 top-10 opacity-10"><i class="fas fa-user-cog text-8xl"></i></div>
                                <div class="flex items-center gap-6">
                                    <div class="h-20 w-20 rounded-[1.5rem] bg-white/20 backdrop-blur-md flex items-center justify-center text-3xl font-black border border-white/30 shadow-inner">
                                        <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-black tracking-tight"><?= htmlspecialchars($user['fullname']) ?></h2>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest">@<?= htmlspecialchars($user['username']) ?></span>
                                            <span class="bg-emerald-400 w-2 h-2 rounded-full shadow-[0_0_8px_rgba(52,211,153,0.8)]"></span>
                                            <span class="text-[10px] font-bold opacity-80 uppercase tracking-tighter">Verified System Access</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form action="edit-user.php" method="POST" class="p-10 space-y-10">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="space-y-6">
                                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Profile Information</h3>
                                        
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Full Identity Name</label>
                                            <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required 
                                                   class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">System Handle (Username)</label>
                                            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required 
                                                   class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Connectivity (Email)</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required 
                                                   class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-6">
                                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Access & Security</h3>
                                        
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Authorization Role</label>
                                            <select name="role" x-model="role" required 
                                                    class="form-input w-full bg-white border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 transition shadow-sm appearance-none">
                                                <option value="resident">Resident</option>
                                                <option value="official">Official / Staff</option>
                                                <option value="admin">System Admin</option>
                                            </select>
                                        </div>
                                        
                                        <div x-show="role === 'official'" x-cloak x-transition>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Assigned Position</label>
                                            <select name="official_position" x-model="officialPosition" :required="role === 'official'"
                                                    class="form-input w-full bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 text-sm font-bold text-amber-900 focus:ring-2 focus:ring-amber-500 transition shadow-sm appearance-none">
                                                <option value="">Select official position...</option>
                                                <option value="barangay-captain">Barangay Captain</option>
                                                <option value="kagawad">Kagawad</option>
                                                <option value="barangay-secretary">Barangay Secretary</option>
                                                <option value="barangay-treasurer">Barangay Treasurer</option>
                                                <option value="barangay-tanod">Barangay Tanod</option>
                                            </select>
                                        </div>
                                        
                                        <div class="pt-4">
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Reset Credential</label>
                                            <div class="relative">
                                                <input :type="showPassword ? 'text' : 'password'" name="new_password" 
                                                       class="form-input w-full bg-rose-50 border border-rose-100 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-rose-500 transition shadow-sm"
                                                       placeholder="Leave blank to maintain current">
                                                <button type="button" @click="showPassword = !showPassword" class="absolute right-5 top-1/2 -translate-y-1/2 text-rose-300 hover:text-rose-500 transition">
                                                    <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                                </button>
                                            </div>
                                            <p class="text-[9px] font-bold text-rose-400 mt-2 px-1 italic text-center uppercase tracking-tighter">Force a security refresh by entering a new password</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-slate-50 rounded-[1.5rem] p-6 flex flex-wrap gap-6 items-center border border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white flex items-center justify-center text-slate-400 shadow-sm"><i class="fas fa-history text-xs"></i></div>
                                        <div>
                                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Last Check-in</p>
                                            <p class="text-xs font-bold text-slate-700"><?= $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never active' ?></p>
                                        </div>
                                    </div>
                                    <div class="h-8 w-px bg-slate-200 hidden md:block"></div>
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white flex items-center justify-center text-slate-400 shadow-sm"><i class="fas fa-calendar-plus text-xs"></i></div>
                                        <div>
                                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Enrolled on</p>
                                            <p class="text-xs font-bold text-slate-700"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pt-6 flex gap-4 border-t border-slate-100">
                                    <a href="user-management.php" class="flex-1 px-8 py-5 rounded-2xl text-xs font-black uppercase text-center text-slate-400 bg-white border border-slate-200 hover:bg-slate-50 transition">Discard Changes</a>
                                    <button type="submit" class="flex-[2] px-8 py-5 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-600/30 transition transform active:scale-95">Update Security Record</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
