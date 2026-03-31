<?php
$page_title = 'Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Analytics.php';
require_once __DIR__ . '/../../includes/GeoIP.php';

$pdo = getDbConnection();
$analytics = new Analytics($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Fetch overview data
$visitCounts = $analytics->getVisitCounts();
$dailyVisits = $analytics->getDailyVisits(30);
$realTimeStats = $analytics->getRealTimeStats();
$newVsReturning = $analytics->getNewVsReturning($period);
$topCountries = $analytics->getVisitorsByCountry($period, 5);
$popularPages = $analytics->getPopularPages(5, $period);
$userStats = $analytics->getUserStats();
$engagementStats = $analytics->getUserEngagementStats();
$planStats = $analytics->getReadingPlanStats();
$formStats = $analytics->getFormStats();
$newsletterStats = $analytics->getNewsletterStats();

// Prepare chart data
$chartLabels = [];
$chartVisits = [];
$chartUnique = [];
foreach ($dailyVisits as $day) {
    $chartLabels[] = date('M j', strtotime($day['date']));
    $chartVisits[] = (int)$day['visits'];
    $chartUnique[] = (int)$day['unique_visitors'];
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Overview</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Key Metrics -->
<div class="analytics-metrics">
    <a href="/adminnew/analytics/realtime" class="analytics-metric analytics-metric-clickable analytics-metric-realtime">
        <div class="analytics-metric-value"><?= $realTimeStats['active_now']; ?></div>
        <div class="analytics-metric-label">Active Now</div>
        <div class="analytics-metric-sub">
            <span class="realtime-indicator"></span> Live
        </div>
    </a>
    <a href="/adminnew/analytics/traffic" class="analytics-metric analytics-metric-clickable">
        <div class="analytics-metric-value"><?= number_format($visitCounts[$period]['total_visits']); ?></div>
        <div class="analytics-metric-label">Page Views</div>
        <div class="analytics-metric-sub">Today: <?= number_format($visitCounts['today']['total_visits']); ?></div>
    </a>
    <a href="/adminnew/analytics/traffic" class="analytics-metric analytics-metric-clickable">
        <div class="analytics-metric-value"><?= number_format($visitCounts[$period]['unique_visitors']); ?></div>
        <div class="analytics-metric-label">Unique Visitors</div>
        <div class="analytics-metric-sub">Today: <?= number_format($visitCounts['today']['unique_visitors']); ?></div>
    </a>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($userStats['total_users']); ?></div>
        <div class="analytics-metric-label">Registered Users</div>
        <div class="analytics-metric-sub">+<?= $userStats['new_this_month']; ?> this month</div>
    </div>
    <div class="analytics-metric <?= $formStats['unprocessed'] > 0 ? 'analytics-metric-alert' : ''; ?>">
        <div class="analytics-metric-value"><?= number_format($formStats['total']); ?></div>
        <div class="analytics-metric-label">Form Submissions</div>
        <?php if ($formStats['unprocessed'] > 0): ?>
            <a href="/adminnew/forms" class="analytics-metric-link"><?= $formStats['unprocessed']; ?> unread</a>
        <?php else: ?>
            <div class="analytics-metric-sub"><?= $formStats['this_month']; ?> this month</div>
        <?php endif; ?>
    </div>
</div>

<!-- Traffic Chart -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Traffic (30 days)</h3>
        <a href="/adminnew/analytics/traffic" class="admin-link">View Details &rarr;</a>
    </div>
    <div class="analytics-chart">
        <canvas id="trafficChart" height="180"></canvas>
    </div>
</div>

<!-- Overview Grid -->
<div class="analytics-grid">
    <!-- Left Column -->
    <div class="analytics-col">

        <!-- Geographic Quick View -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Top Countries</h3>
                <a href="/adminnew/analytics/geographic" class="admin-link">View All &rarr;</a>
            </div>
            <?php if (empty($topCountries)): ?>
                <div class="admin-empty-state">
                    <p>Geographic data will appear as visitors browse your site.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($topCountries as $country): ?>
                        <div class="analytics-list-item">
                            <span>
                                <?= GeoIP::getCountryFlag($country['country_code']); ?>
                                <?= htmlspecialchars($country['country_name'] ?: $country['country_code']); ?>
                            </span>
                            <span class="admin-muted"><?= number_format($country['unique_visitors']); ?> visitors</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Popular Pages Quick View -->
        <div class="admin-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <h3>Popular Pages</h3>
                <a href="/adminnew/analytics/content" class="admin-link">View All &rarr;</a>
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
                            <span class="admin-muted"><?= number_format($page['visits']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column -->
    <div class="analytics-col">

        <!-- New vs Returning -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Visitors</h3>
                <a href="/adminnew/analytics/behavior" class="admin-link">View Details &rarr;</a>
            </div>
            <div class="analytics-visitors-split">
                <div class="analytics-visitors-item">
                    <div class="analytics-visitors-value"><?= $newVsReturning['new_percent']; ?>%</div>
                    <div class="analytics-visitors-label">New</div>
                    <div class="analytics-visitors-count"><?= number_format($newVsReturning['new_visitors']); ?></div>
                </div>
                <div class="analytics-visitors-divider"></div>
                <div class="analytics-visitors-item">
                    <div class="analytics-visitors-value"><?= $newVsReturning['returning_percent']; ?>%</div>
                    <div class="analytics-visitors-label">Returning</div>
                    <div class="analytics-visitors-count"><?= number_format($newVsReturning['returning_visitors']); ?></div>
                </div>
            </div>
        </div>

        <!-- Engagement Stats -->
        <div class="admin-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <h3>Engagement</h3>
            </div>
            <div class="analytics-engagement-grid">
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-value"><?= number_format($engagementStats['total_highlights']); ?></span>
                    <span class="analytics-engagement-label">Highlights</span>
                </div>
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-value"><?= number_format($engagementStats['total_saved']); ?></span>
                    <span class="analytics-engagement-label">Saved</span>
                </div>
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-value"><?= number_format($planStats['active_plans']); ?></span>
                    <span class="analytics-engagement-label">Active Plans</span>
                </div>
                <div class="analytics-engagement-item">
                    <span class="analytics-engagement-value"><?= number_format($newsletterStats['active']); ?></span>
                    <span class="analytics-engagement-label">Subscribers</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="admin-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <h3>Quick Access</h3>
            </div>
            <div class="analytics-quick-links">
                <a href="/adminnew/analytics/geographic" class="analytics-quick-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span>Geographic Map</span>
                </a>
                <a href="/adminnew/analytics/behavior" class="analytics-quick-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Traffic Heatmap</span>
                </a>
                <a href="/adminnew/analytics/content" class="analytics-quick-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span>Content Analytics</span>
                </a>
                <a href="/adminnew/analytics/realtime" class="analytics-quick-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Live Activity</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js" <?= csp_nonce(); ?>></script>
<script <?= csp_nonce(); ?>>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
