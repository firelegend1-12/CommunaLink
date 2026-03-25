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

$page_title = "Barangay Services";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

require_once 'partials/header.php';
?>

<style>
    .services-banner {
        background: linear-gradient(135deg, #34d399, #3b82f6);
        padding: 50px 30px;
        border-radius: 16px;
        color: white;
        text-align: center;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .services-banner::before {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .services-banner h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .services-banner h1 i {
        margin-right: 15px;
        font-size: 2.2rem;
    }
    
    .services-banner p {
        font-size: 1.1rem;
        opacity: 0.9;
        max-width: 700px;
        margin: 0 auto;
    }
    
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(350px, 100%), 1fr));
        gap: 25px;
        margin-bottom: 60px;
    }
    
    .service-card {
        background-color: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
        overflow: hidden;
    }
    
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .service-icon {
        width: 55px;
        height: 55px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }
    
    .service-icon i {
        font-size: 24px;
        color: white;
    }
    
    .service-title {
        display: flex;
        flex-direction: column;
    }
    
    .service-title h3 {
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0 0 5px 0;
        color: #333;
    }
    
    .service-subtitle {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 15px;
    }
    
    .service-description {
        color: #555;
        margin-bottom: 20px;
        line-height: 1.5;
    }
    
    .service-card.red .service-icon {
        background-color: #fee2e2;
    }
    .service-card.red .service-icon i {
        color: #ef4444;
    }
    
    .service-card.blue .service-icon {
        background-color: #dbeafe;
    }
    .service-card.blue .service-icon i {
        color: #3b82f6;
    }
    
    .service-card.green .service-icon {
        background-color: #d1fae5;
    }
    .service-card.green .service-icon i {
        color: #10b981;
    }
    
    .service-card.purple .service-icon {
        background-color: #ede9fe;
    }
    .service-card.purple .service-icon i {
        color: #8b5cf6;
    }
    
    .service-card.orange .service-icon {
        background-color: #ffedd5;
    }
    .service-card.orange .service-icon i {
        color: #f97316;
    }
    
    .service-card.indigo .service-icon {
        background-color: #e0e7ff;
    }
    .service-card.indigo .service-icon i {
        color: #6366f1;
    }
    
    .document-services {
        background-color: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 40px;
    }
    
    .document-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .document-header i {
        font-size: 1.8rem;
        color: #3b82f6;
        margin-right: 15px;
    }
    
    .document-header h2 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #333;
        margin: 0;
    }
    
    .document-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
        gap: 20px;
    }
    
    .document-card {
        border-radius: 12px;
        padding: 25px;
        transition: transform 0.2s;
        position: relative;
    }
    
    .document-card:hover {
        transform: translateY(-5px);
    }
    
    .document-card.blue {
        background-color: #eff6ff;
        border-left: 5px solid #3b82f6;
    }
    
    .document-card.green {
        background-color: #ecfdf5;
        border-left: 5px solid #10b981;
    }
    
    .document-card.purple {
        background-color: #f5f3ff;
        border-left: 5px solid #8b5cf6;
    }
    
    .document-icon {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .document-icon i {
        font-size: 1.5rem;
        margin-right: 10px;
    }
    
    .document-card.blue .document-icon i {
        color: #3b82f6;
    }
    
    .document-card.green .document-icon i {
        color: #10b981;
    }
    
    .document-card.purple .document-icon i {
        color: #8b5cf6;
    }
    
    .document-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0 0 15px 0;
        color: #333;
    }
    
    .document-description {
        color: #555;
        margin-bottom: 15px;
        line-height: 1.5;
    }
    
    .document-info {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 5px;
    }
    
    .back-button {
        display: inline-flex;
        align-items: center;
        padding: 10px 20px;
        background-color: var(--primary-bg);
        color: var(--text-primary);
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 30px;
        transition: all 0.2s ease;
    }
    
    .back-button:hover {
        background-color: #e4e6eb;
    }
    
    .back-button i {
        margin-right: 8px;
    }
    
    .action-button {
        display: inline-block;
        padding: 10px 16px;
        background-color: #3b82f6;
        color: white;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
        text-align: center;
    }
    
    .action-button:hover {
        background-color: #2563eb;
    }
    
    @media (max-width: 767px) {
        .services-banner h1 {
            font-size: 1.5rem;
            flex-direction: column;
            gap: 6px;
        }
        .services-banner h1 i {
            margin-right: 0;
        }
        .services-banner {
            padding: 30px 16px;
        }
        .service-card:hover,
        .document-card:hover {
            transform: none;
        }
        .action-button {
            display: block;
            text-align: center;
        }
    }
</style>

<a href="dashboard.php" class="back-button">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>

<div class="services-banner">
    <h1><i class="fas fa-hand-holding-heart"></i> Barangay Services</h1>
    <p>Access all available services and information for residents</p>
