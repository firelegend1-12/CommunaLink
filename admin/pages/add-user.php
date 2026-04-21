<?php
require_once '../partials/admin_auth.php';
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
$admin_selection_limit = min(2, $admin_cap);
$is_admin_selection_locked = $admin_count >= $admin_selection_limit;

$form_data = $_SESSION['form_data'] ?? [];
$old_fullname = htmlspecialchars((string)($form_data['fullname'] ?? ''), ENT_QUOTES, 'UTF-8');
$old_role = (string)($form_data['role'] ?? '');
$old_official_position = (string)($form_data['official_position'] ?? '');
$old_resident_id = (int)($form_data['resident_id'] ?? 0);
unset($_SESSION['form_data']);

// Show inactivity warning on this page after 3 minutes.
$session_warning_threshold = 180;

$page_title = "Add New User";
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
        .requirement-check {
            transition: all 0.2s ease;
        }
        .requirement-met {
            color: #10b981;
        }
        .requirement-unmet {
            color: #6b7280;
        }
        .modal-backdrop {
            animation: fadeIn 0.2s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .email-valid {
            border-color: #10b981 !important;
            background-color: #f0fdf4 !important;
        }
        .email-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
        .pulse-warning {
            animation: pulse-warn 2s infinite;
        }
        @keyframes pulse-warn {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#1E293B]">
    <div class="flex h-screen overflow-hidden" x-data="{ 
        role: <?php echo htmlspecialchars(json_encode($old_role), ENT_QUOTES, 'UTF-8'); ?>,
        officialPosition: <?php echo htmlspecialchars(json_encode($old_official_position), ENT_QUOTES, 'UTF-8'); ?>,
        fullnameInput: <?php echo htmlspecialchars(json_encode($old_fullname), ENT_QUOTES, 'UTF-8'); ?>,
        residentFound: <?php echo $old_resident_id > 0 ? 'true' : 'false'; ?>,
        residentEmail: '',
        residentId: <?php echo $old_resident_id > 0 ? $old_resident_id : 'null'; ?>,
        residentLookupValidating: false,
        residentError: '',
        residentPasswordConflict: false,
        residentPasswordValidating: false,
        pwdCheckTimer: null,
        password: '',
        confirmPassword: '',
        strength: 0,
        showAdminConfirmModal: false,
        pendingAdminRole: false,
        adminRoleLimit: <?php echo $admin_selection_limit; ?>,
        adminRoleLimitReached: <?php echo $is_admin_selection_locked ? 'true' : 'false'; ?>,
        sessionWarningThreshold: <?php echo $session_warning_threshold; ?>,
        sessionWarningActive: false,
        lastActivityAt: Date.now(),
        activityEventsBound: false,
        formSubmitting: false,
        requirements: {
            minLength: false,
            hasUppercase: false,
            hasLowercase: false,
            hasNumber: false,
            hasSpecial: false
        },
        init() {
            if (this.adminRoleLimitReached && this.role === 'admin') {
                this.role = 'official';
            }

            if (this.role !== 'official') {
                this.officialPosition = '';
            }

            this.bindActivityListeners();
            this.lastActivityAt = Date.now();
            this.checkSessionTimeout();
            setInterval(() => this.checkSessionTimeout(), 1000);

            // if fullname pre-filled, run resident check on init
            if (this.fullnameInput) {
                this.checkResident();
            }
        },
        bindActivityListeners() {
            if (this.activityEventsBound) {
                return;
            }

            this.activityEventsBound = true;

            const resetInactivityTimer = () => {
                this.lastActivityAt = Date.now();
                this.sessionWarningActive = false;
            };

            ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach((eventName) => {
                window.addEventListener(eventName, resetInactivityTimer, { passive: true });
            });
        },
        checkSessionTimeout() {
            const inactiveForSeconds = Math.floor((Date.now() - this.lastActivityAt) / 1000);
            this.sessionWarningActive = inactiveForSeconds >= this.sessionWarningThreshold;
        },
        validatePassword(pw) {
            let s = 0;
            
            // Check requirements
            this.requirements.minLength = pw.length >= 8;
            this.requirements.hasUppercase = /[A-Z]/.test(pw);
            this.requirements.hasLowercase = /[a-z]/.test(pw);
            this.requirements.hasNumber = /[0-9]/.test(pw);
            this.requirements.hasSpecial = /[^A-Za-z0-9]/.test(pw);
            
            // Calculate strength
            if (this.requirements.minLength) s++;
            if (this.requirements.hasUppercase) s++;
            if (this.requirements.hasNumber) s++;
            if (this.requirements.hasSpecial) s++;
            
            this.strength = s;
            // If resident record exists, check that the provided password is not the same
            if (this.residentFound) {
                this.scheduleResidentPasswordCheck();
            } else {
                this.residentPasswordConflict = false;
            }
        },
        handleFullnameInput() {
            this.residentFound = false;
            this.residentId = null;
            this.residentEmail = '';
            this.residentError = '';
            this.residentPasswordConflict = false;
        },
        handleRoleChange(newRole) {
            if (newRole === 'admin' && this.adminRoleLimitReached) {
                return;
            }

            if (newRole === 'admin' && this.role !== 'admin') {
                this.officialPosition = '';
                this.pendingAdminRole = true;
                this.showAdminConfirmModal = true;
            } else if (newRole !== 'admin') {
                this.pendingAdminRole = false;
                this.role = newRole;
                this.showAdminConfirmModal = false;
                if (newRole !== 'official') {
                    this.officialPosition = '';
                }
            }
        },
        // Lookup resident record by fullname using AJAX
        checkResident() {
            const name = (this.fullnameInput || '').trim();
            this.residentError = '';
            this.residentFound = false;
            this.residentEmail = '';
            this.residentId = null;
            if (!name) return;
            this.residentLookupValidating = true;

            const payload = new FormData();
            payload.append('fullname', name);

            fetch('../ajax/check-resident.php', { method: 'POST', body: payload, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    this.residentLookupValidating = false;
                    if (data && data.found) {
                        this.residentFound = true;
                        this.residentId = data.id || null;
                        this.residentEmail = data.email || '';
                        this.residentError = '';
                    } else {
                        this.residentFound = false;
                        this.residentId = null;
                        this.residentEmail = '';
                        this.residentError = data && data.error ? data.error : 'Name cannot be found as resident';
                    }
                    // If we found a resident, schedule a password check with current password
                    if (this.residentFound) {
                        this.scheduleResidentPasswordCheck();
                    }
                }).catch(err => {
                    this.residentLookupValidating = false;
                    this.residentFound = false;
                    this.residentError = 'Lookup failed';
                });
        },

        scheduleResidentPasswordCheck() {
            // Debounce checks to avoid excessive requests
            if (this.pwdCheckTimer) clearTimeout(this.pwdCheckTimer);
            this.pwdCheckTimer = setTimeout(() => this.checkResidentPassword(), 350);
        },

        checkResidentPassword() {
            // If resident not found or no password entered, nothing to do
            if (!this.residentFound || !this.password) {
                this.residentPasswordConflict = false;
                return;
            }

            if (!this.residentId) {
                this.residentPasswordConflict = false;
                return;
            }

            this.residentPasswordValidating = true;
            const payload = new FormData();
            payload.append('resident_id', this.residentId);
            payload.append('password', this.password);

            fetch('../ajax/check-resident-password.php', { method: 'POST', body: payload, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    this.residentPasswordValidating = false;
                    if (data && typeof data.matches !== 'undefined') {
                        this.residentPasswordConflict = !!data.matches;
                    } else {
                        this.residentPasswordConflict = false;
                    }
                }).catch(err => {
                    this.residentPasswordValidating = false;
                    this.residentPasswordConflict = false;
                });
        },
        confirmAdminRole() {
            if (this.adminRoleLimitReached) {
                this.showAdminConfirmModal = false;
                return;
            }

            this.role = 'admin';
            this.officialPosition = '';
            this.pendingAdminRole = false;
            this.showAdminConfirmModal = false;
        },
        cancelAdminRole() {
            this.showAdminConfirmModal = false;
            this.pendingAdminRole = false;
        },
        canSubmit() {
            if (!(this.role === 'official' || this.role === 'admin')) {
                return false;
            }

            if (this.role === 'official' && !this.officialPosition) {
                return false;
            }

            if (this.role === 'admin' && this.adminRoleLimitReached) {
                return false;
            }

            if (this.residentLookupValidating || this.residentPasswordValidating) {
                return false;
            }

                 return this.fullnameInput &&
                     !!this.residentId &&
                     this.password &&
                     this.confirmPassword &&
                     this.password === this.confirmPassword &&
                     this.requirements.minLength && 
                     this.requirements.hasUppercase && 
                     this.requirements.hasLowercase && 
                     this.requirements.hasNumber && 
                     this.requirements.hasSpecial &&
                     this.residentFound && !this.residentPasswordConflict;
        },
        formCompletionPercent() {
            const checks = [
                this.residentFound,
                this.requirements.minLength,
                this.requirements.hasUppercase,
                this.requirements.hasLowercase,
                this.requirements.hasNumber,
                this.requirements.hasSpecial
            ];

            const completed = checks.filter(Boolean).length;
            return Math.round((completed / checks.length) * 100);
        },
        handleFormSubmit(event) {
            if (this.formSubmitting) {
                event.preventDefault();
                return;
            }

            if (!this.canSubmit()) {
                event.preventDefault();
                return;
            }

            this.formSubmitting = true;
        }
    }" x-init="init()">
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
                            <a href="user-management.php" class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                <i class="fas fa-chevron-left text-sm"></i>
                            </a>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Create New User</h1>
                        </div>
                        
                        <?php
include '../partials/user-dropdown.php'; ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-[#F8FAFC] p-4 sm:p-6 lg:p-12">
                <div class="max-w-3xl mx-auto">
                    <!-- Session Timeout Warning -->
                    <div x-show="sessionWarningActive" x-cloak class="mb-6 bg-amber-50 border-l-4 border-amber-500 text-amber-900 p-4 rounded-r-xl shadow-sm pulse-warning">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-clock text-amber-600 text-lg"></i>
                            <div class="flex-1">
                                <p class="font-bold text-sm">Session Activity Detected</p>
                                <p class="text-xs mt-1">Your session will expire due to inactivity. Consider saving your work or taking a break.</p>
                            </div>
                            <a href="../index.php" class="whitespace-nowrap text-xs font-bold text-amber-600 hover:text-amber-800 underline">Refresh Session</a>
                        </div>
                    </div>

                    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-r-xl shadow-sm" role="status">
                        <div class="flex items-center">
                            <i class="fas fa-user-shield mr-3"></i>
                            <p class="font-semibold text-sm">Admin slots used: <?php
echo $admin_count; ?> / <?php
echo $admin_selection_limit; ?></p>
                        </div>
                    </div>

                    <?php
if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 p-5 mb-8 rounded-r-2xl shadow-sm animate-pulse">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-3"></i>
                                <p class="font-semibold text-sm"><?php
echo htmlspecialchars($_SESSION['error_message']); ?></p>
                            </div>
                        </div>
                    <?php
unset($_SESSION['error_message']); endif; ?>

                    <div class="bg-white rounded-[2rem] shadow-xl shadow-indigo-100/30 border border-slate-200 overflow-hidden transform transition hover:shadow-2xl">
                        <div class="px-8 pt-10 pb-6 border-b border-slate-50 bg-gradient-to-br from-white to-slate-50/50">
                            <div class="h-14 w-14 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-indigo-600/30 mb-6 drop-shadow-md">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h2 class="text-2xl font-extrabold text-slate-900">Registration Form</h2>
                            <p class="text-sm font-medium text-slate-500 mt-1">Enroll new members to the system</p>
                            
                            <!-- Form Progress Indicator -->
                            <div class="mt-6 pt-6 border-t border-slate-200">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-600">Form Completion</p>
                                    <span class="text-xs font-black text-indigo-600" x-text="`${formCompletionPercent()}%`"></span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-full rounded-full transition-all duration-300" :style="`width: ${formCompletionPercent()}%`"></div>
                                </div>
                            </div>
                        </div>
                        
                        <form action="../partials/add-user-handler.php" method="POST" @submit="handleFormSubmit($event)" class="p-8 space-y-8">
                            <?php
echo csrf_field(); ?>
                            <input type="hidden" name="resident_id" :value="residentId || ''">
                            
                            <!-- Basic Info Section -->
                            <div class="space-y-6">
                                  <div>
                                      <label class="block text-xs font-semibold text-slate-600 mb-2 ml-1">Full legal name</label>
                                     <input id="fullname" x-model="fullnameInput" @input="handleFullnameInput()" @blur="checkResident()" type="text" name="fullname" required 
                                         value="<?php
        echo $old_fullname; ?>"
                                           class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" 
                                           placeholder="e.g. John Doe">

                                     <div class="mt-2">
                                        <p x-show="residentLookupValidating" class="text-xs text-slate-500"><i class="fas fa-spinner fa-pulse mr-1"></i> Verifying resident...</p>
                                        <p x-show="residentFound" class="text-xs text-emerald-600"><i class="fas fa-check-circle mr-1"></i> Resident record found (<span x-text="residentEmail"></span>)</p>
                                        <p x-show="!residentFound && residentError" class="text-xs text-rose-600"><i class="fas fa-exclamation-circle mr-1"></i> <span x-text="residentError"></span></p>
                                     </div>
                                  </div>
                                
                                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-600 mb-2">Account Username</p>
                                    <p class="text-sm text-slate-700 leading-relaxed">
                                        The resident's registered email is used at sign-in to discover this tied privileged account.
                                    </p>
                                </div>
                                
                                <div class="relative">
                                     <label class="block text-xs font-semibold text-slate-600 mb-2 ml-1">Secure password</label>
                                    <input type="password" name="password" required x-model="password" @input="validatePassword($event.target.value)"
                                           class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm" 
                                           placeholder="Min. 8 characters">

                                    <div class="mt-3">
                                        <label class="block text-xs font-semibold text-slate-600 mb-2 ml-1">Confirm New Password</label>
                                        <input type="password" name="confirm_password" required x-model="confirmPassword"
                                               class="form-input w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition shadow-sm"
                                               placeholder="Re-enter secure password">
                                        <p x-show="password && confirmPassword && password !== confirmPassword" x-cloak class="text-xs text-rose-600 mt-2">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Passwords do not match
                                        </p>
                                        <p x-show="password && confirmPassword && password === confirmPassword" x-cloak class="text-xs text-emerald-600 mt-2">
                                            <i class="fas fa-check-circle mr-1"></i> Passwords match
                                        </p>
                                    </div>
                                    
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

                                    <div class="mt-2">
                                        <p x-show="residentPasswordValidating" class="text-xs text-slate-500"><i class="fas fa-spinner fa-pulse mr-1"></i> Checking password against resident account...</p>
                                        <p x-show="residentPasswordConflict" class="text-xs text-rose-600"><i class="fas fa-ban mr-1"></i> Password used is unavailable</p>
                                    </div>

                                    <!-- Password Requirements Checklist -->
                                    <div class="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
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
                            </div>
                            
                            <div class="h-px bg-slate-100"></div>
                            
                            <!-- Access Section -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-2 ml-1">System role</label>
                                    <div class="flex gap-3">
                                        <label class="flex-1 cursor-pointer">
                                            <input type="radio" name="role" value="official" @change="handleRoleChange('official')" class="sr-only peer">
                                            <div class="p-4 border-2 border-slate-100 rounded-2xl text-center transition hover:bg-emerald-50 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 group">
                                                <i class="fas fa-award text-slate-400 group-hover:text-emerald-600 mb-2"></i>
                                                <div class="text-xs font-bold text-slate-900">Official</div>
                                            </div>
                                        </label>
                                        <label class="flex-1" :class="adminRoleLimitReached ? 'cursor-not-allowed' : 'cursor-pointer'">
                                            <input type="radio" name="role" value="admin" @change="handleRoleChange('admin')" :disabled="adminRoleLimitReached" class="sr-only peer">
                                            <div class="p-4 border-2 border-slate-100 rounded-2xl text-center transition group" :class="adminRoleLimitReached ? 'opacity-50 bg-slate-100 border-slate-200' : 'hover:bg-rose-50 peer-checked:border-rose-600 peer-checked:bg-rose-50'">
                                                <i class="fas fa-shield-alt text-slate-400 group-hover:text-rose-600 mb-2"></i>
                                                <div class="text-xs font-bold text-slate-900">Admin</div>
                                            </div>
                                        </label>
                                    </div>
                                    <p x-show="adminRoleLimitReached" x-cloak class="text-xs font-semibold text-rose-600 mt-2 ml-1">
                                        Admin role is disabled because the maximum of <?php echo $admin_selection_limit; ?> admin accounts has been reached.
                                    </p>
                                </div>
                                
                                <div x-show="role === 'official' && !pendingAdminRole" x-cloak x-transition class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-2 ml-1">Official position</label>
                                    <select name="official_position" x-model="officialPosition" :required="role === 'official'" :disabled="role !== 'official'"
                                            class="form-input w-full bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 text-sm font-bold text-amber-900 focus:ring-2 focus:ring-amber-500 transition shadow-sm appearance-none">
                                        <option value="">Select official position...</option>
                                        <option value="barangay-officials" <?php
echo in_array($old_official_position, ['barangay-officials', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer', 'official'], true) ? 'selected' : ''; ?>>Barangay Officials</option>
                                        <option value="barangay-kagawad" <?php
echo in_array($old_official_position, ['barangay-kagawad', 'kagawad'], true) ? 'selected' : ''; ?>>Barangay Kagawad</option>
                                        <option value="barangay-tanod" <?php
echo $old_official_position === 'barangay-tanod' ? 'selected' : ''; ?>>Barangay Tanod</option>
                                    </select>
                                </div>

                                <div x-show="role === 'admin'" x-cloak x-transition class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-2 ml-1">Confirm with your admin password</label>
                                    <input type="password" name="admin_confirmation_password" :required="role === 'admin'" autocomplete="current-password"
                                           class="form-input w-full bg-rose-50 border border-rose-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-2 focus:ring-rose-500 transition shadow-sm"
                                           placeholder="Enter your current admin password">
                                    <p class="text-xs font-semibold text-rose-600 mt-2 ml-1">
                                        Privileged action: creating an admin account requires re-authentication.
                                    </p>
                                </div>
                                
                                <!-- Form Safety Tips -->
                                <div class="md:col-span-2 mt-4 p-4 bg-indigo-50 rounded-xl border border-indigo-200">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-900 mb-2">
                                        <i class="fas fa-lightbulb mr-1"></i> Pro Tips
                                    </p>
                                    <ul class="space-y-1 text-xs text-indigo-800">
                                        <li><i class="fas fa-shield-alt mr-1"></i> Verify the resident profile has the correct email before enrollment</li>
                                        <li><i class="fas fa-key mr-1"></i> Share the created credentials securely and ask the user to change password after first sign-in</li>
                                        <li><i class="fas fa-user-shield mr-1"></i> Admin accounts have unrestricted access - assign only to trusted personnel</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="pt-6 flex gap-4">
                                <a href="user-management.php" class="flex-1 px-8 py-5 rounded-2xl text-xs font-black uppercase text-center text-slate-500 bg-slate-100 hover:bg-slate-200 transition">Cancel</a>
                                <button type="submit" 
                                        :disabled="!canSubmit() || formSubmitting"
                                        :class="!canSubmit() || formSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                        class="flex-[2] px-8 py-5 rounded-2xl text-xs font-black uppercase text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-600/30 transition transform active:scale-95">
                                    <span x-show="!formSubmitting">Complete Enrollment</span>
                                    <span x-show="formSubmitting"><i class="fas fa-spinner fa-spin mr-2"></i>Processing...</span>
                                </button>
                            </div>

                            <!-- Form Validation Summary -->
                            <div x-show="!canSubmit() && (password || fullnameInput)" x-cloak class="mt-4 p-4 bg-rose-50 rounded-xl border border-rose-200">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-rose-700 mb-2">
                                    <i class="fas fa-circle-exclamation mr-1"></i> Complete the following before submitting:
                                </p>
                                <ul class="space-y-1">
                                    <li x-show="!residentFound && fullnameInput" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Full legal name must match a resident record
                                    </li>
                                    <li x-show="residentPasswordConflict" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Password used is unavailable
                                    </li>
                                    <li x-show="password && !confirmPassword" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Confirm new password is required
                                    </li>
                                    <li x-show="password && confirmPassword && password !== confirmPassword" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Passwords must match
                                    </li>
                                    <li x-show="!requirements.minLength" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Password must be 8+ characters
                                    </li>
                                    <li x-show="!requirements.hasUppercase" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Add uppercase letter
                                    </li>
                                    <li x-show="!requirements.hasLowercase" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Add lowercase letter
                                    </li>
                                    <li x-show="!requirements.hasNumber" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Add a number
                                    </li>
                                    <li x-show="!requirements.hasSpecial" class="text-xs text-rose-600">
                                        <i class="fas fa-times mr-1"></i> Add special character (!@#$%^&*)
                                    </li>
                                </ul>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Admin Privilege Confirmation Modal -->
    <div x-show="showAdminConfirmModal" x-cloak @click.self="cancelAdminRole()" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 modal-backdrop p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden" @click.stop>
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-rose-50 to-rose-100 px-8 py-6 border-b-2 border-rose-200">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-rose-600 flex items-center justify-center text-white">
                        <i class="fas fa-exclamation-circle text-sm"></i>
                    </div>
                    <h3 class="text-lg font-bold text-rose-900">Create Privileged Account</h3>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="px-8 py-6 space-y-4">
                <p class="text-sm text-slate-700 leading-relaxed">
                    You are about to create an <strong class="font-bold text-rose-600">ADMIN account</strong>. This user will have <strong>full system access</strong> and can manage all aspects of CommunaLink.
                </p>
                
                <div class="bg-rose-50 border-l-4 border-rose-600 p-4 rounded">
                    <p class="text-xs text-rose-900 font-semibold">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Ensure this is intentional and the person is trusted.
                    </p>
                </div>

                <p class="text-xs text-slate-600">
                    <strong>Admin Slots:</strong> <span class="text-rose-600 font-bold"><?php echo $admin_count; ?>/<?php echo $admin_selection_limit; ?></span> used
                </p>
            </div>

            <!-- Modal Footer -->
            <div class="bg-slate-50 px-8 py-4 flex gap-3">
                <button @click="cancelAdminRole()" type="button" class="flex-1 px-4 py-3 rounded-xl text-xs font-bold uppercase text-slate-700 bg-slate-200 hover:bg-slate-300 transition">
                    Cancel
                </button>
                <button @click="confirmAdminRole()" type="button" class="flex-1 px-4 py-3 rounded-xl text-xs font-bold uppercase text-white bg-rose-600 hover:bg-rose-700 shadow-lg shadow-rose-600/30 transition">
                    Confirm Admin
                </button>
            </div>
        </div>
    </div>
</body>
</html>


