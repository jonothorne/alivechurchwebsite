<?php
$page_title = 'Traffic Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Analytics.php';

$pdo = getDbConnection();
$analytics = new Analytics($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Fetch traffic data
$visitCounts = $analytics->getVisitCounts();
$dailyVisits = $analytics->getDailyVisits(30);
$trafficSources = $analytics->getTrafficSources(10, $period);
$deviceBreakdown = $analytics->getDeviceBreakdown($period);
$browserBreakdown = $analytics->getBrowserBreakdown($period);

// Prepare chart data
$chartLabels = [];
$chartVisits = [];
$chartUnique = [];
foreach ($dailyVisits as $day) {
    $chartLabels[] = date('M j', strtotime($day['date']));
    $chartVisits[] = (int)$day['visits'];
    $chartUnique[] = (int)$day['unique_visitors'];
}

$deviceLabels = [];
$deviceData = [];
foreach ($deviceBreakdown as $device) {
    $deviceLabels[] = ucfirst($device['device_type']);
    $deviceData[] = (int)$device['count'];
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Traffic</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Key Metrics -->
<div class="analytics-metrics" style="margin-bottom: 1.5rem;">
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($visitCounts[$period]['total_visits']); ?></div>
        <div class="analytics-metric-label">Page Views</div>
        <div class="analytics-metric-sub">Today: <?= number_format($visitCounts['today']['total_visits']); ?></div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($visitCounts[$period]['unique_visitors']); ?></div>
        <div class="analytics-metric-label">Unique Visitors</div>
        <div class="analytics-metric-sub">Today: <?= number_format($visitCounts['today']['unique_visitors']); ?></div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= $visitCounts[$period]['unique_visitors'] > 0 ? round($visitCounts[$period]['total_visits'] / $visitCounts[$period]['unique_visitors'], 1) : 0; ?></div>
        <div class="analytics-metric-label">Pages/Visitor</div>
    </div>
</div>

<!-- Traffic Chart -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Traffic (30 days)</h3>
    </div>
    <div class="analytics-chart">
        <canvas id="trafficChart" height="200"></canvas>
    </div>
</div>

<div class="analytics-grid">
    <!-- Traffic Sources -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Traffic Sources</h3>
            </div>
            <?php if (empty($trafficSources)): ?>
                <p class="admin-muted-text">No data yet</p>
            <?php else: ?>
                <div class="analytics-bars">
                    <?php $maxVisits = $trafficSources[0]['visits']; ?>
                    <?php foreach ($trafficSources as $source): ?>
                        <div class="analytics-bar-row">
                            <span class="analytics-bar-label"><?= htmlspecialchars($source['source']); ?></span>
                            <div class="analytics-bar-track">
                                <div class="analytics-bar-fill" style="width: <?= ($source['visits'] / $maxVisits) * 100; ?>%;"></div>
                            </div>
                            <span class="analytics-bar-value"><?= number_format($source['visits']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Devices & Browsers -->
    <div class="analytics-col">
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Devices</h3>
            </div>
            <div class="analytics-split">
                <div class="analytics-chart-small">
                    <canvas id="deviceChart"></canvas>
                </div>
                <div class="analytics-device-list">
                    <?php foreach ($deviceBreakdown as $device): ?>
                        <div class="analytics-device-item">
                            <span class="analytics-device-name"><?= ucfirst($device['device_type']); ?></span>
                            <span class="analytics-device-stats">
                                <span class="analytics-device-count"><?= number_format($device['count']); ?></span>
                                <span class="admin-muted">(<?= $device['percentage']; ?>%)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Browsers</h3>
            </div>
            <div class="analytics-browser-list">
                <?php foreach ($browserBreakdown as $browser): ?>
                    <div class="analytics-browser-item">
                        <span><?= htmlspecialchars($browser['browser'] ?? 'Unknown'); ?></span>
                        <span class="admin-muted"><?= number_format($browser['count']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script <?= csp_nonce(); ?>>
// Traffic Chart
const trafficCtx = document.getElementById('trafficChart').getContext('2d');
new Chart(trafficCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels); ?>,
        datasets: [
            {
                label: 'Page Views',
                data: <?= json_encode($chartVisits); ?>,
                borderColor: '#4b2679',
                backgroundColor: 'rgba(75, 38, 121, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2
            },
            {
                label: 'Unique',
                data: <?= json_encode($chartUnique); ?>,
                borderColor: '#cd0077',
                backgroundColor: 'rgba(205, 0, 119, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, padding: 15 } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// Device Chart
const deviceCtx = document.getElementById('deviceChart').getContext('2d');
new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($deviceLabels); ?>,
        datasets: [{
            data: <?= json_encode($deviceData); ?>,
            backgroundColor: ['#4b2679', '#cd0077', '#06b6d4'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '60%',
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

<style <?= csp_nonce(); ?>>
.analytics-device-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.analytics-device-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}
.analytics-device-name {
    font-weight: 500;
}
.analytics-device-count {
    font-weight: 600;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
