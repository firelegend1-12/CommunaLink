<?php
require_once '../config/database.php';
$page_title = "My Account";
require_once 'partials/header.php';
$security_csrf_token = csrf_token();

// user_id is from header session
$user_id = $_SESSION['user_id'];

// Fetch user and resident data
try {
    $stmt = $pdo->prepare("SELECT u.email, r.* FROM users u JOIN residents r ON u.id = r.user_id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resident) {
        // Handle case where resident data might not exist
        $resident = ['email' => $_SESSION['email'] ?? ''];
    }
} catch (PDOException $e) {
    // For debugging: error_log($e->getMessage());
    $resident = ['email' => $_SESSION['email'] ?? '']; // Default empty data on error
}
?>

<style>
    .account-container {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 32px;
    }
    
    .profile-sidebar {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        padding: 30px;
        text-align: center;
        height: fit-content;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .profile-sidebar:hover {
        transform: translateY(-5px);.form-group input, .form-group select, .form-group textarea
        box-shadow: 0 8px 16px rgba(0,0,0,0.12);
    }
    
    .profile-picture-container {
        position: relative;
        width: 160px;
        height: 160px;
        margin: 0 auto 24px;
    }
    
    .profile-picture {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid #5c67e2;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(92, 103, 226, 0.3);
    }
    
    .profile-picture-container .upload-overlay {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background-color: #4a54b5;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 3px solid white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        transition: all 0.2s ease;
    }
    
    .profile-picture-container .upload-overlay:hover {
        background-color: #3a43a0;
        transform: scale(1.1);
    }
    
    #profile_image_input {
        display: none;
    }

    .upload-status {
        margin-top: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #4a54b5;
        display: none;
    }

    .upload-status.visible {
        display: block;
    }
    
    .form-container {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        padding: 0;
        overflow: hidden;
    }
    
    .form-header {
        padding: 20px 30px;
        border-bottom: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }
    
    .form-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: #111827;
    }
    
    .form-content {
        padding: 30px;
    }
    
    .form-section {
        margin-bottom: 32px;
    }
    
    .form-section:last-child {
        margin-bottom: 0;
    }
    
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
        position: relative;
    }
    
    .form-section-title::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -2px;
        width: 60px;
        height: 2px;
        background-color: #5c67e2;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
    
    .form-grid-2-col {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        font-size: 0.9rem;
        color: #4b5563;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.2s;
        box-sizing: border-box;
    }
    
    .form-group input:hover,
    .form-group select:hover,
    .form-group textarea:hover {
        border-color: #a5b4fc;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #5c67e2;
        box-shadow: 0 0 0 4px rgba(92, 103, 226, 0.15);
    }
    
    .form-group input[readonly] {
        background-color: #f3f4f6;
        cursor: not-allowed;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-actions {
        text-align: right;
        padding: 20px 30px;
        border-top: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }
    
    .submit-btn {
        background-color: #5c67e2;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(92, 103, 226, 0.25);
    }
    
    .submit-btn:hover {
        background-color: #4a54b5;
        transform: translateY(-2px);
        box-shadow: 0 6px 10px rgba(92, 103, 226, 0.3);
    }
    
    .submit-btn:active {
        transform: translateY(0);
    }

    .password-requirements {
        margin-top: 16px;
        padding: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .password-requirements-title {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 10px;
    }

    .requirement-check {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.82rem;
        font-weight: 500;
        margin-bottom: 6px;
    }

    .requirement-check:last-child {
        margin-bottom: 0;
    }

    .requirement-check i {
        font-size: 0.75rem;
    }

    .requirement-met {
        color: #16a34a;
    }

    .requirement-unmet {
        color: #64748b;
    }

    .redirect-spinner {
        border: 4px solid rgba(0,0,0,0.1);
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border-left-color: #3b82f6;
        animation: redirect-spin 1s linear infinite;
    }

    .redirect-loading-dots span {
        display: inline-block;
        animation: redirect-bounce 1.2s infinite ease-in-out;
    }

    .redirect-loading-dots span:nth-child(2) {
        animation-delay: 0.15s;
    }

    .redirect-loading-dots span:nth-child(3) {
        animation-delay: 0.3s;
    }

    @keyframes redirect-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
        40% { transform: translateY(-4px); opacity: 1; }
    }

    @keyframes redirect-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @media (max-width: 992px) {
        .account-container {
            grid-template-columns: 1fr;
            gap: 24px;
        }
        
        .profile-sidebar {
            max-width: 400px;
            margin: 0 auto;
        }
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 576px) {
        .form-grid, .form-grid-2-col {
            grid-template-columns: 1fr;
        }
        
        .form-content {
            padding: 20px;
        }
    }
</style>

<div class="account-container">
    <aside class="profile-sidebar">
        <form id="profile-pic-form" action="partials/account-handler.php" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="profile-picture-container">
                <?php
                    $defaultAvatar = resident_default_profile_avatar_data_uri();
                    $profileImageUrl = !empty($resident['profile_image_path']) ? resident_profile_image_url((string)$resident['profile_image_path']) : '';
                    $profileSrc = $profileImageUrl !== '' ? htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') : $defaultAvatar;
                ?>
                <img src="<?= $profileSrc ?>" alt="Profile Picture" class="profile-picture" id="profile-pic-preview">
                <label for="profile_image_input" class="upload-overlay">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" name="profile_image" id="profile_image_input" accept="image/*">
            </div>
            <h2 class="text-xl font-bold mb-2"><?= htmlspecialchars($resident['first_name'] ?? '') . ' ' . htmlspecialchars($resident['last_name'] ?? '') ?></h2>
            <p class="text-gray-500 mb-2"><?= htmlspecialchars($resident['email'] ?? '') ?></p>
            <p id="upload-status" class="upload-status" aria-live="polite"></p>
            <input type="hidden" name="update_profile_pic" value="1">
        </form>
    </aside>

    <div class="form-container">
        <div class="form-header">
            <h2>Personal Information</h2>
        </div>
        
        <form action="partials/account-handler.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-content">
                <div class="form-section">
                    <div class="form-section-title">Basic Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($resident['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_initial">Middle Initial</label>
                            <input type="text" id="middle_initial" name="middle_initial" value="<?= htmlspecialchars($resident['middle_initial'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($resident['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($resident['date_of_birth'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="age">Age</label>
                            <input type="number" id="age" name="age" value="<?= htmlspecialchars($resident['age'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?= ($resident['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($resident['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($resident['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="place_of_birth">Place of Birth</label>
                            <input type="text" id="place_of_birth" name="place_of_birth" value="<?= htmlspecialchars($resident['place_of_birth'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="civil_status">Civil Status</label>
                            <select id="civil_status" name="civil_status" required>
                                <option value="">Select Status</option>
                                <option value="Single" <?= ($resident['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= ($resident['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                                <option value="Widowed" <?= ($resident['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                <option value="Separated" <?= ($resident['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="religion">Religion</label>
                            <input type="text" id="religion" name="religion" value="<?= htmlspecialchars($resident['religion'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-grid-2-col" style="margin-top:24px;">
                        <div class="form-group">
                            <label for="citizenship">Citizenship</label>
                            <input type="text" id="citizenship" name="citizenship" value="<?= htmlspecialchars($resident['citizenship'] ?? 'Filipino') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="voter_status">Voter Status</label>
                            <select id="voter_status" name="voter_status" required>
                                <option value="Yes" <?= ($resident['voter_status'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= ($resident['voter_status'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Contact & Address</div>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label>Email Address</label>
                            <div id="email-display-row" style="display:flex;align-items:center;gap:8px;">
                                <input type="email" id="email-display" value="<?= htmlspecialchars($resident['email'] ?? '') ?>" readonly style="flex:1;background:#f3f4f6;cursor:default;">
                                <button type="button" id="btn-change-email" style="white-space:nowrap;padding:10px 14px;background:#5c67e2;color:#fff;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;"><i class="fas fa-pen" style="margin-right:4px;"></i>Change</button>
                            </div>
                            <input type="hidden" id="original_email" value="<?= htmlspecialchars($resident['email'] ?? '') ?>">
                            <div id="email-change-step1" style="display:none;margin-top:10px;">
                                <label style="font-size:0.8rem;color:#64748b;margin-bottom:4px;display:block;">New Email Address</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="email" id="new_email" placeholder="Enter new email" style="flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:0.95rem;box-sizing:border-box;">
                                    <button type="button" id="btn-send-email-otp" style="white-space:nowrap;padding:10px 14px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Send Code</button>
                                </div>
                                <div id="email-send-info" style="display:none;margin-top:6px;font-size:0.8rem;color:#16a34a;"></div>
                                <div id="email-send-error" style="display:none;margin-top:6px;font-size:0.8rem;color:#dc2626;"></div>
                            </div>
                            <div id="email-change-step2" style="display:none;margin-top:10px;">
                                <label style="font-size:0.8rem;color:#64748b;margin-bottom:4px;display:block;">Verification Code <span style="color:#4f46e5;">(check your new email inbox)</span></label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" id="email-otp-input" maxlength="6" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" style="flex:1;padding:10px 14px;border:2px solid #c7d2fe;border-radius:8px;font-size:1rem;letter-spacing:4px;text-align:center;font-weight:700;box-sizing:border-box;">
                                    <button type="button" id="btn-confirm-email-change" style="white-space:nowrap;padding:10px 14px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;">Confirm</button>
                                </div>
                                <div id="email-otp-error" style="display:none;margin-top:6px;font-size:0.8rem;color:#dc2626;"></div>
                                <button type="button" id="btn-cancel-email-change" style="background:none;border:none;color:#94a3b8;font-size:0.8rem;cursor:pointer;margin-top:6px;padding:0;">Cancel</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contact_no">Contact Number</label>
                            <input type="tel" id="contact_no" name="contact_no" value="<?= htmlspecialchars($resident['contact_no'] ?? '') ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="address">Full Address</label>
                            <textarea id="address" name="address" required><?= htmlspecialchars($resident['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Change Password</div>
                    <p style="font-size:0.85rem;color:#64748b;margin-bottom:16px;">Fill in to change your password. Email verification will be required.</p>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
                            <div id="pw-match-status" style="display:none;margin-top:6px;font-size:0.85rem;align-items:center;gap:6px;">
                                <i id="pw-match-icon" class="fas fa-circle" aria-hidden="true"></i>
                                <span id="pw-match-text"></span>
                            </div>
                        </div>
                        <div class="form-group full-width" id="current-password-row" style="display:none;">
                            <label for="current_password">Current Password <span style="color:#94a3b8;font-weight:400;font-size:0.8rem;">(required to confirm it's you)</span></label>
                            <input type="password" id="current_password" placeholder="Enter your current password" autocomplete="current-password">
                        </div>
                    </div>
                    <div style="margin-top:14px;">
                        <button type="button" id="btn-change-password" style="padding:11px 24px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:8px;font-size:0.9rem;font-weight:600;cursor:pointer;"><i class="fas fa-lock" style="margin-right:8px;"></i>Change Password</button>
                        <div id="pw-change-status" style="display:none;margin-top:8px;font-size:0.85rem;"></div>
                    </div>
                    <div class="password-requirements" id="password-requirements">
                        <p class="password-requirements-title">Password Requirements</p>
                        <div class="requirements-list">
                            <div class="requirement-check requirement-unmet" data-rule="minLength">
                                <i class="fas fa-circle" aria-hidden="true"></i>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="requirement-check requirement-unmet" data-rule="hasUppercase">
                                <i class="fas fa-circle" aria-hidden="true"></i>
                                <span>Uppercase letter (A-Z)</span>
                            </div>
                            <div class="requirement-check requirement-unmet" data-rule="hasLowercase">
                                <i class="fas fa-circle" aria-hidden="true"></i>
                                <span>Lowercase letter (a-z)</span>
                            </div>
                            <div class="requirement-check requirement-unmet" data-rule="hasNumber">
                                <i class="fas fa-circle" aria-hidden="true"></i>
                                <span>Number (0-9)</span>
                            </div>
                            <div class="requirement-check requirement-unmet" data-rule="hasSpecial">
                                <i class="fas fa-circle" aria-hidden="true"></i>
                                <span>Special character (!@#$%^&*)</span>
                            </div>
                            <!-- moved 'Passwords match' indicator under Confirm New Password field -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="update_details" class="submit-btn">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- OTP Verification Modal -->
<div id="otp-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; display:none; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; box-shadow:0 25px 50px rgba(0,0,0,0.15); max-width:420px; width:90%; padding:32px; text-align:center; position:relative;">
        <button id="otp-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
        <div style="margin-bottom:20px;">
            <div style="width:56px;height:56px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                <i class="fas fa-shield-alt" style="color:#fff;font-size:24px;"></i>
            </div>
            <h3 style="margin:0 0 8px;font-size:1.3rem;font-weight:700;color:#1e293b;">Email Verification</h3>
            <p id="otp-subtitle" style="margin:0;font-size:0.9rem;color:#64748b;">A verification code has been sent to your email.</p>
        </div>
        <div id="otp-error" style="display:none;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:16px;"></div>
        <div style="display:flex;gap:8px;justify-content:center;margin-bottom:20px;">
            <input type="text" id="otp-input" maxlength="6" placeholder="000000" style="width:180px;text-align:center;font-size:1.8rem;font-weight:700;letter-spacing:8px;padding:12px;border:2px solid #e2e8f0;border-radius:10px;outline:none;transition:border-color 0.2s;" inputmode="numeric" autocomplete="one-time-code">
        </div>
        <button id="otp-submit" style="width:100%;padding:12px 24px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;transition:opacity 0.2s;">
            Verify & Apply
        </button>
        <p style="margin-top:12px;font-size:0.8rem;color:#94a3b8;">Code expires in 10 minutes.</p>
    </div>
</div>

<div id="login-redirect-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.62); z-index:10000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div style="background:#fff; border-radius:16px; box-shadow:0 25px 50px rgba(0,0,0,0.15); max-width:420px; width:90%; padding:32px; text-align:center; position:relative;">
        <div style="margin-bottom:20px; display:flex; justify-content:center;">
            <div class="redirect-spinner"></div>
        </div>
        <h3 style="margin:0 0 8px;font-size:1.3rem;font-weight:700;color:#1e293b;">Redirecting</h3>
        <p id="login-redirect-message" style="margin:0;font-size:0.9rem;color:#64748b;">Please wait while we take you back to the login screen.</p>
        <p class="redirect-loading-dots" aria-hidden="true" style="margin-top:12px;font-size:0.8rem;color:#94a3b8;"><span>.</span><span>.</span><span>.</span></p>
    </div>
</div>

<script>
const SECURITY_CSRF_TOKEN = <?php echo json_encode($security_csrf_token); ?>;
const LOGIN_REDIRECT_URL = <?php echo json_encode(app_url('/index.php')); ?>;

document.addEventListener('DOMContentLoaded', function () {
    // --- Profile picture logic ---
    const profileImageInput = document.getElementById('profile_image_input');
    const profilePicForm = document.getElementById('profile-pic-form');
    const profilePicPreview = document.getElementById('profile-pic-preview');
    const uploadStatus = document.getElementById('upload-status');

    const urlParams = new URLSearchParams(window.location.search);
    const picUpdated = urlParams.get('success') === 'pic_updated';
    const picError = urlParams.get('error');
    const loginRedirectModal = document.getElementById('login-redirect-modal');
    const loginRedirectMessage = document.getElementById('login-redirect-message');

    function showLoginRedirect(message, targetUrl) {
        if (loginRedirectMessage) {
            loginRedirectMessage.textContent = message || 'Please wait while we take you back to the login screen.';
        }
        if (loginRedirectModal) {
            loginRedirectModal.style.display = 'flex';
        }

        window.setTimeout(() => {
            window.location.href = targetUrl || LOGIN_REDIRECT_URL;
        }, 1800);
    }

    if (picUpdated) {
        uploadStatus.textContent = 'Profile photo updated successfully.';
        uploadStatus.classList.add('visible');
        uploadStatus.style.color = '#15803d';
    } else if (picError) {
        const errorMap = {
            upload_failed: 'Upload failed. Please try again.',
            invalid_file: 'Invalid file. Use JPG, PNG, or GIF under 5MB.',
            no_file: 'Please choose an image file before uploading.',
            file_too_large: 'Image is too large. Please use a file under 5MB.'
        };
        uploadStatus.textContent = errorMap[picError] || 'Unable to update profile photo.';
        uploadStatus.classList.add('visible');
        uploadStatus.style.color = '#b91c1c';
    }

    profileImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) { profilePicPreview.src = e.target.result; }
            reader.readAsDataURL(this.files[0]);
            uploadStatus.textContent = 'Uploading profile photo...';
            uploadStatus.classList.add('visible');
            uploadStatus.style.color = '#4a54b5';
            setTimeout(() => { profilePicForm.submit(); }, 300);
        }
    });

    // --- Age auto-calculation ---
    const birthdateInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');
    function calculateAge() {
        if(birthdateInput.value) {
            const birthDate = new Date(birthdateInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
            ageInput.value = age >= 0 ? age : '';
        } else { ageInput.value = ''; }
    }
    birthdateInput.addEventListener('change', calculateAge);
    calculateAge();

    // === EMAIL CHANGE INLINE FLOW ===
    const btnChangeEmail = document.getElementById('btn-change-email');
    const emailStep1 = document.getElementById('email-change-step1');
    const emailStep2 = document.getElementById('email-change-step2');
    const btnSendEmailOtp = document.getElementById('btn-send-email-otp');
    const btnConfirmEmailChange = document.getElementById('btn-confirm-email-change');
    const btnCancelEmailChange = document.getElementById('btn-cancel-email-change');
    const newEmailInput = document.getElementById('new_email');
    const emailOtpInput = document.getElementById('email-otp-input');
    const emailSendInfo = document.getElementById('email-send-info');
    const emailSendError = document.getElementById('email-send-error');
    const emailOtpError = document.getElementById('email-otp-error');

    if (btnChangeEmail) {
        btnChangeEmail.addEventListener('click', function() {
            emailStep1.style.display = 'block';
            btnChangeEmail.style.display = 'none';
            setTimeout(() => newEmailInput && newEmailInput.focus(), 100);
        });
    }

    if (btnCancelEmailChange) {
        btnCancelEmailChange.addEventListener('click', function() {
            emailStep1.style.display = 'none';
            emailStep2.style.display = 'none';
            if (btnChangeEmail) btnChangeEmail.style.display = '';
            if (newEmailInput) newEmailInput.value = '';
            if (emailOtpInput) emailOtpInput.value = '';
            emailSendInfo.style.display = 'none';
            emailSendError.style.display = 'none';
            emailOtpError.style.display = 'none';
        });
    }

    if (btnSendEmailOtp) {
        btnSendEmailOtp.addEventListener('click', async function() {
            const newEmail = newEmailInput ? newEmailInput.value.trim() : '';
            emailSendError.style.display = 'none';
            if (!newEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
                emailSendError.textContent = 'Please enter a valid email address.';
                emailSendError.style.display = 'block';
                return;
            }
            btnSendEmailOtp.disabled = true;
            btnSendEmailOtp.textContent = 'Sending...';

            const body = new URLSearchParams();
            body.append('action', 'send_otp');
            body.append('type', 'email');
            body.append('new_email', newEmail);
            body.append('csrf_token', SECURITY_CSRF_TOKEN);

            try {
                const res = await fetch('partials/account-security-handler.php', { method: 'POST', body });
                const data = await res.json();
                if (data.success) {
                    emailSendInfo.textContent = data.message;
                    emailSendInfo.style.display = 'block';
                    emailStep2.style.display = 'block';
                    setTimeout(() => emailOtpInput && emailOtpInput.focus(), 100);
                } else {
                    emailSendError.textContent = data.message || 'Failed to send code.';
                    emailSendError.style.display = 'block';
                }
            } catch (e) {
                emailSendError.textContent = 'Network error. Please try again.';
                emailSendError.style.display = 'block';
            }
            btnSendEmailOtp.disabled = false;
            btnSendEmailOtp.textContent = 'Send Code';
        });
    }

    if (btnConfirmEmailChange) {
        btnConfirmEmailChange.addEventListener('click', async function() {
            const code = emailOtpInput ? emailOtpInput.value.trim() : '';
            emailOtpError.style.display = 'none';
            if (code.length !== 6 || !/^\d{6}$/.test(code)) {
                emailOtpError.textContent = 'Enter the 6-digit code from your email.';
                emailOtpError.style.display = 'block';
                return;
            }
            btnConfirmEmailChange.disabled = true;
            btnConfirmEmailChange.textContent = 'Verifying...';

            const body = new URLSearchParams();
            body.append('action', 'verify_change_email');
            body.append('otp_code', code);
            body.append('csrf_token', SECURITY_CSRF_TOKEN);

            try {
                const res = await fetch('partials/account-security-handler.php', { method: 'POST', body });
                const data = await res.json();
                if (data.success) {
                    emailSendInfo.textContent = '✅ ' + data.message;
                    emailSendInfo.style.display = 'block';
                    btnConfirmEmailChange.textContent = 'Redirecting...';
                    showLoginRedirect(data.message || 'Email updated successfully. Redirecting to login...', data.redirect);
                } else {
                    emailOtpError.textContent = data.message || 'Verification failed.';
                    emailOtpError.style.display = 'block';
                    btnConfirmEmailChange.disabled = false;
                    btnConfirmEmailChange.textContent = 'Confirm';
                }
            } catch (e) {
                emailOtpError.textContent = 'Network error. Please try again.';
                emailOtpError.style.display = 'block';
                btnConfirmEmailChange.disabled = false;
                btnConfirmEmailChange.textContent = 'Confirm';
            }
        });
    }

    // === PASSWORD CHANGE FLOW ===
    const newPwInput = document.getElementById('new_password');
    const confirmPwInput = document.getElementById('confirm_password');
    const currentPwRow = document.getElementById('current-password-row');
    const currentPwInput = document.getElementById('current_password');
    const btnChangePassword = document.getElementById('btn-change-password');
    const pwChangeStatus = document.getElementById('pw-change-status');

    const requirementNodes = document.querySelectorAll('.requirement-check[data-rule]');
    const requirementMap = {};
    requirementNodes.forEach((node) => {
        const rule = node.getAttribute('data-rule');
        if (rule) {
            requirementMap[rule] = node;
        }
    });
    const pwMatchEl = document.getElementById('pw-match-status');
    const pwMatchIcon = document.getElementById('pw-match-icon');
    const pwMatchText = document.getElementById('pw-match-text');

    function getPasswordRules(passwordValue, confirmValue) {
        const pw = passwordValue || '';
        const confirmPw = confirmValue || '';
        return {
            minLength: pw.length >= 8,
            hasUppercase: /[A-Z]/.test(pw),
            hasLowercase: /[a-z]/.test(pw),
            hasNumber: /[0-9]/.test(pw),
            hasSpecial: /[^A-Za-z0-9]/.test(pw),
            matches: pw.length > 0 && pw === confirmPw
        };
    }

    function updateRequirementState(rule, met) {
        const node = requirementMap[rule];
        if (!node) return;
        node.classList.toggle('requirement-met', !!met);
        node.classList.toggle('requirement-unmet', !met);
        const icon = node.querySelector('i');
        if (icon) {
            icon.className = met ? 'fas fa-check-circle' : 'fas fa-circle';
        }
    }

    function updateRequirementUI() {
        const pw = newPwInput ? newPwInput.value.trim() : '';
        const confirmPw = confirmPwInput ? confirmPwInput.value.trim() : '';
        const rules = getPasswordRules(pw, confirmPw);
        Object.keys(rules).forEach((rule) => updateRequirementState(rule, rules[rule]));

        // Update dedicated password-match element (moved out of checklist)
        if (pwMatchEl) {
            if (rules.matches) {
                pwMatchEl.style.display = 'inline-flex';
                pwMatchEl.style.color = '#16a34a';
                if (pwMatchIcon) pwMatchIcon.className = 'fas fa-check-circle';
                if (pwMatchText) pwMatchText.textContent = 'Passwords match';
            } else if (pw.length > 0 || confirmPw.length > 0) {
                pwMatchEl.style.display = 'inline-flex';
                pwMatchEl.style.color = '#dc2626';
                if (pwMatchIcon) pwMatchIcon.className = 'fas fa-exclamation-circle';
                if (pwMatchText) pwMatchText.textContent = 'Passwords do not match';
            } else {
                pwMatchEl.style.display = 'none';
                if (pwMatchIcon) pwMatchIcon.className = 'fas fa-circle';
                if (pwMatchText) pwMatchText.textContent = '';
            }
        }

        return rules;
    }

    function handlePasswordInput() {
        const nv = newPwInput ? newPwInput.value.trim() : '';
        const cv = confirmPwInput ? confirmPwInput.value.trim() : '';
        updateRequirementUI();
        if (currentPwRow) currentPwRow.style.display = (nv.length >= 8 && cv.length >= 1) ? 'block' : 'none';
    }
    if (newPwInput) newPwInput.addEventListener('input', handlePasswordInput);
    if (confirmPwInput) confirmPwInput.addEventListener('input', handlePasswordInput);
    handlePasswordInput();

    function showPwStatus(msg, ok) {
        if (!pwChangeStatus) return;
        pwChangeStatus.textContent = msg;
        pwChangeStatus.style.color = ok ? '#16a34a' : '#dc2626';
        pwChangeStatus.style.display = 'block';
    }

    if (btnChangePassword) {
        btnChangePassword.addEventListener('click', async function() {
            const newPw = newPwInput ? newPwInput.value.trim() : '';
            const confirmPw = confirmPwInput ? confirmPwInput.value.trim() : '';
            const currentPw = currentPwInput ? currentPwInput.value.trim() : '';
            if (pwChangeStatus) pwChangeStatus.style.display = 'none';
            const rules = updateRequirementUI();

            if (!newPw) { showPwStatus('Please enter a new password.', false); return; }
            if (!rules.minLength || !rules.hasUppercase || !rules.hasLowercase || !rules.hasNumber || !rules.hasSpecial) {
                showPwStatus('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.', false);
                return;
            }
            if (!rules.matches) { showPwStatus('Passwords do not match.', false); return; }
            if (!currentPw) {
                if (currentPwRow) { currentPwRow.style.display = 'block'; }
                showPwStatus('Please enter your current password.', false);
                if (currentPwInput) currentPwInput.focus();
                return;
            }

            btnChangePassword.disabled = true;
            btnChangePassword.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i>Sending code...';

            const body1 = new URLSearchParams();
            body1.append('action', 'send_otp');
            body1.append('type', 'password');
            body1.append('current_password', currentPw);
            body1.append('new_password', newPw);
            body1.append('csrf_token', SECURITY_CSRF_TOKEN);

            try {
                const res = await fetch('partials/account-security-handler.php', { method: 'POST', body: body1 });
                const data = await res.json();
                if (!data.success) {
                    showPwStatus(data.message || 'Failed to send code.', false);
                    btnChangePassword.disabled = false;
                    btnChangePassword.innerHTML = '<i class="fas fa-lock" style="margin-right:8px;"></i>Change Password';
                    return;
                }
            } catch (e) {
                showPwStatus('Network error. Please try again.', false);
                btnChangePassword.disabled = false;
                btnChangePassword.innerHTML = '<i class="fas fa-lock" style="margin-right:8px;"></i>Change Password';
                return;
            }

            // Show OTP modal for password verification
            const otpModal = document.getElementById('otp-modal');
            const otpSubtitle = document.getElementById('otp-subtitle');
            const otpError2 = document.getElementById('otp-error');
            const otpInputEl = document.getElementById('otp-input');
            if (otpModal) {
                otpSubtitle.textContent = 'A verification code has been sent to your registered email.';
                otpError2.style.display = 'none';
                otpInputEl.value = '';
                otpModal.style.display = 'flex';
            }
            btnChangePassword.disabled = false;
            btnChangePassword.innerHTML = '<i class="fas fa-lock" style="margin-right:8px;"></i>Change Password';
        });
    }

    // OTP modal (password verification)
    const otpModal = document.getElementById('otp-modal');
    const otpSubmitBtn = document.getElementById('otp-submit');
    const otpCloseBtn = document.getElementById('otp-close');
    const otpErrorEl = document.getElementById('otp-error');
    const otpInputEl = document.getElementById('otp-input');

    if (otpSubmitBtn) {
        otpSubmitBtn.addEventListener('click', async function() {
            const code = otpInputEl ? otpInputEl.value.trim() : '';
            if (code.length !== 6) { if (otpErrorEl) { otpErrorEl.textContent = 'Please enter a valid 6-digit code.'; otpErrorEl.style.display='block'; } return; }
            otpSubmitBtn.disabled = true;
            otpSubmitBtn.textContent = 'Verifying...';

            const body = new URLSearchParams();
            body.append('action', 'verify_change_password');
            body.append('otp_code', code);
            body.append('csrf_token', SECURITY_CSRF_TOKEN);

            try {
                const res = await fetch('partials/account-security-handler.php', { method: 'POST', body });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('otp-subtitle').textContent = '✅ ' + data.message;
                    if (otpErrorEl) otpErrorEl.style.display = 'none';
                    otpSubmitBtn.textContent = 'Redirecting...';
                    showLoginRedirect(data.message || 'Password updated successfully. Redirecting to login...', data.redirect);
                } else {
                    if (otpErrorEl) { otpErrorEl.textContent = data.message || 'Verification failed.'; otpErrorEl.style.display='block'; }
                    otpSubmitBtn.disabled = false;
                    otpSubmitBtn.textContent = 'Verify & Apply';
                }
            } catch (e) {
                if (otpErrorEl) { otpErrorEl.textContent = 'Network error. Please try again.'; otpErrorEl.style.display='block'; }
                otpSubmitBtn.disabled = false;
                otpSubmitBtn.textContent = 'Verify & Apply';
            }
        });
    }

    if (otpInputEl) {
        otpInputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); if (otpSubmitBtn) otpSubmitBtn.click(); }
        });
    }
    if (otpCloseBtn) otpCloseBtn.addEventListener('click', function() { if (otpModal) otpModal.style.display = 'none'; });
    if (otpModal) otpModal.addEventListener('click', function(e) { if (e.target === otpModal) otpModal.style.display = 'none'; });
});
</script>

<?php require_once 'partials/footer.php'; ?> 