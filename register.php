<?php
/**
 * Registration Page
 */
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: admin/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CommuniLink</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="assets/svg/logos.jpg" type="image/jpeg">
    <style>
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        .form-section h3 {
            color: #fff;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 0.5rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-row.full-width {
            grid-template-columns: 1fr;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #fff;
            font-weight: 500;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input,
        .input-wrapper select,
        .input-wrapper textarea {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .input-wrapper input:focus,
        .input-wrapper select:focus,
        .input-wrapper textarea:focus {
            outline: none;
            border-color: #4F46E5;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        .input-wrapper input::placeholder {
            color: #888;
        }
        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 0.9rem;
        }
        .required {
            color: #EF4444;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 2rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .submit-btn {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: #fff;
            width: 100%;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Header -->
        <header class="auth-header">
            <div class="auth-header-left">
                <img src="assets/svg/logos.jpg" alt="CommuniLink Logo" style="height: 50px; width: auto;">
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

                    <form action="includes/register-handler.php" method="POST" id="registrationForm">

                        
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
                        </div>
                        
                        <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input id="password" name="password" type="password" required placeholder="Minimum 8 characters">
                            </div>
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
                    <img src="assets/sk.svg" alt="SK Logo">
                    <p>
                        In today's rapidly evolving communities, efficient and secure identification systems are essential for streamlined governance and effective service delivery.
                    </p>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            updateStepDisplay();
            setupFormValidation();
        });

        // Navigation
        document.getElementById('nextBtn').addEventListener('click', nextStep);
        document.getElementById('prevBtn').addEventListener('click', prevStep);

        function nextStep() {
            if (validateCurrentStep()) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateStepDisplay();
                }
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepDisplay();
            }
        }

        function updateStepDisplay() {
            // Hide all steps
            for (let i = 1; i <= totalSteps; i++) {
                const stepElement = document.getElementById(`step${i}`);
                if (stepElement) {
                    stepElement.style.display = 'none';
                }
            }

            // Show current step
            const currentStepElement = document.getElementById(`step${currentStep}`);
            if (currentStepElement) {
                currentStepElement.style.display = 'block';
            }

            // Update buttons
            updateButtons();

            // If on last step, populate review
            if (currentStep === totalSteps) {
                populateReview();
            }
        }

        function updateButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');

            prevBtn.style.display = currentStep > 1 ? 'block' : 'none';
            nextBtn.style.display = currentStep < totalSteps ? 'block' : 'none';
            submitBtn.style.display = currentStep === totalSteps ? 'block' : 'none';
        }

        function validateCurrentStep() {
            const currentStepElement = document.getElementById(`step${currentStep}`);
            const requiredFields = currentStepElement.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#EF4444';
                    isValid = false;
                } else {
                    field.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                }
            });

            // Special validation for step 1
            if (currentStep === 1) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    document.getElementById('confirm_password').style.borderColor = '#EF4444';
                    alert('Passwords do not match!');
                    isValid = false;
                }
            }

            return isValid;
        }

        function populateReview() {
            const reviewContent = document.getElementById('reviewContent');
            const formData = new FormData(document.getElementById('registrationForm'));
            
            let reviewHTML = '<div style="color: #333; background: rgba(255,255,255,0.9); padding: 1rem; border-radius: 8px;">';
            reviewHTML += '<h4 style="margin-bottom: 1rem; color: #4F46E5;">Please review your information:</h4>';
            
            // Account Info
            reviewHTML += '<div style="margin-bottom: 1rem;"><strong>Account Information:</strong><br>';
            reviewHTML += `Name: ${formData.get('fullname')}<br>`;
            reviewHTML += `Email: ${formData.get('email')}<br></div>`;
            
            // Personal Info
            reviewHTML += '<div style="margin-bottom: 1rem;"><strong>Personal Information:</strong><br>';
            reviewHTML += `First Name: ${formData.get('first_name')}<br>`;
            reviewHTML += `Middle Initial: ${formData.get('middle_initial') || 'N/A'}<br>`;
            reviewHTML += `Last Name: ${formData.get('last_name')}<br>`;
            reviewHTML += `Gender: ${formData.get('gender')}<br>`;
            reviewHTML += `Date of Birth: ${formData.get('date_of_birth')}<br>`;
            reviewHTML += `Place of Birth: ${formData.get('place_of_birth')}<br>`;
            reviewHTML += `Religion: ${formData.get('religion') || 'N/A'}<br>`;
            reviewHTML += `Citizenship: ${formData.get('citizenship')}<br>`;
            reviewHTML += `Civil Status: ${formData.get('civil_status')}<br>`;
            reviewHTML += `Voter Status: ${formData.get('voter_status')}<br></div>`;
            
            // Contact Info
            reviewHTML += '<div style="margin-bottom: 1rem;"><strong>Contact Information:</strong><br>';
            reviewHTML += `Contact Number: ${formData.get('contact_no') || 'N/A'}<br>`;
            reviewHTML += `Address: ${formData.get('address')}<br></div>`;
            
            reviewHTML += '<p style="color: #10B981; font-weight: 600;">✓ All information looks correct. Click "Create Account" to proceed.</p>';
            reviewHTML += '</div>';
            
            reviewContent.innerHTML = reviewHTML;
        }

        function setupFormValidation() {
            // Auto-fill first and last name from fullname
            document.getElementById('fullname').addEventListener('input', function() {
                const fullname = this.value;
                const nameParts = fullname.split(' ');
                const firstName = nameParts[0] || '';
                const lastName = nameParts[nameParts.length - 1] || '';
                
                if (nameParts.length > 1) {
                    document.getElementById('first_name').value = firstName;
                    document.getElementById('last_name').value = lastName;
                }
            });

            // Calculate age from date of birth
            document.getElementById('date_of_birth').addEventListener('change', function() {
                const birthDate = new Date(this.value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
            });
        }
    </script>
</body>
</html> 