<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header('Location: ../../index.php');
    exit;
}

include_once '../../config/database.php';
include_once '../../includes/functions.php';
include_once '../../config/env_loader.php';

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
                            <div class="flex items-center gap-2"><img src="http://maps.google.com/mapfiles/ms/icons/red-dot.png" class="w-4 h-6" alt="Barangay Center" style="object-fit:contain;"> Barangay Center</div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    let map;
    let markers = [];
    let lastOpenIncidentId = null;
    let lastIncidentDataHash = '';
    let infoWindow;

    let clusterer = null;

    function initMap() {
        const center = { lat: 10.710827350642523, lng: 122.51720118954563 };
        map = new google.maps.Map(document.getElementById('incident-map'), {
            center: center,
            zoom: 14,
            mapTypeId: 'hybrid'
        });

        // Add a default marker for the barangay center
        new google.maps.Marker({
            position: center,
            map: map,
            title: 'Barangay Center'
        });

        infoWindow = new google.maps.InfoWindow();

        fetchAndUpdateReports();
        setInterval(fetchAndUpdateReports, 30000);
    }

    function clearMarkers() {
        if (clusterer) {
            clusterer.clearMarkers();
        } else {
            markers.forEach(m => m.setMap(null));
        }
        markers = [];
    }

    function addMarkers(reports) {
        clearMarkers();
        let foundOpen = false;
        
        reports.forEach(report => {
            if (report.latitude && report.longitude) {
                const lat = parseFloat(report.latitude);
                const lng = parseFloat(report.longitude);
                if (isNaN(lat) || isNaN(lng)) return;
                
                const pos = { lat, lng };

                const marker = new google.maps.Marker({
                    position: pos,
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: '#2563eb',
                        fillOpacity: 0.85,
                        strokeWeight: 2,
                        strokeColor: '#2563eb',
                        scale: 8
                    }
                });

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

                marker.addListener("click", () => {
                    infoWindow.setContent(popupContent);
                    infoWindow.open(map, marker);
                    lastOpenIncidentId = report.id;
                });

                if (lastOpenIncidentId === report.id) {
                    infoWindow.setContent(popupContent);
                    infoWindow.open(map, marker);
                    foundOpen = true;
                }
                markers.push(marker);
            }
        });

        if (!foundOpen) {
            lastOpenIncidentId = null;
        }

        // Apply Clustering!
        if (clusterer) {
            clusterer.clearMarkers();
            clusterer.addMarkers(markers);
        } else {
            clusterer = new markerClusterer.MarkerClusterer({ map, markers });
        }
    }

    function updateReportCount(count) {
        document.getElementById('report-count').textContent = `Showing ${count} report${count === 1 ? '' : 's'}`;
    }

    let allReports = [];
    function filterReports(range) {
        let filtered = [];
        const now = new Date();
        const fromDateDom = document.getElementById('date-from').value;
        const toDateDom = document.getElementById('date-to').value;
        const fromDate = fromDateDom ? new Date(fromDateDom) : null;
        const toDate = toDateDom ? new Date(toDateDom) : null;

        allReports.forEach(report => {
            if (!report.reported_at) return;
            const reported = new Date(report.reported_at);
            let match = false;
            
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
            }

            if (fromDate && reported < fromDate) match = false;
            if (toDate) {
                let toDatePlus = new Date(toDate);
                toDatePlus.setDate(toDatePlus.getDate() + 1);
                if (reported >= toDatePlus) match = false;
            }
            if ((fromDate || toDate) && range === 'overall') {
                match = true;
                if (fromDate && reported < fromDate) match = false;
                if (toDate && reported >= new Date(new Date(toDate).setDate(new Date(toDate).getDate() + 1))) match = false;
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
        map.setCenter({ lat: 10.710827350642523, lng: 122.51720118954563 });
        map.setZoom(17);
    };

    function fetchAndUpdateReports() {
        fetch('../../admin/partials/fetch-live-incidents.php')
            .then(res => res.json())
            .then(data => {
                if (data.incidents) {
                    const newHash = JSON.stringify(data.incidents.map(i => ({id:i.id,lat:i.latitude,lng:i.longitude,updated:i.reported_at,status:i.status})));
                    if (newHash !== lastIncidentDataHash) {
                        allReports = data.incidents;
                        filterReports(document.getElementById('filter-range').value);
                        lastIncidentDataHash = newHash;
                    }
                }
            });
    }
    </script>
    <?php
    $apiKey = function_exists('env') ? env('GOOGLE_MAPS_API_KEY', 'AIzaSyDSePOKkt_W5bY7YsYaEJrMoSRWxTMGnuI') : 'AIzaSyDSePOKkt_W5bY7YsYaEJrMoSRWxTMGnuI';
    ?>
    <!-- Marker Clusterer CDN -->
    <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $apiKey; ?>&callback=initMap" async defer></script>
</body>
</html> 