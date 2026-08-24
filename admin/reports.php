<?php
/**
 * Reports Page
 *
 * Aggregated donor, eligibility, and messaging reports.
 */

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form id="reportFilterForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" class="form-control" id="startDate" name="start_date">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" class="form-control" id="endDate" name="end_date">
            </div>
            <div class="col-md-3">
                <label class="form-label">Blood Group</label>
                <select class="form-select" id="bloodGroup" name="blood_group">
                    <option value="">All Blood Groups</option>
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $group): ?>
                        <option value="<?= sanitize($group) ?>"><?= sanitize($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-filter me-1"></i> Apply
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnResetReports">
                    <i class="fas fa-rotate-left"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-total">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-value" id="totalDonors">0</div>
            <div class="stat-label">Total Donors</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-eligible">
            <i class="fas fa-heartbeat stat-icon"></i>
            <div class="stat-value" id="eligibleDonors">0</div>
            <div class="stat-label">Eligible Donors</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-messages">
            <i class="fas fa-paper-plane stat-icon"></i>
            <div class="stat-value" id="messagesSent">0</div>
            <div class="stat-label">Messages Sent</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-b-pos">
            <i class="fas fa-campground stat-icon"></i>
            <div class="stat-value" id="upcomingCamps">0</div>
            <div class="stat-label">Upcoming Camps</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-column me-2 text-primary"></i>Blood Group Distribution</span>
                <a class="btn btn-sm btn-outline-primary report-export" data-report="blood_groups" href="#">
                    <i class="fas fa-download me-1"></i> Export
                </a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="bloodGroupReportChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line me-2 text-primary"></i>Messages Over Time</span>
                <a class="btn btn-sm btn-outline-primary report-export" data-report="messages" href="#">
                    <i class="fas fa-download me-1"></i> Export
                </a>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="messageTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-check me-2 text-success"></i>Eligible Donors</span>
                <a class="btn btn-sm btn-outline-primary report-export" data-report="eligible" href="#">
                    <i class="fas fa-download me-1"></i> Export
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Blood</th>
                                <th>Last Donation</th>
                            </tr>
                        </thead>
                        <tbody id="eligibleRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Recently Contacted</span>
                <a class="btn btn-sm btn-outline-primary report-export" data-report="summary" href="#">
                    <i class="fas fa-download me-1"></i> Summary
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Donor</th>
                                <th>Channel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recentMessageRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reportFilterForm');
    const resetBtn = document.getElementById('btnResetReports');
    let bloodChart = null;
    let trendChart = null;

    function filters() {
        return new URLSearchParams({
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value,
            blood_group: document.getElementById('bloodGroup').value
        });
    }

    function text(value) {
        return String(value ?? '');
    }

    function escapeHtml(value) {
        return text(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function badgeStatus(status) {
        const label = escapeHtml(status);
        const cls = text(status).toLowerCase().replace(/[^a-z0-9_-]/g, '');
        return '<span class="badge-status ' + cls + '">' + label + '</span>';
    }

    function setExportLinks() {
        document.querySelectorAll('.report-export').forEach(function (link) {
            const params = filters();
            params.set('report', link.dataset.report);
            params.set('format', 'xlsx');
            link.href = '<?= BASE_URL ?>/ajax/report-export.php?' + params.toString();
        });
    }

    function renderEmpty(colspan, label) {
        return '<tr><td colspan="' + colspan + '" class="text-center text-muted py-4">' + label + '</td></tr>';
    }

    function renderCharts(data) {
        const bloodLabels = data.blood_groups.map(item => item.blood_group);
        const bloodValues = data.blood_groups.map(item => Number(item.total));
        const trendLabels = data.message_trend.map(item => item.report_date);
        const trendValues = data.message_trend.map(item => Number(item.total));

        if (bloodChart) {
            bloodChart.destroy();
        }
        bloodChart = new Chart(document.getElementById('bloodGroupReportChart'), {
            type: 'bar',
            data: {
                labels: bloodLabels,
                datasets: [{
                    label: 'Active Donors',
                    data: bloodValues,
                    backgroundColor: ['#ef4444', '#dc2626', '#3b82f6', '#2563eb', '#a855f7', '#9333ea', '#22c55e', '#16a34a'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        if (trendChart) {
            trendChart.destroy();
        }
        trendChart = new Chart(document.getElementById('messageTrendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Messages',
                    data: trendValues,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    function renderTables(data) {
        const eligibleRows = document.getElementById('eligibleRows');
        const messageRows = document.getElementById('recentMessageRows');

        eligibleRows.innerHTML = data.eligible_donors.length
            ? data.eligible_donors.map(function (donor) {
                return `<tr>
                    <td>${escapeHtml(donor.donor_name)}</td>
                    <td>${escapeHtml(donor.mobile)}</td>
                    <td>${donor.blood_group
                        ? `<span class="badge-blood" data-blood="${escapeHtml(donor.blood_group)}">${escapeHtml(donor.blood_group)}</span>`
                        : '<span class="text-secondary">&mdash;</span>'}</td>
                    <td>${escapeHtml(donor.last_donation_date || 'Never')}</td>
                </tr>`;
            }).join('')
            : renderEmpty(4, 'No eligible donors found.');

        messageRows.innerHTML = data.recent_messages.length
            ? data.recent_messages.map(function (row) {
                return `<tr>
                    <td>${escapeHtml(row.sent_at)}</td>
                    <td>${escapeHtml(row.donor_name)}</td>
                    <td>${escapeHtml(row.message_type)}</td>
                    <td>${badgeStatus(row.status)}</td>
                </tr>`;
            }).join('')
            : renderEmpty(4, 'No message history found.');
    }

    function loadReports() {
        const params = filters();
        params.set('csrf_token', window.CSRF_TOKEN || '');

        fetch('<?= BASE_URL ?>/ajax/report-data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
            },
            body: params.toString()
        })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    showToast(result.message || 'Unable to load reports.', 'error');
                    return;
                }

                const data = result.data;
                document.getElementById('totalDonors').textContent = data.summary.total_donors;
                document.getElementById('eligibleDonors').textContent = data.summary.eligible_donors;
                document.getElementById('messagesSent').textContent = data.summary.messages_sent;
                document.getElementById('upcomingCamps').textContent = data.summary.upcoming_camps;
                renderCharts(data);
                renderTables(data);
                setExportLinks();
            })
            .catch(() => showToast('Unable to load reports.', 'error'));
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadReports();
    });

    resetBtn.addEventListener('click', function () {
        form.reset();
        loadReports();
    });

    loadReports();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
