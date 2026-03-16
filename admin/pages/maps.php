<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header('Location: ../../index.php');
    exit;
}

include_once '../../config/database.php';
include_once '../../includes/functions.php';

$title = "Maps";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Barangay Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        #incident-map { height: 500px; width: 100%; border-radius: 0.75rem; }
        @media (max-width: 640px) { #incident-map { height: 300px; } }
        .legend {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            position: absolute;
            bottom: 1.5rem;
            left: 1.5rem;
            z-index: 1000;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800">Maps</h1>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center">
                        <div class="flex items-center gap-2 flex-wrap">
                            <label for="filter-range" class="font-medium text-gray-700">Show:</label>
                            <select id="filter-range" class="bg-gray-100 text-gray-500 px-4 py-2 rounded-lg border border-transparent focus:ring-2 focus:ring-blue-400 focus:outline-none">
                                <option value="overall">Overall</option>
                                <option value="today">Today</option>
                                <option value="week">Week</option>
                                <option value="month">Month</option>
                                <option value="year">Year</option>
                            </select>
                            <label for="date-from" class="ml-4 font-medium text-gray-700">From:</label>
                            <input type="date" id="date-from" class="bg-gray-100 text-gray-500 px-4 py-2 rounded-lg border border-transparent focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                            <label for="date-to" class="ml-2 font-medium text-gray-700">To:</label>
                            <input type="date" id="date-to" class="bg-gray-100 text-gray-500 px-4 py-2 rounded-lg border border-transparent focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                            <span id="report-count" class="ml-4 bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold"></span>
                        </div>
                        <button id="center-barangay" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm flex items-center transition duration-300 mt-2 sm:mt-0"><i class="fas fa-crosshairs mr-2"></i> Center Barangay</button>
                    </div>
                    <div class="p-6 relative">
                        <div id="incident-map" class="border border-gray-200 rounded-lg"></div>
                        <div class="legend">
                            <div class="flex items-center gap-2 mb-1"><span class="inline-block w-4 h-4 rounded-full bg-blue-600"></span> Incident Report</div>
                            <div class="flex items-center gap-2"><img src="https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png" class="w-4 h-6" alt="Barangay Center"> Barangay Center</div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const map = L.map('incident-map').setView([10.710827350642523, 122.51720118954563], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Add a default marker for the barangay center
    L.marker([10.710827350642523, 122.51720118954563]).addTo(map)
        .bindPopup('<b>Barangay Center</b>');

    let allReports = [];
    let markers = [];
    let lastOpenIncidentId = null;
    let lastOpenLatLng = null;
    let lastIncidentDataHash = '';

    function clearMarkers() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];
    }

    function addMarkers(reports) {
        // Find if a popup is open and record its incident ID and latlng
        let openPopupIncidentId = null;
        let openPopupLatLng = null;
        if (map._popup && map._popup._isOpen) {
            // Try to find the marker with an open popup
            markers.forEach(m => {
                if (m.getPopup() && m.getPopup().isOpen()) {
                    openPopupIncidentId = m.incidentId;
                    openPopupLatLng = m.getLatLng();
                }
            });
        }
        clearMarkers();
        let foundOpen = false;
        reports.forEach(report => {
            if (report.latitude && report.longitude) {
                const lat = parseFloat(report.latitude);
                const lng = parseFloat(report.longitude);
                if (isNaN(lat) || isNaN(lng)) return;
                const marker = L.circleMarker([lat, lng], {
                    radius: 10,
                    color: '#2563eb',
                    fillColor: '#2563eb',
                    fillOpacity: 0.85,
                    weight: 2
                }).addTo(map);
                let locationText = report.location;
                if (lat && lng) {
                    locationText = `GPS: ${lat}, ${lng}`;
                }
                const popupContent =
                    `<b>${report.type}</b><br>
                     <b>By:</b> ${report.resident_name || 'Unknown'}<br>
                     ${locationText}<br>
                     ${report.description}<br>
                     <small>Reported: ${report.reported_at}</small>`;
                marker.bindPopup(popupContent);
                marker.incidentId = report.id;
                marker.on('popupopen', function() {
                    lastOpenIncidentId = report.id;
                    lastOpenLatLng = marker.getLatLng();
                });
                marker.on('popupclose', function() {
                    if (lastOpenIncidentId === report.id) {
                        lastOpenIncidentId = null;
                        lastOpenLatLng = null;
                    }
                });
                // If this marker's popup should be open, open it
                if (
                    (openPopupIncidentId && openPopupIncidentId === report.id && openPopupLatLng && marker.getLatLng().equals(openPopupLatLng)) ||
                    (lastOpenIncidentId === report.id && lastOpenLatLng && marker.getLatLng().equals(lastOpenLatLng))
                ) {
                    marker.openPopup();
                    foundOpen = true;
                }
                markers.push(marker);
            }
        });
        // If the previously open marker is gone, clear the state
        if (!foundOpen) {
            lastOpenIncidentId = null;
            lastOpenLatLng = null;
        }
    }

    function updateReportCount(count) {
        document.getElementById('report-count').textContent = `Showing ${count} report${count === 1 ? '' : 's'}`;
    }

    function filterReports(range) {
        let filtered = [];
        const now = new Date();
        const fromDate = document.getElementById('date-from').value ? new Date(document.getElementById('date-from').value) : null;
        const toDate = document.getElementById('date-to').value ? new Date(document.getElementById('date-to').value) : null;
        allReports.forEach(report => {
            if (!report.reported_at) return;
            const reported = new Date(report.reported_at);
            let match = false;
            if (fromDate && reported < fromDate) return;
            if (toDate) {
                // Add 1 day to include the end date
                let toDatePlus = new Date(toDate);
                toDatePlus.setDate(toDatePlus.getDate() + 1);
                if (reported >= toDatePlus) return;
            }
            if (range === 'overall' && !fromDate && !toDate) {
                match = true;
            } else if (range === 'today') {
                match = reported.toDateString() === now.toDateString();
            } else if (range === 'week') {
                const weekAgo = new Date(now);
                weekAgo.setDate(now.getDate() - 7);
                match = reported >= weekAgo && reported <= now;
            } else if (range === 'month') {
                const monthAgo = new Date(now);
                monthAgo.setMonth(now.getMonth() - 1);
                match = reported >= monthAgo && reported <= now;
            } else if (range === 'year') {
                const yearAgo = new Date(now);
                yearAgo.setFullYear(now.getFullYear() - 1);
                match = reported >= yearAgo && reported <= now;
            } else if (fromDate || toDate) {
                match = true;
            }
            if (match) filtered.push(report);
        });
        addMarkers(filtered);
        updateReportCount(filtered.length);
    }

    document.getElementById('filter-range').onchange = (e) => filterReports(e.target.value);
    document.getElementById('date-from').onchange = () => filterReports(document.getElementById('filter-range').value);
    document.getElementById('date-to').onchange = () => filterReports(document.getElementById('filter-range').value);
    document.getElementById('center-barangay').onclick = () => {
        map.setView([10.710827350642523, 122.51720118954563], 17);
    };

    function fetchAndUpdateReports() {
        fetch('../../admin/partials/fetch-live-incidents.php')
            .then(res => res.json())
            .then(data => {
                if (data.incidents) {
                    // Compare new data to previous
                    const newHash = JSON.stringify(data.incidents.map(i => ({id:i.id,lat:i.latitude,lng:i.longitude,updated:i.reported_at,status:i.status})));
                    if (newHash !== lastIncidentDataHash) {
                        allReports = data.incidents;
                        filterReports(document.getElementById('filter-range').value);
                        lastIncidentDataHash = newHash;
                    }
                }
            });
    }
    fetchAndUpdateReports();
    setInterval(fetchAndUpdateReports, 1000);
    </script>
</body>
</html> 