<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/env_loader.php';

// This is already in header.php, but for safety.
if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}

require_role('resident');

$page_title = "Report an Incident";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

require_once 'partials/header.php';
?>

<div class="report-incident-container">
    <div class="report-form-container">
        <h2><i class="fas fa-exclamation-triangle"></i>Report a New Incident</h2>
        <p class="text-secondary" style="margin-top: -20px; margin-bottom: 25px;">Please provide as much detail as possible. Use the map to pinpoint the exact location.</p>
        
        <form id="incident-form" enctype="multipart/form-data">
            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude" name="longitude">

            <div class="form-group">
                <label for="incident-type">Nature of Report</label>
                <select id="incident-type" name="type" required>
                    <option value="" disabled selected>Select the type of incident...</option>
                    <option value="Traffic Accident">Traffic Accident</option>
                    <option value="Noise Complaint">Noise Complaint</option>
                    <option value="Waste Management Issue">Waste Management Issue</option>
                    <option value="Public Disturbance">Public Disturbance</option>
                    <option value="Vandalism">Vandalism</option>
                    <option value="Suspicious Activity">Suspicious Activity</option>
                    <option value="Utility Issue (Water/Power)">Utility Issue (Water/Power)</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Detailed Description</label>
                <textarea id="description" name="description" placeholder="Describe the incident clearly. Include details like time, people involved, etc." required></textarea>
            </div>

            <div class="form-group">
                <label for="media">Attach Media (Photo/Video)</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="media" name="media" accept="image/*,video/mp4,video/quicktime">
                    <div class="file-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div class="file-upload-text">Click to upload or drag and drop</div>
                    <div class="file-upload-hint">Max file size: 10MB. Allowed types: JPG, PNG, MP4.</div>
                </div>
                <div id="file-name" class="mt-2"></div>
            </div>
            
            <div id="form-message"></div>

            <div class="form-actions">
                <button type="submit" class="submit-btn" id="submit-button">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
            </div>
        </form>
    </div>
    <div id="map" style="height: 550px; width: 100%; border: 2px solid #ccc; background-color: #f0f0f0;">
        <div style="padding: 20px; text-align: center; color: #666;">
            <i class="fas fa-map" style="font-size: 2rem; margin-bottom: 10px;"></i><br>
            Loading map... Please wait.
        </div>
    </div>
</div>

<script>
let map;
let marker;

// Lazy-loading intersection observer function
function initLazyMap() {
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;

    mapDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #666;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px;"></i><br>Loading Google Maps...</div>';

    // Inject Google Maps script dynamically
    const script = document.createElement('script');
    const apiKey = "<?php echo function_exists('env') ? env('GOOGLE_MAPS_API_KEY', 'AIzaSyBTRUvbV-rro8a-R7E86SEf5TRzBcBSpIE') : 'AIzaSyBTRUvbV-rro8a-R7E86SEf5TRzBcBSpIE'; ?>";
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&loading=async&callback=initGoogleMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

// Global callback for Google Maps API
window.initGoogleMap = async function() {
    console.log('Google Maps API Loaded.');
    const { Map } = await google.maps.importLibrary("maps");
    const { Marker } = await google.maps.importLibrary("marker");

    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;
    mapDiv.innerHTML = ''; // Clear loading text

    // Set map to default coordinates (e.g., Iloilo coordinates)
    const initialLocation = { lat: 10.7104, lng: 122.5118 };

    map = new Map(mapDiv, {
        center: initialLocation,
        zoom: 15,
        mapTypeId: 'satellite', // Changed to Satellite view as requested
        mapTypeControl: true,
        streetViewControl: false,
    });

    // Form inputs
    const latInput = document.getElementById('latitude');
    const lonInput = document.getElementById('longitude');

    // Add click listener
    map.addListener('click', (e) => {
        const clickedLocation = e.latLng;
        latInput.value = clickedLocation.lat();
        lonInput.value = clickedLocation.lng();

        if (marker) {
            marker.setPosition(clickedLocation);
        } else {
            marker = new Marker({
                position: clickedLocation,
                map: map,
                draggable: true,
                title: "Incident Location"
            });

            // Update on drag
            marker.addListener('dragend', () => {
                const pos = marker.getPosition();
                latInput.value = pos.lat();
                lonInput.value = pos.lng();
            });
            
            const infoWindow = new google.maps.InfoWindow({
                content: "<b>Location of Incident</b><br>You can drag this marker."
            });
            infoWindow.open(map, marker);
        }
    });
};

// Application logic (Form and Upload listeners)
function initFileInput() {
    const fileInput = document.getElementById('media');
    const fileNameDisplay = document.getElementById('file-name');
    
    fileInput.addEventListener('change', function() {
        if (fileInput.files.length > 0) {
            fileNameDisplay.textContent = `Selected file: ${fileInput.files[0].name}`;
        } else {
            fileNameDisplay.textContent = '';
        }
    });
}

function initForm() {
    const form = document.getElementById('incident-form');
    const messageDiv = document.getElementById('form-message');
    const submitButton = document.getElementById('submit-button');
    const latInput = document.getElementById('latitude');
    const lonInput = document.getElementById('longitude');
    const fileNameDisplay = document.getElementById('file-name');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!latInput.value || !lonInput.value) {
            showMessage('error', 'Please select a location on the map by clicking on it.');
            return;
        }
        
        const formData = new FormData(form);
        formData.append('action', 'report_incident');
        
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        showMessage('submitting', 'Submitting your report, please wait...');
        
        fetch('../api/incidents.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                form.reset();
                fileNameDisplay.textContent = '';
                setTimeout(() => {
                    window.location.href = 'my-reports.php';
                }, 2000);
            } else {
                showMessage('error', 'Error: ' + data.error);
            }
        })
        .catch(error => {
            showMessage('error', 'An unexpected network error occurred. Please try again.');
            console.error('Error:', error);
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
        });
    });
    
    function showMessage(type, text) {
        messageDiv.textContent = text;
        messageDiv.className = type;
        messageDiv.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initFileInput();
    initForm();
    
    // Lazy Load Observer setup
    const mapDiv = document.getElementById('map');
    if (mapDiv && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    initLazyMap();
                    observer.unobserve(mapDiv); // Only load once
                }
            });
        }, { threshold: 0.1 });
        observer.observe(mapDiv);
    } else {
        // Fallback for browsers without IntersectionObserver
        initLazyMap();
    }
});
</script>

<?php require_once 'partials/footer.php'; ?> 