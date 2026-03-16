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
// Simple and reliable map initialization
function initializeMap() {
    console.log('Starting map initialization...');
    
    const mapDiv = document.getElementById('map');
    if (!mapDiv) {
        console.error('Map div not found!');
        return;
    }
    
    // Clear loading message
    mapDiv.innerHTML = '';
    
    // Check if Leaflet is available
    if (typeof L === 'undefined') {
        console.error('Leaflet not loaded!');
        mapDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: red;">Error: Map library not loaded. Please refresh the page.</div>';
        return;
    }
    
    try {
        // Remove previous map instance if it exists
        if (window._leaflet_map_instance) {
            window._leaflet_map_instance.remove();
            window._leaflet_map_instance = null;
        }
        window._leaflet_map_instance = L.map('map', {
            center: [10.7104, 122.5118],
            zoom: 15,
            zoomControl: true
        });
        const map = window._leaflet_map_instance;
        
        console.log('Map created successfully');
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        console.log('Tile layer added');
        
        // Get form elements
        const latInput = document.getElementById('latitude');
        const lonInput = document.getElementById('longitude');
        let marker = null;
        
        // Add click handler
        map.on('click', function(e) {
            console.log('Map clicked at:', e.latlng);
            const { lat, lng } = e.latlng;
            latInput.value = lat;
            lonInput.value = lng;
            
            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                marker = L.marker(e.latlng, {draggable: true}).addTo(map);
                marker.on('dragend', function(event) {
                    const position = marker.getLatLng();
                    latInput.value = position.lat;
                    lonInput.value = position.lng;
                });
            }
            marker.bindPopup("<b>Location of Incident</b><br>You can drag this marker.").openPopup();
        });
        
        console.log('Map initialization complete');
        
    } catch (error) {
        console.error('Map initialization failed:', error);
        mapDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: red;">Error: Failed to initialize map. Please refresh the page.</div>';
    }
}

// File input handling
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

// Form submission
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

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    
    // Initialize form and file input immediately
    initFileInput();
    initForm();
    
    // Try to initialize map immediately
    initializeMap();
    
    // If map didn't initialize, try again after delays
    setTimeout(function() {
        const mapDiv = document.getElementById('map');
        if (mapDiv && !mapDiv.querySelector('.leaflet-container')) {
            console.log('Retrying map initialization...');
            initializeMap();
        }
    }, 1000);
    
    setTimeout(function() {
        const mapDiv = document.getElementById('map');
        if (mapDiv && !mapDiv.querySelector('.leaflet-container')) {
            console.log('Final map initialization attempt...');
            initializeMap();
        }
    }, 3000);
});
</script>

<?php require_once 'partials/footer.php'; ?> 