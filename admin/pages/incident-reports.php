<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$page_title = "Incident Reports Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .status-pending { background-color: #FFFBEB; color: #F59E0B; }
        .status-in-progress { background-color: #EFF6FF; color: #3B82F6; }
        .status-resolved { background-color: #F0FDF4; color: #16A34A; }
        .status-rejected { background-color: #FEF2F2; color: #EF4444; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div id="incident-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                    echo '<p>' . htmlspecialchars($_SESSION['success_message']) . '</p>';
                    echo '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo display_error($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                }
                ?>
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="reports-tbody" class="bg-white divide-y divide-gray-200">
                                    <!-- Dynamic content will be loaded here -->
                                    <tr><td colspan="7" class="text-center py-4">Loading reports...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('incident-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const tbody = document.getElementById('reports-tbody');

        function escapeHTML(str) {
            return str ? str.replace(/[&<>"]'/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[match])) : '';
        }

        let incidentPollingInterval = null;
        function startIncidentPolling() {
            if (incidentPollingInterval) clearInterval(incidentPollingInterval);
            incidentPollingInterval = setInterval(() => {
                if (!document.querySelector('.dropdown-open')) fetchReports();
            }, 2000);
        }

        async function fetchReports() {
            try {
                const response = await fetch('../../admin/partials/fetch-live-incidents.php');
                const data = await response.json();
                tbody.innerHTML = '';
                if (data.incidents && data.incidents.length > 0) {
                    data.incidents.forEach(report => {
                        const tr = document.createElement('tr');
                        const statusClass = 'status-' + report.status.toLowerCase().replace(' ', '-');
                        let locationDisplay = report.location;
                        if (report.latitude && report.longitude) {
                            locationDisplay = `<div class="text-xs">
                                <div class="font-medium">GPS Location:</div>
                                <div class="text-gray-400">${report.latitude}, ${report.longitude}</div>
                                <a href="https://www.google.com/maps?q=${report.latitude},${report.longitude}" 
                                   target="_blank" class="text-blue-500 hover:text-blue-700 text-xs">
                                   <i class="fas fa-map-marker-alt"></i> View on Map
                                </a>
                            </div>`;
                        }
                        tr.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#${report.id}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${escapeHTML(report.resident_name || '')}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${escapeHTML(report.type)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${locationDisplay}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(report.reported_at).toLocaleDateString()}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="status-badge ${statusClass}">${escapeHTML(report.status)}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative">
                              <div class="relative inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" x-effect="if(open){ window.clearInterval(window.incidentPollingInterval); $el.classList.add('dropdown-open'); } else { window.startIncidentPolling && window.startIncidentPolling(); $el.classList.remove('dropdown-open'); }">
                                <button type="button" x-ref="dropdownBtn" @click="
                                    open = !open;
                                    if (open) {
                                        const rect = $refs.dropdownBtn.getBoundingClientRect();
                                        top = rect.bottom + window.scrollY;
                                        left = rect.left + window.scrollX;
                                    }
                                " class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-200 focus:outline-none" aria-haspopup="true" aria-expanded="false">
                                  <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <circle cx="4" cy="10" r="1.5"/>
                                    <circle cx="10" cy="10" r="1.5"/>
                                    <circle cx="16" cy="10" r="1.5"/>
                                  </svg>
                                </button>
                                <template x-teleport="body">
                                  <div x-show="open" @click.away="open = false" x-cloak
                                       class="fixed z-50 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                       :style="'top: ' + top + 'px; left: ' + left + 'px;'">
                                    <div class="py-1">
                                      <a href="update-incident.php?id=${report.id}" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Update</a>
                                    </div>
                                  </div>
                                </template>
                              </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No incident reports found.</td></tr>';
                }
            } catch (error) {
                console.error('Error fetching reports:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Failed to load reports.</td></tr>';
            }
            // Dropdown JS for action menu (must be re-attached after each table update)
            tbody.querySelectorAll('.dropdown-btn').forEach(btn => {
              btn.onclick = function(e) {
                e.stopPropagation();
                // Close all other dropdowns
                tbody.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
                // Open this one
                btn.nextElementSibling.classList.toggle('hidden');
              };
            });
            // Only add the document click listener once
            if (!window.__incidentDropdownListenerAdded) {
              document.addEventListener('click', () => {
                tbody.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
              });
              window.__incidentDropdownListenerAdded = true;
            }
        }
        window.startIncidentPolling = startIncidentPolling;
        fetchReports();
        startIncidentPolling();
    });
    </script>
</body>
</html> 