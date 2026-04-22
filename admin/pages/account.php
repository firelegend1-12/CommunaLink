<?php
require_once '../partials/admin_auth.php';
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

    $role_map = [
        'admin' => 'Administrator',
        'official' => 'Official',
        'barangay-officials' => 'Barangay Official',
        'barangay-kagawad' => 'Barangay Kagawad',
        'barangay-tanod' => 'Barangay Tanod',
    ];
    $role_slug = strtolower(trim((string)($user['role'] ?? '')));
    $role_badge_label = $role_map[$role_slug] ?? ucwords(str_replace('-', ' ', $role_slug));
    $security_chip_label = $role_badge_label . ' Vault';

    $username_value = (string)($user['username'] ?? '');
    $email_value = (string)($user['email'] ?? '');
    $is_linked_account = (stripos($username_value, 'linked.r') === 0) || (stripos($email_value, '@linked.local') !== false);
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
        .form-input { transition: all 0.2s ease; }
        .form-input:focus { transform: translateY(-1px); }
        .requirement-check { transition: all 0.2s ease; }
        .requirement-met { color: #10b981; }
        .requirement-unmet { color: #6b7280; }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        showPasswords: false,
        originalEmail: <?php echo htmlspecialchars(json_encode((string)($user['email'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>,
        emailInput: <?php echo htmlspecialchars(json_encode((string)($user['email'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>,
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
        requirements: {
            minLength: false,
            hasUppercase: false,
            hasLowercase: false,
            hasNumber: false,
            hasSpecial: false
        },
        validatePassword(pw) {
            this.requirements.minLength = pw.length >= 8;
            this.requirements.hasUppercase = /[A-Z]/.test(pw);
            this.requirements.hasLowercase = /[a-z]/.test(pw);
            this.requirements.hasNumber = /[0-9]/.test(pw);
            this.requirements.hasSpecial = /[^A-Za-z0-9]/.test(pw);
        },
        isPasswordStrong() {
            return this.requirements.minLength &&
                this.requirements.hasUppercase &&
                this.requirements.hasLowercase &&
                this.requirements.hasNumber &&
                this.requirements.hasSpecial;
        },
        isEmailChanged() {
            return (this.emailInput || '').trim().toLowerCase() !== (this.originalEmail || '').trim().toLowerCase();
        },
        needsCurrentPassword() {
            return this.isEmailChanged() || this.newPassword !== '' || this.confirmPassword !== '';
        },
        currentPasswordValid() {
            return !this.needsCurrentPassword() || (this.currentPassword || '').trim() !== '';
        },
        passwordsMatch() {
            return this.newPassword !== '' && this.newPassword === this.confirmPassword;
        },
        init() {
            this.validatePassword(this.newPassword || '');
        }
    }">
        <!-- Sidebar Navigation -->
        <?php
include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white/80 backdrop-blur-md shadow-sm z-10 border-b border-slate-200">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center gap-4">
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Account Settings</h1>
                            <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-indigo-200"><?= htmlspecialchars($security_chip_label) ?></span>
                        </div>
                        
                        <?php
include '../partials/user-dropdown.php'; ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-12">
                <div class="max-w-3xl mx-auto">
                    <?php
if (isset($_SESSION['success_message'])): ?>
                        <div id="account-success-alert" class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-6 mb-8 rounded-r-3xl shadow-sm animate-fade-in">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                                    <p class="font-bold text-xs uppercase tracking-widest"><?php
echo htmlspecialchars($_SESSION['success_message']); ?></p>
                                </div>
                                <button onclick="this.parentElement.parentElement.remove()" class="text-emerald-300 hover:text-emerald-500 transition"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    <?php
unset($_SESSION['success_message']); endif; ?>

                    <?php
if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-6 mb-8 rounded-r-3xl shadow-sm">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <span class="font-bold text-xs uppercase tracking-widest"><?php
echo htmlspecialchars($_SESSION['error_message']); ?></span>
                        </div>
                    <?php
unset($_SESSION['error_message']); endif; ?>

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
                                        <span class="bg-indigo-500/20 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-indigo-400/20 text-indigo-300"><?= htmlspecialchars($role_badge_label) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="../partials/account-handler.php" method="POST" class="p-12 space-y-12">
                            <?php echo csrf_field(); ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                                <!-- Profile Column -->
                                <div class="space-y-8">
                                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-4">
                                        <div class="h-8 w-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 text-sm"><i class="fas fa-user-edit"></i></div>
                                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Public Profile</h3>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Display Name</label>
                                        <input type="text" name="fullname" value="<?php
echo htmlspecialchars($user['fullname']); ?>" required 
                                               class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Email Connection</label>
                                        <input type="email" name="email" x-model="emailInput" required <?= $is_linked_account ? 'readonly' : '' ?>
                                               class="form-input w-full border border-slate-200 rounded-2xl px-6 py-4.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-sm <?= $is_linked_account ? 'bg-slate-100 cursor-not-allowed' : 'bg-slate-50' ?>">
                                        <?php if ($is_linked_account): ?>
                                            <p class="text-xs text-slate-500 mt-2"><i class="fas fa-lock mr-1"></i> Email connection is system-managed for created linked accounts and cannot be changed manually.</p>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($is_linked_account): ?>
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                                        <p class="text-xs font-bold uppercase tracking-widest text-slate-600 mb-2">Account Username</p>
                                        <p class="text-sm text-slate-700 leading-relaxed">
                                            The resident's registered email is used at sign-in to discover this tied privileged account.
                                        </p>
                                    </div>
                                    <?php endif; ?>

                                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600 mb-3">Password Requirements</p>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 requirement-check" :class="requirements.minLength ? 'requirement-met' : 'requirement-unmet'">
                                                <i :class="requirements.minLength ? 'fas fa-check-circle' : 'fas fa-circle'" class="text-xs"></i>
                                                <span class="text-xs font-medium">At least 8 characters</span>
                                            </div>
                                            <div class="flex items-center gap-2 requirement-check" :class="requirements.hasUppercase ? 'requirement-met' : 'requirement-unmet'">
                                                <i :class="requirements.hasUppercase ? 'fas fa-check-circle' : 'fas fa-circle'" class="text-xs"></i>
                                                <span class="text-xs font-medium">Uppercase letter (A-Z)</span>
                                            </div>
                                            <div class="flex items-center gap-2 requirement-check" :class="requirements.hasLowercase ? 'requirement-met' : 'requirement-unmet'">
                                                <i :class="requirements.hasLowercase ? 'fas fa-check-circle' : 'fas fa-circle'" class="text-xs"></i>
                                                <span class="text-xs font-medium">Lowercase letter (a-z)</span>
                                            </div>
                                            <div class="flex items-center gap-2 requirement-check" :class="requirements.hasNumber ? 'requirement-met' : 'requirement-unmet'">
                                                <i :class="requirements.hasNumber ? 'fas fa-check-circle' : 'fas fa-circle'" class="text-xs"></i>
                                                <span class="text-xs font-medium">Number (0-9)</span>
                                            </div>
                                            <div class="flex items-center gap-2 requirement-check" :class="requirements.hasSpecial ? 'requirement-met' : 'requirement-unmet'">
                                                <i :class="requirements.hasSpecial ? 'fas fa-check-circle' : 'fas fa-circle'" class="text-xs"></i>
                                                <span class="text-xs font-medium">Special character (!@#$%^&*)</span>
                                            </div>
                                        </div>
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
                                            <input :type="showPasswords ? 'text' : 'password'" name="current_password" x-model="currentPassword"
                                                   :class="currentPasswordValid() ? 'border-rose-100' : 'border-rose-400 bg-rose-50'"
                                                   class="form-input w-full bg-white border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 transition" 
                                                   placeholder="Verify current status">
                                            <p x-show="needsCurrentPassword() && !currentPasswordValid()" x-cloak class="text-xs text-rose-600 mt-2">
                                                <i class="fas fa-exclamation-circle mr-1"></i> Current password is required for email/password updates.
                                            </p>
                                            <p x-show="needsCurrentPassword() && currentPasswordValid()" x-cloak class="text-xs text-emerald-600 mt-2">
                                                <i class="fas fa-check-circle mr-1"></i> Current password entered.
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[10px] font-black text-rose-400 uppercase tracking-widest mb-2 ml-1 text-center">Update Credentials</label>
                                            <div class="grid grid-cols-1 gap-4 mt-2">
                                                <div>
                                                    <input :type="showPasswords ? 'text' : 'password'" name="new_password" x-model="newPassword" @input="validatePassword(newPassword)"
                                                       :class="newPassword === '' || isPasswordStrong() ? 'border-rose-100' : 'border-rose-400 bg-rose-50'"
                                                       class="form-input w-full bg-white border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 transition" 
                                                       placeholder="Enter New Password">
                                                    <p x-show="newPassword !== '' && !isPasswordStrong()" x-cloak class="text-xs text-rose-600 mt-2">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Password does not meet all requirements.
                                                    </p>
                                                    <p x-show="newPassword !== '' && isPasswordStrong()" x-cloak class="text-xs text-emerald-600 mt-2">
                                                        <i class="fas fa-check-circle mr-1"></i> Password meets requirements.
                                                    </p>
                                                </div>
                                                <div>
                                                    <input :type="showPasswords ? 'text' : 'password'" name="confirm_password" x-model="confirmPassword"
                                                       :class="confirmPassword === '' || passwordsMatch() ? 'border-rose-100' : 'border-rose-400 bg-rose-50'"
                                                       class="form-input w-full bg-white border rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 transition" 
                                                       placeholder="Confirm Selection">
                                                    <p x-show="confirmPassword !== '' && !passwordsMatch()" x-cloak class="text-xs text-rose-600 mt-2">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Passwords do not match.
                                                    </p>
                                                    <p x-show="confirmPassword !== '' && passwordsMatch()" x-cloak class="text-xs text-emerald-600 mt-2">
                                                        <i class="fas fa-check-circle mr-1"></i> Passwords match.
                                                    </p>
                                                </div>
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