</div>

<div class="services-grid">
    <div class="service-card red" onclick="window.location.href='report-incident.php';" style="cursor: pointer;">
        <div class="service-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="service-title">
            <h3>Report Incident</h3>
            <div class="service-subtitle">Emergency or issue reporting</div>
        </div>
        <p class="service-description">
            Report emergencies, crimes, safety hazards, or any community issues that need attention.
        </p>
    </div>
    
    <div class="service-card blue" onclick="window.location.href='my-reports.php';" style="cursor: pointer;">
        <div class="service-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="service-title">
            <h3>My Reports</h3>
            <div class="service-subtitle">View your submitted reports</div>
        </div>
        <p class="service-description">
            Track the status of your incident reports and view admin responses.
        </p>
    </div>
    
    <div class="service-card green" onclick="window.location.href='emergency-contacts.php';" style="cursor: pointer;">
        <div class="service-icon">
            <i class="fas fa-phone-alt"></i>
        </div>
        <div class="service-title">
            <h3>Emergency Contacts</h3>
            <div class="service-subtitle">Important phone numbers</div>
        </div>
        <p class="service-description">
            Access emergency numbers, barangay contacts, and government service hotlines.
        </p>
    </div>
    
    <div class="service-card purple">
        <div class="service-icon">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="service-title">
            <h3>Barangay Events</h3>
            <div class="service-subtitle">Community activities</div>
        </div>
        <p class="service-description">
            Stay updated with upcoming events, activities, and community programs.
        </p>
    </div>
    
    <div class="service-card orange" onclick="window.location.href='#incident-map';" style="cursor: pointer;">
        <div class="service-icon">
            <i class="fas fa-map-marked-alt"></i>
        </div>
        <div class="service-title">
            <h3>Incident Map</h3>
            <div class="service-subtitle">View incident locations</div>
        </div>
        <p class="service-description">
            View a map of reported incidents in the barangay and submit new reports.
        </p>
    </div>
    
    <div class="service-card indigo" onclick="window.location.href='chat.php';" style="cursor: pointer;">
        <div class="service-icon">
            <i class="fas fa-comments"></i>
        </div>
        <div class="service-title">
            <h3>Chat Support</h3>
            <div class="service-subtitle">Real-time assistance</div>
        </div>
        <p class="service-description">
            Chat with barangay officials for immediate assistance and inquiries.
        </p>
        <div style="text-align: center; margin-top: 10px;">
            <div class="action-button">Start Chat</div>
        </div>
    </div>
</div>

<div class="document-services">
    <div class="document-header">
        <i class="fas fa-file-alt"></i>
        <h2>Document Services</h2>
    </div>
    
    <div class="document-grid">
        <!-- Barangay Clearance -->
        <div class="document-card blue">
            <div class="document-icon">
                <i class="fas fa-certificate"></i>
                <h3 class="document-title">Barangay Clearance</h3>
            </div>
            <p class="document-description">
                Required for various transactions and applications.
            </p>
            <p class="document-info">Processing Time: 1-2 business days</p>
            <a href="new-barangay-clearance.php" class="action-button" style="margin-top:10px;">Request</a>
        </div>
        <!-- Certificate of Indigency -->
        <div class="document-card green">
            <div class="document-icon">
                <i class="fas fa-hand-holding-usd"></i>
                <h3 class="document-title">Certificate of Indigency</h3>
            </div>
            <p class="document-description">
                Proof of low income for social services and assistance.
            </p>
            <p class="document-info">Processing Time: 1 business day</p>
            <a href="new-certificate-of-indigency.php" class="action-button" style="margin-top:10px;">Request</a>
        </div>
        <!-- Certificate of Residency -->
        <div class="document-card purple">
            <div class="document-icon">
                <i class="fas fa-home"></i>
                <h3 class="document-title">Certificate of Residency</h3>
            </div>
            <p class="document-description">
                Proof of residence in the barangay.
            </p>
            <p class="document-info">Processing Time: 1 business day</p>
            <a href="new-certificate-of-residency.php" class="action-button" style="margin-top:10px;">Request</a>
        </div>
        <!-- Barangay Business Clearance -->
        <div class="document-card" style="background-color: #f0fdfa; border-left: 5px solid #14b8a6;">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <i class="fas fa-store" style="color: #14b8a6; font-size: 1.5rem; margin-right: 10px;"></i>
                <h3 class="document-title" style="margin: 0;">Barangay Business Clearance</h3>
            </div>
            <p class="document-description">
                Required for operating businesses in the barangay.
            </p>
            <p class="document-info">Processing Time: 3-5 business days</p>
            <a href="new-barangay-business-clearance.php" class="action-button" style="margin-top:10px;">Request</a>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?> 