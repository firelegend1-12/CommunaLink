<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        redirect_to('../index.php');
    }
}

require_role('resident');

$page_title = "My Incident Reports";
$user_fullname = $_SESSION['fullname'] ?? 'Resident';

require_once 'partials/header.php';
?>
<style>
    .table-container {
        padding: 30px;
        background-color: var(--card-bg);
        max-width: 1200px;
        margin: 20px auto;
        border-radius: 12px;
        box-shadow: 0 4px 12px var(--shadow-color);
    }
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    th {
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        color: var(--text-secondary);
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-badge.status-pending {
        background-color: #fffbe6;
        color: #f7b924;
    }
    .status-badge.status-in-progress { background-color: #cce7ff; color: #006de0; }
    .status-badge.status-resolved { background-color: #d4edda; color: #155724; }
    .status-badge.status-rejected { background-color: #f8d7da; color: #721c24; }
    .submit-btn {
        background: linear-gradient(135deg, var(--accent-blue), var(--accent-blue-dark));
        color: var(--text-light);
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(92, 103, 226, 0.4);
        transition: all 0.3s ease;
        text-decoration: none;
    }
    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(92, 103, 226, 0.6);
    }
</style>
<div class="table-container">
    <div class="table-header">
        <h2>My Submitted Reports</h2>
        <a href="report-incident.php" class="submit-btn">Report New Incident</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Report ID</th>
                <th>Type</th>
                <th>Location</th>
                <th>Date Reported</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="reports-tbody">
            <!-- Data will be populated by JavaScript -->
            <tr>
                <td colspan="5" style="text-align: center; padding: 30px;">Loading reports...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('../api/incidents.php?action=get_my_reports')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('reports-tbody');
            tbody.innerHTML = ''; // Clear loading state

            if (data.success && data.reports.length > 0) {
                data.reports.forEach(report => {
                    const tr = document.createElement('tr');
                    
                    const formattedDate = new Date(report.reported_at).toLocaleString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
                    });

                    const statusClass = 'status-' + report.status.toLowerCase().replace(' ', '-');

                    tr.innerHTML = `
                        <td>#${report.id}</td>
                        <td>${escapeHTML(report.type)}</td>
                        <td>${(report.latitude && report.longitude)
                          ? `<a href="https://www.google.com/maps?q=${report.latitude},${report.longitude}"
                                target="_blank"
                                style="
                                  display: inline-block;
                                  background: #e6f9ec;
                                  color: #179c4c;
                                  font-weight: 600;
                                  padding: 4px 12px;
                                  border-radius: 16px;
                                  text-decoration: none;
                                  font-family: monospace;
                                  transition: background 0.2s, color 0.2s;"
                                onmouseover="this.style.background='#c3f2d6';this.style.color='#0b6b2b';"
                                onmouseout="this.style.background='#e6f9ec';this.style.color='#179c4c';"
                            >${report.latitude}, ${report.longitude}</a>`
                          : escapeHTML(report.location)}
                        </td>
                        <td>${formattedDate}</td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                ${escapeHTML(report.status)}
                            </span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else if (data.success) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 30px;">You have not submitted any reports yet.</td></tr>';
            } else {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px;">Error: ${data.error}</td></tr>`;
            }
        })
        .catch(error => {
            const tbody = document.getElementById('reports-tbody');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 30px;">An unexpected error occurred while fetching data.</td></tr>';
            console.error('Error:', error);
        });
});

function escapeHTML(str) {
    return str.replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}
</script>
<?php require_once 'partials/footer.php'; ?> 