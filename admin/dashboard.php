<?php
/**
 * Dashboard Page
 * 
 * Displays blood group stats, upcoming camp, eligible donors, 
 * messages sent today, and blood group distribution chart.
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Fetch data
$bloodCounts = getAllBloodGroupCounts();
$totalDonors = getTotalDonors();
$eligibleDonors = getEligibleDonorsCount();
$messagesToday = getMessagesSentToday();
$nextCamp = getNextUpcomingCamp();

// Recent message logs
$db = getDB();
$recentLogs = $db->query("SELECT ml.*, d.donor_name FROM message_logs ml LEFT JOIN donors d ON ml.donor_id = d.id ORDER BY ml.sent_at DESC LIMIT 5")->fetchAll();
?>

<!-- ── Stats Row ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Total Donors -->
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card bg-total">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-value" data-count="<?= $totalDonors ?>"><?= $totalDonors ?></div>
            <div class="stat-label">Total Donors</div>
        </div>
    </div>

    <?php
    $groups = [
        'A+' => 'bg-a-pos', 'A-' => 'bg-a-neg',
        'B+' => 'bg-b-pos', 'B-' => 'bg-b-neg',
        'AB+' => 'bg-ab-pos', 'AB-' => 'bg-ab-neg',
        'O+' => 'bg-o-pos', 'O-' => 'bg-o-neg'
    ];
    foreach ($groups as $group => $class):
    ?>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card <?= $class ?>">
            <i class="fas fa-tint stat-icon"></i>
            <div class="stat-value" data-count="<?= $bloodCounts[$group] ?? 0 ?>"><?= $bloodCounts[$group] ?? 0 ?></div>
            <div class="stat-label"><?= $group ?> Donors</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Widgets Row ────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Eligible Donors -->
    <div class="col-md-4">
        <div class="stat-card bg-eligible">
            <i class="fas fa-heartbeat stat-icon"></i>
            <div class="stat-value" data-count="<?= $eligibleDonors ?>"><?= $eligibleDonors ?></div>
            <div class="stat-label">Eligible Donors</div>
        </div>
    </div>
    <!-- Messages Sent Today -->
    <div class="col-md-4">
        <div class="stat-card bg-messages">
            <i class="fas fa-paper-plane stat-icon"></i>
            <div class="stat-value" data-count="<?= $messagesToday ?>"><?= $messagesToday ?></div>
            <div class="stat-label">Messages Today</div>
        </div>
    </div>
    <!-- Upcoming Camp -->
    <div class="col-md-4">
        <div class="info-card card h-100">
            <div class="card-header">
                <i class="fas fa-campground"></i>
                <span>Upcoming Blood Camp</span>
            </div>
            <div class="card-body">
                <?php if ($nextCamp): ?>
                    <h6 class="mb-2 fw-bold"><?= sanitize($nextCamp['title']) ?></h6>
                    <p class="mb-1 text-secondary">
                        <i class="fas fa-calendar-alt me-1"></i> <?= formatDate($nextCamp['camp_date']) ?>
                    </p>
                    <p class="mb-1 text-secondary">
                        <i class="fas fa-clock me-1"></i> <?= formatTime($nextCamp['start_time']) ?> — <?= formatTime($nextCamp['end_time']) ?>
                    </p>
                    <p class="mb-0 text-secondary">
                        <i class="fas fa-map-marker-alt me-1"></i> <?= sanitize($nextCamp['location']) ?>
                    </p>
                <?php else: ?>
                    <div class="empty-state py-3">
                        <i class="fas fa-calendar-times d-block mb-2" style="font-size:1.5rem;"></i>
                        <p class="mb-0">No upcoming camps scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Charts Row ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Blood Group Distribution -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2 text-primary"></i> Blood Group Distribution
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="bloodGroupChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history me-2 text-primary"></i> Recent Messages
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentLogs)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-inbox d-block mb-2"></i>
                        <p class="mb-0">No messages sent yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge <?= $log['message_type'] === 'WhatsApp' ? 'bg-success' : 'bg-primary' ?>">
                                            <i class="<?= $log['message_type'] === 'WhatsApp' ? 'fab fa-whatsapp' : 'fas fa-sms' ?> me-1"></i>
                                            <?= $log['message_type'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-medium"><?= sanitize($log['donor_name'] ?? 'Unknown') ?></span>
                                        <br><small class="text-muted"><?= sanitize($log['mobile']) ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= formatDate($log['sent_at'], 'd M h:i A') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $log['status'] === 'Sent' ? 'bg-success-subtle text-success' : ($log['status'] === 'Failed' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') ?>">
                                            <?= sanitize($log['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Dashboard Charts Script ────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('bloodGroupChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
                datasets: [{
                    data: [
                        <?= $bloodCounts['A+'] ?? 0 ?>,
                        <?= $bloodCounts['A-'] ?? 0 ?>,
                        <?= $bloodCounts['B+'] ?? 0 ?>,
                        <?= $bloodCounts['B-'] ?? 0 ?>,
                        <?= $bloodCounts['AB+'] ?? 0 ?>,
                        <?= $bloodCounts['AB-'] ?? 0 ?>,
                        <?= $bloodCounts['O+'] ?? 0 ?>,
                        <?= $bloodCounts['O-'] ?? 0 ?>
                    ],
                    backgroundColor: [
                        '#ef4444', '#dc2626',
                        '#3b82f6', '#2563eb',
                        '#a855f7', '#9333ea',
                        '#22c55e', '#16a34a'
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 16,
                            font: {
                                family: 'Inter',
                                size: 12,
                                weight: '500'
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
