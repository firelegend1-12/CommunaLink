<?php
/**
 * Registration Page
 */

require_once 'config/init.php';

// Apply security headers for public page rollout
apply_page_security_headers('public');

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . app_url('/admin/index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="assets/images/barangay-logo.png" type="image/png">
    <link rel="stylesheet" href="assets/css/register.min.css?v=<?= filemtime('assets/css/register.min.css') ?>">
</head>
<body>
    <div class="auth-container">
        <!-- Header -->
        <header class="auth-header">
            <div class="auth-header-left">
                <img src="assets/images/barangay-logo.png" alt="Barangay Logo" style="height: 72px; width: auto;">
            </div>
        </header>

        <!-- Main Content -->
        <main class="auth-main">
            <img src="assets/svg/wave-bg.svg" alt="" class="wave-bg">
            <div class="auth-content">
                <!-- Left Column - Form -->
                <div class="auth-form-container">
                    <h1>Create Account</h1>
                    <p class="subtitle">
                        Join our community platform and help us build a better, more connected barangay
                    </p>

                    <?php 
                    if(isset($_SESSION['error_message'])) {
                        echo '<div class="alert alert-error"><p>' . $_SESSION['error_message'] . '</p></div>';
                        unset($_SESSION['error_message']);
                    }
                    ?>

                    <form action="<?= htmlspecialchars(app_url('/includes/register-handler.php')) ?>" method="POST" id="registrationForm">

                        
                        <!-- Step 1: Account Information -->
                        <div class="form-section" id="step1">
                            <h3><i class="fas fa-user-lock"></i> Account Information</h3>
                            
                        <div class="form-group">
                                <label for="fullname">Full Name <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input id="fullname" name="fullname" type="text" required placeholder="Enter your full name">
                            </div>
                        </div>

                        <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input id="email" name="email" type="email" autocomplete="email" required placeholder="example@gmail.com">
                            </div>
                            <small style="color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem; display: block;">✓ Must be a valid email address</small>
                        </div>
                        
                        <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input id="password" name="password" type="password" required placeholder="Minimum 8 characters">
                            </div>
                            <small style="font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <div id="req-length" style="color: #ef4444;">✓ Minimum 8 characters</div>
                                <div id="req-number" style="color: #ef4444;">✓ At least one number (0-9)</div>
                                <div id="req-special" style="color: #ef4444;">✓ At least one special character (!@#$%^&*)</div>
                            </small>
                        </div>
                        
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input id="confirm_password" name="confirm_password" type="password" required placeholder="Confirm your password">
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Personal Information -->
                        <div class="form-section" id="step2" style="display: none;">
                            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user input-icon"></i>
                                        <input id="first_name" name="first_name" type="text" required placeholder="First name">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="middle_initial">Middle Initial</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user input-icon"></i>
                                        <input id="middle_initial" name="middle_initial" type="text" maxlength="5" placeholder="M.I.">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input id="last_name" name="last_name" type="text" required placeholder="Last name">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gender">Gender <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-venus-mars input-icon"></i>
                                        <select id="gender" name="gender" required>
                                            <option value="">Select gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-calendar input-icon"></i>
                                        <input id="date_of_birth" name="date_of_birth" type="date" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="place_of_birth">Place of Birth <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-map-marker-alt input-icon"></i>
                                    <input id="place_of_birth" name="place_of_birth" type="text" required placeholder="City, Province">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="religion">Religion</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-pray input-icon"></i>
                                        <input id="religion" name="religion" type="text" placeholder="Religion">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="citizenship">Citizenship <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-flag input-icon"></i>
                                        <input id="citizenship" name="citizenship" type="text" required placeholder="Filipino" value="Filipino">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="civil_status">Civil Status <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-heart input-icon"></i>
                                        <select id="civil_status" name="civil_status" required>
                                            <option value="">Select status</option>
                                            <option value="Single">Single</option>
                                            <option value="Married">Married</option>
                                            <option value="Widowed">Widowed</option>
                                            <option value="Separated">Separated</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="voter_status">Voter Status <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-vote-yea input-icon"></i>
                                        <select id="voter_status" name="voter_status" required>
                                            <option value="">Select status</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Contact & Address -->
                        <div class="form-section" id="step3" style="display: none;">
                            <h3><i class="fas fa-address-book"></i> Contact & Address Information</h3>
                            
                            <div class="form-group">
                                <label for="contact_no">Contact Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input id="contact_no" name="contact_no" type="tel" placeholder="09XX XXX XXXX">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Complete Address <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-home input-icon"></i>
                                    <textarea id="address" name="address" rows="3" required placeholder="House/Unit No., Street, Barangay, City/Municipality, Province"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review & Submit -->
                        <div class="form-section" id="step4" style="display: none;">
                            <h3><i class="fas fa-check-circle"></i> Review Your Information</h3>
                            <div id="reviewContent">
                                <!-- Review content will be populated by JavaScript -->
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="btn-group" id="btnGroup">
                            <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">Previous</button>
                            <button type="button" class="btn btn-secondary" id="nextBtn">Next</button>
                            <button type="submit" class="submit-btn" id="submitBtn" style="display: none;">Create Account</button>
                        </div>
                    </form>

                    <p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">
                        Already have an account? <a href="index.php" class="link">Sign In</a>
                    </p>
                </div>
                
                <!-- Right Column - Image/Info -->
                <div class="auth-image-container">
                    <p>
                        In today's rapidly evolving communities, efficient and secure identification systems are essential for streamlined governance and effective service delivery.
                    </p>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/register.min.js?v=<?= filemtime('assets/js/register.min.js') ?>" defer></script>
    
    <script>
        // Password requirements validation
        const passwordField = document.getElementById('password');
        const reqLength = document.getElementById('req-length');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');

        if (passwordField) {
            passwordField.addEventListener('input', function() {
                const password = this.value;

                // Check minimum 8 characters
                if (password.length >= 8) {
                    reqLength.style.color = '#22c55e';
                } else {
                    reqLength.style.color = '#ef4444';
                }

                // Check for at least one number
                if (/[0-9]/.test(password)) {
                    reqNumber.style.color = '#22c55e';
                } else {
                    reqNumber.style.color = '#ef4444';
                }

                // Check for at least one special character
                if (/[!@#$%^&*()_+\-=\[\]{};:'"",.<>?\/\\|`~]/.test(password)) {
                    reqSpecial.style.color = '#22c55e';
                } else {
                    reqSpecial.style.color = '#ef4444';
                }
            });
        }
    </script>
</body>
</html>
