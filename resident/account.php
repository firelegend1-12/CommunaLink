<?php
require_once '../config/database.php';
$page_title = "My Account";
require_once 'partials/header.php';

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
        width: 80%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.2s;
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
            <div class="profile-picture-container">
                <img src="<?= !empty($resident['profile_image_path']) ? '../admin/' . htmlspecialchars($resident['profile_image_path']) : 'https://via.placeholder.com/150' ?>" alt="Profile Picture" class="profile-picture" id="profile-pic-preview">
                <label for="profile_image_input" class="upload-overlay">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" name="profile_image" id="profile_image_input" accept="image/*">
            </div>
            <h2 class="text-xl font-bold mb-2"><?= htmlspecialchars($resident['first_name'] ?? '') . ' ' . htmlspecialchars($resident['last_name'] ?? '') ?></h2>
            <p class="text-gray-500 mb-2"><?= htmlspecialchars($resident['email'] ?? '') ?></p>
            <input type="hidden" name="update_profile_pic" value="1">
        </form>
    </aside>

    <div class="form-container">
        <div class="form-header">
            <h2>Personal Information</h2>
        </div>
        
        <form action="partials/account-handler.php" method="POST">
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
                        <div class="form-group">
                            <label for="citizenship">Citizenship</label>
                            <input type="text" id="citizenship" name="citizenship" value="<?= htmlspecialchars($resident['citizenship'] ?? 'Filipino') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Contact & Address</div>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($resident['email'] ?? '') ?>" readonly>
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
                    <div class="form-section-title">Other Information</div>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label for="voter_status">Voter Status</label>
                            <select id="voter_status" name="voter_status" required>
                                <option value="Yes" <?= ($resident['voter_status'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= ($resident['voter_status'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                            </select>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const profileImageInput = document.getElementById('profile_image_input');
    const profilePicForm = document.getElementById('profile-pic-form');
    const profilePicPreview = document.getElementById('profile-pic-preview');

    profileImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            // Preview the image before submitting
            const reader = new FileReader();
            reader.onload = function(e) {
                profilePicPreview.src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
            
            // Submit the form automatically when a file is chosen
            setTimeout(() => {
                profilePicForm.submit();
            }, 500);
        }
    });

    const birthdateInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');

    function calculateAge() {
        if(birthdateInput.value) {
            const birthDate = new Date(birthdateInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            ageInput.value = age >= 0 ? age : '';
        } else {
            ageInput.value = '';
        }
    }

    birthdateInput.addEventListener('change', calculateAge);
    // Calculate age on page load if birthdate is already set
    calculateAge();
});
</script>

<?php require_once 'partials/footer.php'; ?> 