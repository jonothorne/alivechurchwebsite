<?php
$page_title = 'Analytics';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/Analytics.php';

$pdo = getDbConnection();
$analytics = new Analytics($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Fetch all analytics data
$visitCounts = $analytics->getVisitCounts();
$dailyVisits = $analytics->getDailyVisits(30);
$popularPages = $analytics->getPopularPages(10, $period);
$trafficSources = $analytics->getTrafficSources(10, $period);
$deviceBreakdown = $analytics->getDeviceBreakdown($period);
$browserBreakdown = $analytics->getBrowserBreakdown($period);
$engagementStats = $analytics->getUserEngagementStats();
$planStats = $analytics->getReadingPlanStats();
$userStats = $analytics->getUserStats();
$formStats = $analytics->getFormStats();
$newsletterStats = $analytics->getNewsletterStats();
$mostReadStudies = $analytics->getMostReadStudies(5);

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

<!-- Header with Period Filter -->
<div class="analytics-header">
    <div class="analytics-title">Analytics</div>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Key Metrics Row -->
<div class="analytics-metrics">
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
        <div class="analytics-metric-value"><?= number_format($userStats['total_users']); ?></div>
        <div class="analytics-metric-label">Users</div>
        <div class="analytics-metric-sub">+<?= $userStats['new_this_month']; ?> this month</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($planStats['active_plans']); ?></div>
        <div class="analytics-metric-label">Active Plans</div>
        <div class="analytics-metric-sub"><?= $planStats['completed_plans']; ?> completed</div>
    </div>
    <div class="analytics-metric <?= $formStats['unprocessed'] > 0 ? 'analytics-metric-alert' : ''; ?>">
        <div class="analytics-metric-value"><?= number_format($formStats['total']); ?></div>
        <div class="analytics-metric-label">Form Submissions</div>
        <?php if ($formStats['unprocessed'] > 0): ?>
            <a href="/admin/forms" class="analytics-metric-link"><?= $formStats['unprocessed']; ?> unread</a>
        <?php else: ?>
            <div class="analytics-metric-sub"><?= $formStats['this_month']; ?> this month</div>
        <?php endif; ?>
    </div>
</div>

<!-- Traffic Chart -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Traffic (30 days)</h3>
    </div>
    <div class="analytics-chart">
        <canvas id="trafficChart" height="180"></canvas>
    </div>
</div>

<!-- Two Column Grid -->
<div class="analytics-grid">
    <!-- Left Column -->
    <div class="analytics-col">

        <!-- Popular Pages -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Popular Pages</h3>
            </div>
            <?php if (empty($popularPages)): ?>
                <p class="admin-muted-text">No data yet</p>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($popularPages as $page): ?>
                        <div class="analytics-list-item">
                            <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="analytics-list-title">
                                <?= htmlspecialchars(strlen($page['page_url']) > 35 ? substr($page['page_url'], 0, 35) . '...' : $page['page_url']); ?>
                            </a>
                            <div class="analytics-list-stats">
                                <span><?= number_format($page['visits']); ?></span>
                                <span class="admin-muted"><?= number_format($page['unique_visitors']); ?> unique</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Traffic Sources -->
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

        <!-- Devices & Browsers -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Devices</h3>
            </div>
            <div class="analytics-split">
                <div class="analytics-chart-small">
                    <canvas id="deviceChart"></canvas>
                </div>
                <div class="analytics-browser-list">
                    <?php foreach ($browserBreakdown as $browser): ?>
                        <div class="analytics-browser-item">
                            <span><?= htmlspecialchars($browser['browser']); ?></span>
                            <span class="admin-muted"><?= number_format($browser['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Column -->
    <div class="analytics-col">

        <!-- User Engagement -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Engagement</h3>
            </div>
            <div class="analytics-engagement">
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-icon">✨</span>
                    <span class="analytics-engagement-value"><?= number_format($engagementStats['total_highlights']); ?></span>
                    <span class="analytics-engagement-label">Highlights</span>
                </div>
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-icon">🔖</span>
                    <span class="analytics-engagement-value"><?= number_format($engagementStats['total_saved']); ?></span>
                    <span class="analytics-engagement-label">Saved</span>
                </div>
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-icon">⏱️</span>
                    <span class="analytics-engagement-value"><?= number_format($engagementStats['total_reading_time']); ?></span>
                    <span class="analytics-engagement-label">Min Read</span>
                </div>
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-icon">✅</span>
                    <span class="analytics-engagement-value"><?= number_format($engagementStats['studies_completed']); ?></span>
                    <span class="analytics-engagement-label">Completed</span>
                </div>
            </div>
        </div>

        <!-- Reading Plans -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Reading Plans</h3>
            </div>
            <div class="analytics-plan-stats">
                <div><strong><?= $planStats['active_plans']; ?></strong> Active</div>
                <div><strong><?= $planStats['paused_plans']; ?></strong> Paused</div>
                <div><strong><?= $planStats['completed_plans']; ?></strong> Done</div>
                <div><strong><?= $planStats['completion_rate']; ?>%</strong> Rate</div>
            </div>
            <?php if (!empty($planStats['popular_plans'])): ?>
                <div class="analytics-list" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--color-border);">
                    <?php foreach ($planStats['popular_plans'] as $plan): ?>
                        <div class="analytics-list-item">
                            <span><?= $plan['icon'] ?: '📖'; ?> <?= htmlspecialchars($plan['title']); ?></span>
                            <span class="admin-muted"><?= $plan['user_count']; ?> users</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- User Stats -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>User Activity</h3>
            </div>
            <div class="analytics-user-stats">
                <div><strong><?= $userStats['active_this_week']; ?></strong> active this week</div>
                <div><strong><?= $userStats['with_streaks']; ?></strong> with streaks</div>
                <div><strong><?= $userStats['longest_streak']; ?></strong> day longest streak</div>
                <div><strong><?= $userStats['new_this_week']; ?></strong> new this week</div>
            </div>
        </div>

        <!-- Most Read Studies -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Top Studies</h3>
            </div>
            <?php if (empty($mostReadStudies)): ?>
                <p class="admin-muted-text">No data yet</p>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($mostReadStudies as $study): ?>
                        <div class="analytics-list-item">
                            <span><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></span>
                            <span class="admin-muted"><?= number_format($study['read_count']); ?> reads</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Newsletter -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Newsletter</h3>
            </div>
            <div class="analytics-newsletter">
                <div><strong><?= number_format($newsletterStats['active']); ?></strong> subscribers</div>
                <div><strong>+<?= number_format($newsletterStats['new_this_month']); ?></strong> this month</div>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
                borderColor: 'var(--color-purple)',
                backgroundColor: 'rgba(107, 52, 165, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2
            },
            {
                label: 'Unique',
                data: <?= json_encode($chartUnique); ?>,
                borderColor: 'var(--color-magenta)',
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
            backgroundColor: ['var(--color-purple)', 'var(--color-magenta)', 'var(--color-cyan)'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
