<?php
/**
 * User Account Page - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "My Account Profile";

try {
    // Get current user's data
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT fullname, email, username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        logout();
        redirect_to('../../index.php');
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Could not fetch user data.";
    redirect_to('../index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - CommunaLink</title>
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
        .form-input { transition: all 0.2s ease; }
        .form-input:focus { transform: translateY(-1px); }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        showPasswords: false
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
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Personal Workspace</h1>
                            <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-indigo-200">Security Vault</span>
                        </div>
                        
                        <?php include '../partials/user-dropdown.php'; ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-12">
                <div class="max-w-3xl mx-auto">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div id="account-success-alert" class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-6 mb-8 rounded-r-3xl shadow-sm animate-fade-in">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                                    <p class="font-bold text-xs uppercase tracking-widest"><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                                </div>
                                <button onclick="this.parentElement.parentElement.remove()" class="text-emerald-300 hover:text-emerald-500 transition"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    <?php unset($_SESSION['success_message']); endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-6 mb-8 rounded-r-3xl shadow-sm">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <span class="font-bold text-xs uppercase tracking-widest"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                        </div>
                    <?php unset($_SESSION['error_message']); endif; ?>

                    <div class="bg-white rounded-[3rem] shadow-2xl shadow-indigo-100/50 border border-slate-200 overflow-hidden transform transition duration-500">
                        <div class="px-12 py-12 bg-gradient-to-br from-slate-900 to-indigo-950 text-white relative">
                            <div class="absolute right-12 top-1/2 -translate-y-1/2 opacity-5"><i class="fas fa-shield-alt text-[120px]"></i></div>
                            <div class="flex items-center gap-8 relative z-10">
                                <div class="h-24 w-24 rounded-[1.8rem] bg-indigo-500/20 backdrop-blur-xl flex items-center justify-center text-4xl font-black border border-white/10 shadow-lg">
                                    <?= strtoupper(substr($user['fullname'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h2 class="text-3xl font-black tracking-tighter uppercase mb-1"><?= htmlspecialchars($user['fullname']) ?></h2>
                                    <div class="flex items-center gap-3">
                                        <span class="bg-white/10 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-white/5">@<?= htmlspecialchars($user['username']) ?></span>
                                        <span class="bg-indigo-500/20 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-indigo-400/20 text-indigo-300">ADMINISTRATOR</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="../partials/account-handler.php" method="POST" class="p-12 space-y-12">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                                <!-- Profile Column -->
                                <div class="space-y-8">
                                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-4">
                                        <div class="h-8 w-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 text-sm"><i class="fas fa-user-edit"></i></div>
                                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Public Profile</h3>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Display Name</label>
                                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required 
                                               class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Email Connection</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required 
                                               class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                    </div>
                                </div>

                                <!-- Security Column -->
                                <div class="space-y-8">
                                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-4">
                                        <div class="h-8 w-8 rounded-lg bg-rose-50 flex items-center justify-center text-rose-400 text-sm"><i class="fas fa-lock"></i></div>
                                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-rose-500">Security Gate</h3>
                                    </div>
                                    
                                    <div class="bg-rose-50/50 p-6 rounded-[2rem] border border-rose-100/50 space-y-6">
                                        <div>
                                            <label class="block text-[10px] font-black text-rose-400 uppercase tracking-widest mb-2 ml-1">Current Password</label>
                                            <input :type="showPasswords ? 'text' : 'password'" name="current_password" 
                                                   class="form-input w-full bg-white border border-rose-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 transition" 
                                                   placeholder="Verify current status">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[10px] font-black text-rose-400 uppercase tracking-widest mb-2 ml-1 text-center">Update Credentials</label>
                                            <div class="grid grid-cols-1 gap-4 mt-2">
                                                <input :type="showPasswords ? 'text' : 'password'" name="new_password" 
                                                       class="form-input w-full bg-white border border-rose-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 transition" 
                                                       placeholder="Enter New Password">
                                                <input :type="showPasswords ? 'text' : 'password'" name="confirm_password" 
                                                       class="form-input w-full bg-white border border-rose-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 transition" 
                                                       placeholder="Confirm Selection">
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-center">
                                            <button type="button" @click="showPasswords = !showPasswords" class="text-[9px] font-black uppercase text-rose-400 hover:text-rose-600 transition tracking-widest">
                                                <i class="fas" :class="showPasswords ? 'fa-eye-slash' : 'fa-eye'"></i> Toggle Visibility
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-10 flex flex-col sm:flex-row gap-4 border-t border-slate-100">
                                <div class="flex-1">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 italic">Notice</p>
                                    <p class="text-[10px] text-slate-500 leading-relaxed max-w-xs">Updating your credentials will require a new verification on your next session log. Ensure your connection details are active.</p>
                                </div>
                                <button type="submit" class="sm:w-auto px-10 py-5 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-600/30 transition transform active:scale-95 flex items-center justify-center gap-3">
                                    <i class="fas fa-save shadow-sm"></i> Commit All Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('account-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.classList.add('opacity-0', 'transition-all', 'duration-1000', 'translate-x-12');
                setTimeout(() => alert.remove(), 1000);
            }, 5000);
        }
    });
    </script>
</body>
</html>