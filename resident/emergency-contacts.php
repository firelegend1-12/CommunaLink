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
        grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
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
    .info-content p a {
        color: inherit;
        text-decoration: none;
    }
    .info-content p a:hover {
        text-decoration: underline;
        color: var(--accent-blue, #3b82f6);
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
    
    @media (max-width: 767px) {
        .contact-container {
            gap: 20px;
        }
        .contact-section {
            padding: 16px;
        }
        .section-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .info-card {
            padding: 14px;
        }
        .notice-section {
            padding: 16px;
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

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
                    <h3>Fire Department (BFP Iloilo)</h3>
                    <p><a href="tel:0333373011">(033) 337-3011</a></p>
                    <p style="font-size: 0.9rem; margin-top: 2px;"><a href="tel:0333374989">(033) 337-4989</a></p>
                    <span class="subtext">Globe: <a href="tel:09359180727">0935-918-0727</a> | Smart: <a href="tel:09473869950">0947-386-9950</a> | TNT: <a href="tel:09639421215">0963-942-1215</a></span>
                </div>
            </div>
            <div class="info-card blue">
                <i class="fas fa-shield-alt info-icon"></i>
                <div class="info-content">
                    <h3>Police Station (ICPO)</h3>
                    <p><a href="tel:0333370400">(033) 337-0400</a></p>
                    <p style="font-size: 0.9rem; margin-top: 2px;"><a href="tel:166">166</a> (Direct Hotline)</p>
                    <span class="subtext">Mobile: <a href="tel:09083770194">0908-377-0194</a></span>
                </div>
            </div>
            <div class="info-card green">
                <i class="fas fa-ambulance info-icon"></i>
                <div class="info-content">
                    <h3>Medical Emergency & Rescue (ICER)</h3>
                    <p><a href="tel:0333332333">(033) 333-2333</a></p>
                    <p style="font-size: 0.9rem; margin-top: 2px;"><a href="tel:0333351554">(033) 335-1554</a> | <a href="tel:0333333333">(033) 333-3333</a></p>
                    <span class="subtext">Smart: <a href="tel:09190661554">0919-066-1554</a> | COC: <a href="tel:09190662333">0919-066-2333</a></span>
                </div>
            </div>
            <div class="info-card purple">
                <i class="fas fa-heartbeat info-icon"></i>
                <div class="info-content">
                    <h3>Philippine Red Cross (Iloilo)</h3>
                    <p><a href="tel:0333375950">(033) 337-5950</a></p>
                    <span class="subtext">Blood bank, first aid, disaster response</span>
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
            <div class="info-card amber">
                <i class="fas fa-envelope info-icon"></i>
                <div class="info-content">
                    <h3>Email Address</h3>
                    <p style="font-size: 1rem;"><a href="mailto:brgypakiad2023@gmail.com">brgypakiad2023@gmail.com</a></p>
                    <span class="subtext">Barangay Pakiad</span>
                </div>
            </div>
            <div class="info-card green">
                <i class="fas fa-mobile-alt info-icon"></i>
                <div class="info-content">
                    <h3>Barangay Office (Mobile)</h3>
                    <p><a href="tel:09943692152">0994 369 2152</a></p>
                    <span class="subtext">Direct hotline</span>
                </div>
            </div>
            <div class="info-card blue">
                <i class="fas fa-phone-alt info-icon"></i>
                <div class="info-content">
                    <h3>Barangay Office (Landline)</h3>
                    <p><a href="tel:3326539">332 65 39</a></p>
                    <span class="subtext">Office landline</span>
                </div>
            </div>
            <div class="info-card purple">
                <i class="fas fa-map-marker-alt info-icon"></i>
                <div class="info-content">
                    <h3>Office Address</h3>
                    <p style="font-size: 1rem;">Barangay Hall, Pakiad</p>
                    <span class="subtext">Oton, Iloilo</span>
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