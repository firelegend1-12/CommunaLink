<?php
/**
 * Add Resident Page
 */

// Include authentication system
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
require_login();

// Page title
$page_title = "Add Resident - CommunaLink";

$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$religion_options = [
    'Roman Catholic',
    'Christian',
    'Protestant',
    'Iglesia ni Cristo',
    'Islam',
    'Buddhism',
    'Hinduism',
    'Judaism',
    'Seventh-day Adventist',
    'Jehovah\'s Witness',
    'Church of Christ',
    'Born Again',
    'Mormon',
    'LDS',
    'Sikhism',
    'Taoism',
    'Baha\'i',
    'Atheist',
    'Agnostic',
    'Other'
];

$citizenship_options = [
    'Filipino',
    'American',
    'Australian',
    'British',
    'Canadian',
    'Chinese',
    'Japanese',
    'Korean',
    'Indian',
    'Indonesian',
    'Malaysian',
    'Singaporean',
    'Thai',
    'Vietnamese',
    'Mongolian',
    'Pakistani',
    'Bangladeshi',
    'Nepalese',
    'Sri Lankan',
    'Saudi Arabian',
    'Emirati',
    'Qatari',
    'Kuwaiti',
    'Omani',
    'Bahraini',
    'Turkish',
    'Russian',
    'Ukrainian',
    'French',
    'German',
    'Spanish',
    'Italian',
    'Portuguese',
    'Dutch',
    'Belgian',
    'Swiss',
    'Swedish',
    'Norwegian',
    'Finnish',
    'Danish',
    'Polish',
    'Czech',
    'Slovak',
    'Hungarian',
    'Romanian',
    'Bulgarian',
    'Greek',
    'Irish',
    'Scottish',
    'New Zealander',
    'Mexican',
    'Brazilian',
    'Argentine',
    'Chilean',
    'Colombian',
    'Peruvian',
    'Venezuelan',
    'South African',
    'Egyptian',
    'Nigerian',
    'Kenyan',
    'Moroccan',
    'Ethiopian',
    'Other'
];

