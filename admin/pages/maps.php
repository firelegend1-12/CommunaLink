<?php
require_once '../partials/admin_auth.php';

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
    <title>Barangay Pakiad</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <?php
include '../partials/sidebar.php'; ?>
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
                            <div class="flex items-center gap-2"><img src="https://maps.google.com/mapfiles/ms/icons/red-dot.png" class="w-4 h-6" alt="Barangay Center" style="object-fit:contain;"> Barangay Center</div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    let map;
    let markers = [];
    let clusterer = null;
    let heatmap = null;
    let lastOpenIncidentId = null;
    let lastIncidentDataHash = '';
    let infoWindow;
    const BARANGAY_HALL = { lat: 10.710781570266882, lng: 122.51720404103982 };
    let mapUnavailableShown = false;

    function showMapUnavailable(message) {
        const mapDiv = document.getElementById('incident-map');
        if (!mapDiv || mapUnavailableShown) {
            return;
        }

        mapUnavailableShown = true;
        mapDiv.innerHTML = `
            <div class="flex items-center justify-center h-full min-h-[500px] bg-gray-100 rounded-lg text-center px-6">
                <div>
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 text-2xl">!</div>
                    <div class="text-lg font-semibold text-gray-800">Map unavailable</div>
                    <div class="mt-2 text-sm text-gray-600">${message}</div>
                </div>
            </div>
        `;
    }

    function initMap() {
        map = new google.maps.Map(document.getElementById('incident-map'), {
            center: BARANGAY_HALL,
            zoom: 14,
            mapTypeId: 'roadmap',
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true
        });

        new google.maps.Marker({
            position: BARANGAY_HALL,
            map: map,
            title: 'Barangay Hall',
            icon: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
        });

        infoWindow = new google.maps.InfoWindow();

        fetchAndUpdateReports();
        setInterval(fetchAndUpdateReports, 30000);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(character) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[character];
        });
    }

    function formatPopupContent(report, lat, lng) {
        const locationText = lat && lng
            ? `GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}`
            : (report.location || 'Location unavailable');

        return `
            <div class="text-sm leading-relaxed">
                <div class="font-semibold text-gray-900">${escapeHtml(report.type || 'Incident Report')}</div>
                <div><strong>By:</strong> ${escapeHtml(report.resident_name || 'Unknown')}</div>
                <div>${escapeHtml(locationText)}</div>
                <div>${escapeHtml(report.description || '')}</div>
                <div class="text-xs text-gray-500 mt-1">Reported: ${escapeHtml(report.reported_at || '')}</div>
            </div>
        `;
    }

    function clearMarkers() {
        if (clusterer) {
            clusterer.clearMarkers();
            clusterer = null;
        }
        if (heatmap) {
            heatmap.setMap(null);
            heatmap = null;
        }
        markers.forEach(marker => marker.setMap(null));
        markers = [];
    }

    function addMarkers(reports) {
        clearMarkers();
        const heatPoints = [];
        let foundOpen = false;
        
        reports.forEach(report => {
            if (report.latitude && report.longitude) {
                const lat = parseFloat(report.latitude);
                const lng = parseFloat(report.longitude);
                if (isNaN(lat) || isNaN(lng)) return;

                const point = { lat, lng };
                heatPoints.push(new google.maps.LatLng(lat, lng));

                const marker = new google.maps.Marker({
                    position: point,
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: '#2563eb',
                        fillOpacity: 0.9,
                        strokeWeight: 2,
                        strokeColor: '#1d4ed8',
                        scale: 7
                    }
                });
                const popupContent = formatPopupContent(report, lat, lng);

                marker.addListener('click', () => {
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

        if (markers.length > 0) {
            clusterer = new markerClusterer.MarkerClusterer({ map, markers });
        }

        if (heatPoints.length > 0) {
            heatmap = new google.maps.visualization.HeatmapLayer({
                data: heatPoints,
                radius: 35,
                opacity: 0.75,
                dissipating: true
            });
            heatmap.setMap(map);
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
        if (map) {
            map.panTo(BARANGAY_HALL);
            map.setZoom(17);
        }
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
            })
            .catch(() => {
                showMapUnavailable('Unable to load incident coordinates right now. Please try refreshing the page.');
            });
    }
    </script>
    <?php
$apiKey = function_exists('maps_api_key') ? (string) maps_api_key('') : '';
    ?>
    <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
    <?php
if ($apiKey !== ''): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php
echo urlencode($apiKey); ?>&libraries=visualization&callback=initMap" async defer onerror="showMapUnavailable('Google Maps failed to load. Please verify the API key and network access.');"></script>
    <script>
        window.gm_authFailure = function() {
            showMapUnavailable('Google Maps rejected the current API key. Incident detail embeds work without a key, but the heat map page needs the full JavaScript Maps API.');
        };
    </script>
    <?php
else: ?>
    <script>
        console.error('Google Maps API key is missing. Set GOOGLE_MAPS_API_KEY in environment configuration.');
        document.addEventListener('DOMContentLoaded', function() {
            showMapUnavailable('Google Maps API key is missing. Incident detail embeds can still render, but the heat map page needs the JavaScript Maps API key.');
        });
    </script>
    <?php
endif; ?>
</body>
</html> 


