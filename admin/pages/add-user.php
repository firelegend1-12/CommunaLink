<?php
/**
 * Add User Page - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect_to('../index.php');
}

$admin_cap = get_admin_user_cap();
$admin_count_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$admin_count = (int) $admin_count_stmt->fetchColumn();

$page_title = "Add New User";
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
        role: '', 
        password: '',
        strength: 0,
        validatePassword(pw) {
            let s = 0;
            if (pw.length >= 8) s++;
            if (/[A-Z]/.test(pw)) s++;
            if (/[0-9]/.test(pw)) s++;
            if (/[^A-Za-z0-9]/.test(pw)) s++;
            this.strength = s;
        }
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
                                <i class="fas fa-chevron-left text-sm"></i>
                            </a>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Create New User</h1>
                        </div>
                        
                        <?php include '../partials/user-dropdown.php'; ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-12">
                <div class="max-w-xl mx-auto">
                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-r-xl shadow-sm" role="status">
                        <div class="flex items-center">
                            <i class="fas fa-user-shield mr-3"></i>
                            <p class="font-bold text-xs uppercase tracking-widest">Admin Slots Used: <?php echo $admin_count; ?> / <?php echo $admin_cap; ?></p>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-5 mb-8 rounded-r-2xl shadow-sm animate-pulse">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-3"></i>
                                <p class="font-bold text-xs uppercase tracking-widest"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                            </div>
                        </div>
                    <?php unset($_SESSION['error_message']); endif; ?>

                    <div class="bg-white rounded-[2rem] shadow-xl shadow-indigo-100/30 border border-slate-200 overflow-hidden transform transition hover:shadow-2xl">
                        <div class="px-8 pt-10 pb-6 border-b border-slate-50 bg-gradient-to-br from-white to-slate-50/50">
                            <div class="h-14 w-14 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-600/30 mb-6 drop-shadow-md">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h2 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Registration Form</h2>
                            <p class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest">Enroll new members to the system</p>
                        </div>
                        
                        <form action="../partials/add-user-handler.php" method="POST" class="p-8 space-y-8">
                            <?php echo csrf_field(); ?>
                            
                            <!-- Basic Info Section -->
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Full Legal Name</label>
                                    <input type="text" name="fullname" required 
                                           class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" 
                                           placeholder="e.g. John Doe">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Email Address (Login Username)</label>
                                    <input type="email" name="email" required 
                                           class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" 
                                           placeholder="user@example.com">
                                </div>
                                
                                <div class="relative">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Secure Password</label>
                                    <input type="password" name="password" required x-model="password" @input="validatePassword($event.target.value)"
                                           class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" 
                                           placeholder="Min. 8 characters">
                                    
                                    <!-- Strength Meter -->
                                    <div class="mt-3 px-1">
                                        <div class="flex gap-1 h-1">
                                            <div class="flex-1 rounded-full transition-all duration-500" :class="strength >= 1 ? 'bg-rose-500' : 'bg-slate-200'"></div>
                                            <div class="flex-1 rounded-full transition-all duration-500" :class="strength >= 2 ? 'bg-amber-500' : 'bg-slate-200'"></div>
                                            <div class="flex-1 rounded-full transition-all duration-500" :class="strength >= 3 ? 'bg-indigo-500' : 'bg-slate-200'"></div>
                                            <div class="flex-1 rounded-full transition-all duration-500" :class="strength >= 4 ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                                        </div>
                                        <p class="text-[9px] font-black uppercase mt-2 tracking-widest text-right" 
                                           :class="strength <= 1 ? 'text-rose-500' : (strength <= 3 ? 'text-amber-500' : 'text-emerald-500')">
                                            <span x-text="strength <= 1 ? 'Weak Protection' : (strength === 2 ? 'Fair Security' : (strength === 3 ? 'Strong Vault' : 'Military Grade'))"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="h-px bg-slate-100"></div>
                            
                            <!-- Access Section -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">System Role</label>
                                    <div class="flex gap-3">
                                        <label class="flex-1 cursor-pointer">
                                            <input type="radio" name="role" value="official" x-model="role" class="sr-only peer">
                                            <div class="p-4 border-2 border-slate-100 rounded-2xl text-center transition hover:bg-emerald-50 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 group">
                                                <i class="fas fa-award text-slate-400 group-hover:text-emerald-600 mb-2"></i>
                                                <div class="text-[10px] font-black text-slate-900 uppercase">Official</div>
                                            </div>
                                        </label>
                                        <label class="flex-1 cursor-pointer">
                                            <input type="radio" name="role" value="admin" x-model="role" class="sr-only peer">
                                            <div class="p-4 border-2 border-slate-100 rounded-2xl text-center transition hover:bg-rose-50 peer-checked:border-rose-600 peer-checked:bg-rose-50 group">
                                                <i class="fas fa-shield-alt text-slate-400 group-hover:text-rose-600 mb-2"></i>
                                                <div class="text-[10px] font-black text-slate-900 uppercase">Admin</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div x-show="role === 'official'" x-cloak x-transition class="md:col-span-2">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Official Position</label>
                                    <select name="official_position" :required="role === 'official'" 
                                            class="form-input w-full bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 text-sm font-bold text-amber-900 focus:ring-2 focus:ring-amber-500 transition shadow-sm appearance-none">
                                        <option value="">Select official position...</option>
                                        <option value="barangay-captain">Barangay Captain</option>
                                        <option value="kagawad">Kagawad</option>
                                        <option value="barangay-secretary">Barangay Secretary</option>
                                        <option value="barangay-treasurer">Barangay Treasurer</option>
                                        <option value="barangay-tanod">Barangay Tanod</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="pt-6 flex gap-4">
                                <a href="user-management.php" class="flex-1 px-8 py-5 rounded-2xl text-xs font-black uppercase text-center text-slate-500 bg-slate-100 hover:bg-slate-200 transition">Cancel</a>
                                <button type="submit" class="flex-[2] px-8 py-5 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-600/30 transition transform active:scale-95">Complete Enrollment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>