function old_value(array $data, string $key): string {
    return htmlspecialchars((string)($data[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Add New Resident</h1>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20 divide-y divide-gray-200">
                                
                                <div class="py-1">
                                    <a href="account.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user-circle mr-2 text-gray-500"></i>
                                        My Account
                                    </a>
                                </div>
                                <div class="py-1">
                                    <a href="../../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2 text-gray-500"></i>
                                        Sign Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md" role="alert">
                        <p class="font-bold">Success</p>
                        <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert">
                        <p class="font-bold">Error</p>
                        <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <form action="../partials/add-resident-handler.php" method="POST" enctype="multipart/form-data" class="space-y-6" id="residentForm">
                        <!-- Personal Information Section -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Personal Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                                    <input type="text" name="first_name" id="first_name" required autocomplete="given-name" pattern="[A-Za-z\s'\-\.]+" minlength="2" maxlength="100" value="<?php echo old_value($form_data, 'first_name'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Enter first name">
                                </div>
                                <div>
                                    <label for="middle_initial" class="block text-sm font-medium text-gray-700">Middle Initial</label>
                                    <input type="text" name="middle_initial" id="middle_initial" required autocomplete="additional-name" pattern="[A-Za-z\.\- ]+" minlength="1" maxlength="5" value="<?php echo old_value($form_data, 'middle_initial'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="M.I.">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" required autocomplete="family-name" pattern="[A-Za-z\s'\-\.]+" minlength="2" maxlength="100" value="<?php echo old_value($form_data, 'last_name'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Enter last name">
                                </div>
                                <div>
                                    <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
                                    <select name="gender" id="gender" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="" <?php echo empty($form_data['gender']) ? 'selected' : ''; ?>>Select gender</option>
                                        <option value="Male" <?php echo (($form_data['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (($form_data['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (($form_data['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo old_value($form_data, 'date_of_birth'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="place_of_birth" class="block text-sm font-medium text-gray-700">Place of Birth</label>
                                    <input type="text" name="place_of_birth" id="place_of_birth" required minlength="3" maxlength="255" pattern="^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9\s\-\.,'#/()]+$" title="Place of Birth must include both letters and numbers." value="<?php echo old_value($form_data, 'place_of_birth'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g. Pakiad 1, Oton">
                                </div>
                                <div>
                                    <label for="age" class="block text-sm font-medium text-gray-700">Age</label>
                                    <input type="number" name="age" id="age" readonly required min="1" max="120" value="<?php echo old_value($form_data, 'age'); ?>" class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Auto-calculated from DOB">
                                </div>
                                <div>
                                    <label for="religion" class="block text-sm font-medium text-gray-700">Religion</label>
                                    <select name="religion" id="religion" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="" <?php echo empty($form_data['religion']) ? 'selected' : ''; ?>>Select religion</option>
                                        <?php foreach ($religion_options as $religion_option): ?>
                                            <option value="<?php echo htmlspecialchars($religion_option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($form_data['religion'] ?? '') === $religion_option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($religion_option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="citizenship" class="block text-sm font-medium text-gray-700">Citizenship</label>
                                    <select name="citizenship" id="citizenship" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="" <?php echo empty($form_data['citizenship']) ? 'selected' : ''; ?>>Select citizenship</option>
                                        <?php foreach ($citizenship_options as $citizenship_option): ?>
                                            <option value="<?php echo htmlspecialchars($citizenship_option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($form_data['citizenship'] ?? '') === $citizenship_option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($citizenship_option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="civil_status" class="block text-sm font-medium text-gray-700">Civil Status</label>
                                    <select name="civil_status" id="civil_status" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="" <?php echo empty($form_data['civil_status']) ? 'selected' : ''; ?>>Select civil status</option>
                                        <option value="Single" <?php echo (($form_data['civil_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo (($form_data['civil_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                                        <option value="Widowed" <?php echo (($form_data['civil_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        <option value="Separated" <?php echo (($form_data['civil_status'] ?? '') === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Contact Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                    <input type="email" name="email" id="email" required autocomplete="email" value="<?php echo old_value($form_data, 'email'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="example@gmail.com">
                                    <div class="mt-2">
                                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                        <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Minimum 8 characters">
                                        <div class="mt-2 text-xs space-y-1">
                                            <div id="req-length" class="text-red-500">✓ Password must be at least 8 characters</div>
                                            <div id="req-number" class="text-red-500">✓ Password must contain at least one number (0-9)</div>
                                            <div id="req-special" class="text-red-500">✓ Password must contain at least one special character (!@#$%^&*)</div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="contact_no" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                    <input type="tel" name="contact_no" id="contact_no" required inputmode="tel" autocomplete="tel" pattern="^(\\+?63|0)9\\d{9}$" minlength="11" maxlength="13" value="<?php echo old_value($form_data, 'contact_no'); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="09123456789">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                    <textarea name="address" id="address" rows="3" required minlength="5" maxlength="500" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Complete address"><?php echo old_value($form_data, 'address'); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Identification and Uploads Section -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-4">Identification & Uploads</h3>
                            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>ID Number:</strong> Will be automatically generated for year <?php echo date('Y'); ?> (BR-<?php echo date('Y'); ?>-XXXX format)
                                </p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="id_number" class="block text-sm font-medium text-gray-700">ID Number</label>
                                    <input type="text" name="id_number" id="id_number" readonly class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md shadow-sm text-gray-500 sm:text-sm" value="Auto-generated">
                                    <p class="text-xs text-gray-500 mt-1">Format: BR-<?php echo date('Y'); ?>-XXXX (Year <?php echo date('Y'); ?> sequence)</p>
                                </div>
                                <div>
                                    <label for="voter_status" class="block text-sm font-medium text-gray-700">Voter Status</label>
                                    <select name="voter_status" id="voter_status" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                        <option value="" <?php echo empty($form_data['voter_status']) ? 'selected' : ''; ?>>Select voter status</option>
                                        <option value="Yes" <?php echo (($form_data['voter_status'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                        <option value="No" <?php echo (($form_data['voter_status'] ?? '') === 'No') ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="profile_image" class="block text-sm font-medium text-gray-700">Profile Image</label>
                                    <input type="file" name="profile_image" id="profile_image" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                <div>
                                    <label for="signature" class="block text-sm font-medium text-gray-700">Signature</label>
                                    <input type="file" name="signature" id="signature" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end pt-4 border-t mt-6">
                            <a href="residents.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md mr-2">Cancel</a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Save Resident</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('residentForm');
        const passwordField = document.getElementById('password');
        const reqLength = document.getElementById('req-length');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        const dateOfBirthField = document.getElementById('date_of_birth');
        const ageField = document.getElementById('age');
        const contactField = document.getElementById('contact_no');
        const placeOfBirthField = document.getElementById('place_of_birth');
        let suppressBeforeUnload = false;

        function hasResidentFormInput() {
            const fields = form.querySelectorAll('input, select, textarea');

            for (const field of fields) {
                const tag = field.tagName.toLowerCase();
                const type = (field.type || '').toLowerCase();

                if (type === 'hidden' || type === 'submit' || type === 'button') {
                    continue;
                }

                if (field.id === 'id_number') {
                    continue;
                }

                if (tag === 'select') {
                    if (field.value && field.value.trim() !== '') {
                        return true;
                    }
                    continue;
                }

                if (type === 'file') {
                    if (field.files && field.files.length > 0) {
                        return true;
                    }
                    continue;
                }

                if (String(field.value || '').trim() !== '') {
                    return true;
                }
            }

            return false;
        }

        function updatePasswordRequirements() {
            const password = passwordField.value;

            reqLength.style.color = password.length >= 8 ? '#22c55e' : '#ef4444';
            reqNumber.style.color = /[0-9]/.test(password) ? '#22c55e' : '#ef4444';
            reqSpecial.style.color = /[!@#$%^&*()_+\-=[\]{};:'",.<>?/\\|`~]/.test(password) ? '#22c55e' : '#ef4444';

            if (password.length < 8) {
                passwordField.setCustomValidity('Password must be at least 8 characters long.');
            } else if (!/[0-9]/.test(password)) {
                passwordField.setCustomValidity('Password must contain at least one number (0-9).');
            } else if (!/[!@#$%^&*()_+\-=[\]{};:'",.<>?/\\|`~]/.test(password)) {
                passwordField.setCustomValidity('Password must contain at least one special character (!@#$%^&*).');
            } else {
                passwordField.setCustomValidity('');
            }
        }

        function calculateAge() {
            if (!dateOfBirthField.value) {
                ageField.value = '';
                return;
            }

            const birthDate = new Date(dateOfBirthField.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            ageField.value = age > 0 ? age : '';
        }

        function validateContact() {
            if (!contactField.value) {
                contactField.setCustomValidity('Contact number is required.');
                return;
            }

            const validPhone = /^(\+?63|0)9\d{9}$/.test(contactField.value);
            contactField.setCustomValidity(validPhone ? '' : 'Enter a valid Philippine mobile number (e.g. 09123456789).');
        }

        function validatePlaceOfBirth() {
            if (!placeOfBirthField.value) {
                placeOfBirthField.setCustomValidity('Place of Birth is required.');
                return;
            }

            const validPlace = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9\s\-\.,'#/()]+$/.test(placeOfBirthField.value);
            placeOfBirthField.setCustomValidity(validPlace ? '' : 'Place of Birth must include both letters and numbers.');
        }

        passwordField.addEventListener('input', updatePasswordRequirements);
        dateOfBirthField.addEventListener('change', calculateAge);
        dateOfBirthField.addEventListener('input', calculateAge);
        contactField.addEventListener('input', validateContact);
        placeOfBirthField.addEventListener('input', validatePlaceOfBirth);

        form.addEventListener('submit', function (event) {
            calculateAge();
            updatePasswordRequirements();
            validateContact();
            validatePlaceOfBirth();

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            suppressBeforeUnload = true;
        });

        window.addEventListener('beforeunload', function (event) {
            if (suppressBeforeUnload) {
                return;
            }

            if (hasResidentFormInput()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        calculateAge();
        updatePasswordRequirements();
        validateContact();
    });
    </script>
</body>
</html>
