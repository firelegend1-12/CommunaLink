<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// This is already in header.php, but for safety.
if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}

require_role('resident');

$page_title = "Emergency Contacts";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

require_once 'partials/header.php';
?>

<style>
    .contact-container {
        display: flex;
        flex-direction: column;
        gap: 35px;
    }
    .contact-section {
        background-color: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.07);
    }
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    .section-header i {
        font-size: 1.5rem;
        margin-right: 15px;
        width: 30px;
        text-align: center;
    }
    .section-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }
    .section-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    .info-card {
        background-color: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        border-left: 5px solid;
        display: flex;
        align-items: flex-start;
    }
    .info-icon {
        font-size: 1.2rem;
        margin-right: 15px;
        margin-top: 3px;
        width: 20px;
        text-align: center;
    }
    .info-content h3 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 8px;
    }
    .info-content p {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 500;
        color: #333;
    }
    .info-content .subtext {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 400;
    }

    /* Section Colors */
    .emergency-section .section-header i, .emergency-section .section-header h2 { color: #ef4444; }
    .barangay-section .section-header i, .barangay-section .section-header h2 { color: #3b82f6; }
    .government-section .section-header i, .government-section .section-header h2 { color: #4b5563; }

    /* Card Colors */
    .info-card.red { border-color: #ef4444; background-color: #fef2f2; }
    .info-card.red .info-icon { color: #ef4444; }
    .info-card.blue { border-color: #3b82f6; background-color: #eff6ff; }
    .info-card.blue .info-icon { color: #3b82f6; }
    .info-card.green { border-color: #10b981; background-color: #ecfdf5; }
    .info-card.green .info-icon { color: #10b981; }
    .info-card.purple { border-color: #8b5cf6; background-color: #f5f3ff; }
    .info-card.purple .info-icon { color: #8b5cf6; }
    .info-card.amber { border-color: #f59e0b; background-color: #fffbeb; }
    .info-card.amber .info-icon { color: #f59e0b; }
    .info-card.gray { border-color: #6b7280; background-color: #f3f4f6; }
    .info-card.gray .info-icon { color: #6b7280; }


    .notice-section {
        background-color: #fffbeb;
        border-radius: 16px;
        padding: 25px;
        display: flex;
        align-items: flex-start;
        border-left: 5px solid #f59e0b;
    }
    .notice-section .info-icon {
        color: #f59e0b;
        font-size: 1.5rem;
    }
    .notice-content h3 {
        color: #d97706;
        margin-top: 0;
        margin-bottom: 10px;
    }
    .notice-content p {
        margin: 0;
        color: #b45309;
        line-height: 1.6;
    }
    .notice-content p strong {
        font-weight: 700;
    }
    .back-button {
        display: inline-flex;
        align-items: center;
        padding: 10px 20px;
        background-color: white;
        color: var(--text-primary);
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 30px;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
    }
    .back-button:hover {
        background-color: #f8f9fa;
    }
    .back-button i {
        margin-right: 8px;
    }
</style>

<a href="dashboard.php" class="back-button">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>

<div class="contact-container">
    <!-- Emergency Numbers Section -->
    <div class="contact-section emergency-section">
        <div class="section-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h2>Emergency Numbers</h2>
        </div>
        <div class="section-grid">
            <div class="info-card red">
                <i class="fas fa-fire-extinguisher info-icon"></i>
                <div class="info-content">
                    <h3>Fire Department</h3>
                    <p>911</p>
                    <span class="subtext">National Emergency Hotline</span>
                </div>
            </div>
            <div class="info-card blue">
                <i class="fas fa-shield-alt info-icon"></i>
                <div class="info-content">
                    <h3>Police Station</h3>
                    <p>911</p>
                    <span class="subtext">National Emergency Hotline</span>
                </div>
            </div>
            <div class="info-card green">
                <i class="fas fa-ambulance info-icon"></i>
                <div class="info-content">
                    <h3>Medical Emergency</h3>
                    <p>911</p>
                    <span class="subtext">National Emergency Hotline</span>
                </div>
            </div>
            <div class="info-card purple">
                <i class="fas fa-life-ring info-icon"></i>
                <div class="info-content">
                    <h3>Rescue Services</h3>
                    <p>911</p>
                    <span class="subtext">National Emergency Hotline</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Barangay Office Contacts Section -->
    <div class="contact-section barangay-section">
        <div class="section-header">
            <i class="fas fa-building"></i>
            <h2>Barangay Office Contacts</h2>
        </div>
        <div class="section-grid">
            <div class="info-card blue">
                <i class="fas fa-user-tie info-icon"></i>
                <div class="info-content">
                    <h3>Barangay Captain</h3>
                    <p>+63 912 345 6789</p>
                    <span class="subtext">Captain Juan Dela Cruz</span>
                </div>
            </div>
            <div class="info-card green">
                <i class="fas fa-phone-alt info-icon"></i>
                <div class="info-content">
                    <h3>Barangay Office</h3>
                    <p>+63 2 8123 4567</p>
                    <span class="subtext">Main Office Line</span>
                </div>
            </div>
            <div class="info-card amber">
                <i class="fas fa-envelope info-icon"></i>
                <div class="info-content">
                    <h3>Email Address</h3>
                    <p style="font-size: 1rem;">info@barangaymasigasig.gov.ph</p>
                    <span class="subtext">General Inquiries</span>
                </div>
            </div>
            <div class="info-card purple">
                <i class="fas fa-map-marker-alt info-icon"></i>
                <div class="info-content">
                    <h3>Office Address</h3>
                    <p style="font-size: 1rem;">Barangay Hall, Masigasig Street</p>
                    <span class="subtext">Quezon City, Metro Manila</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Government Services Section -->
    <div class="contact-section government-section">
        <div class="section-header">
            <i class="fas fa-university"></i>
            <h2>Government Services</h2>
        </div>
        <div class="section-grid">
            <div class="info-card gray">
                <i class="fas fa-file-alt info-icon"></i>
                <div class="info-content">
                    <h3>Document Requests</h3>
                    <p>+63 2 8123 4568</p>
                    <span class="subtext">Barangay Clearance, Certificates</span>
                </div>
            </div>
            <div class="info-card gray">
                <i class="fas fa-users info-icon"></i>
                <div class="info-content">
                    <h3>Social Services</h3>
                    <p>+63 2 8123 4569</p>
                    <span class="subtext">Assistance Programs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Important Notice Section -->
    <div class="notice-section">
        <i class="fas fa-exclamation-triangle info-icon"></i>
        <div class="notice-content">
            <h3>Important Notice</h3>
            <p>
                For life-threatening emergencies, always call <strong>911</strong> first. The barangay office is available during business hours (8:00 AM - 5:00 PM, Monday to Friday).
            </p>
            <p style="margin-top: 10px;">
                <strong>After Hours:</strong> For urgent barangay matters outside office hours, contact the Barangay Captain directly.
            </p>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?> 