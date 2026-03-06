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
$mostReadStudies = $analytics->getMostReadStudies(10);
$mostHighlightedStudies = $analytics->getMostHighlightedStudies(10);
$mostSavedStudies = $analytics->getMostSavedStudies(10);
$dailyRegistrations = $analytics->getDailyRegistrations(30);

// Prepare chart data
$chartLabels = [];
$chartVisits = [];
$chartUnique = [];
foreach ($dailyVisits as $day) {
    $chartLabels[] = date('M j', strtotime($day['date']));
    $chartVisits[] = (int)$day['visits'];
    $chartUnique[] = (int)$day['unique_visitors'];
}

$regLabels = [];
$regData = [];
foreach ($dailyRegistrations as $day) {
    $regLabels[] = date('M j', strtotime($day['date']));
    $regData[] = (int)$day['registrations'];
}

$deviceLabels = [];
$deviceData = [];
foreach ($deviceBreakdown as $device) {
    $deviceLabels[] = ucfirst($device['device_type']);
    $deviceData[] = (int)$device['count'];
}
?>

<!-- Period Selector -->
<div class="analytics-header">
    <h1>Site Analytics</h1>
    <div class="period-selector">
        <a href="?period=today" class="period-btn <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="period-btn <?= $period === 'week' ? 'active' : ''; ?>">7 Days</a>
        <a href="?period=month" class="period-btn <?= $period === 'month' ? 'active' : ''; ?>">30 Days</a>
        <a href="?period=year" class="period-btn <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="period-btn <?= $period === 'all' ? 'active' : ''; ?>">All Time</a>
    </div>
</div>

<!-- Overview Stats -->
<div class="stats-grid stats-grid-5">
    <div class="stat-card">
        <div class="stat-label">Page Views</div>
        <div class="stat-value"><?= number_format($visitCounts[$period]['total_visits']); ?></div>
        <div class="stat-compare">
            <span class="stat-sub">Today: <?= number_format($visitCounts['today']['total_visits']); ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Unique Visitors</div>
        <div class="stat-value"><?= number_format($visitCounts[$period]['unique_visitors']); ?></div>
        <div class="stat-compare">
            <span class="stat-sub">Today: <?= number_format($visitCounts['today']['unique_visitors']); ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Registered Users</div>
        <div class="stat-value"><?= number_format($userStats['total_users']); ?></div>
        <div class="stat-compare">
            <span class="stat-sub">+<?= $userStats['new_this_month']; ?> this month</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Plans</div>
        <div class="stat-value"><?= number_format($planStats['active_plans']); ?></div>
        <div class="stat-compare">
            <span class="stat-sub"><?= $planStats['completed_plans']; ?> completed</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Form Submissions</div>
        <div class="stat-value"><?= number_format($formStats['total']); ?></div>
        <div class="stat-compare">
            <?php if ($formStats['unprocessed'] > 0): ?>
                <a href="/admin/forms" class="stat-link"><?= $formStats['unprocessed']; ?> unread</a>
            <?php else: ?>
                <span class="stat-sub"><?= $formStats['this_month']; ?> this month</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Traffic Chart -->
<div class="card">
    <div class="card-header">
        <h2>Traffic Overview (Last 30 Days)</h2>
    </div>
    <div class="chart-container">
        <canvas id="trafficChart" height="100"></canvas>
    </div>
</div>

<!-- Two Column Layout -->
<div class="analytics-grid">
    <!-- Left Column -->
    <div class="analytics-column">

        <!-- User Engagement -->
        <div class="card">
            <div class="card-header">
                <h2>User Engagement</h2>
            </div>
            <div class="engagement-stats">
                <div class="engagement-stat">
                    <span class="engagement-icon">✨</span>
                    <div class="engagement-info">
                        <div class="engagement-value"><?= number_format($engagementStats['total_highlights']); ?></div>
                        <div class="engagement-label">Highlights Created</div>
                    </div>
                </div>
                <div class="engagement-stat">
                    <span class="engagement-icon">🔖</span>
                    <div class="engagement-info">
                        <div class="engagement-value"><?= number_format($engagementStats['total_saved']); ?></div>
                        <div class="engagement-label">Studies Saved</div>
                    </div>
                </div>
                <div class="engagement-stat">
                    <span class="engagement-icon">⏱️</span>
                    <div class="engagement-info">
                        <div class="engagement-value"><?= number_format($engagementStats['total_reading_time']); ?></div>
                        <div class="engagement-label">Reading Minutes</div>
                    </div>
                </div>
                <div class="engagement-stat">
                    <span class="engagement-icon">✅</span>
                    <div class="engagement-info">
                        <div class="engagement-value"><?= number_format($engagementStats['studies_completed']); ?></div>
                        <div class="engagement-label">Studies Completed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reading Plans -->
        <div class="card">
            <div class="card-header">
                <h2>Reading Plans</h2>
            </div>
            <div class="plan-stats-grid">
                <div class="plan-stat">
                    <div class="plan-stat-value"><?= $planStats['active_plans']; ?></div>
                    <div class="plan-stat-label">In Progress</div>
                </div>
                <div class="plan-stat">
                    <div class="plan-stat-value"><?= $planStats['paused_plans']; ?></div>
                    <div class="plan-stat-label">Paused</div>
                </div>
                <div class="plan-stat">
                    <div class="plan-stat-value"><?= $planStats['completed_plans']; ?></div>
                    <div class="plan-stat-label">Completed</div>
                </div>
                <div class="plan-stat">
                    <div class="plan-stat-value"><?= $planStats['completion_rate']; ?>%</div>
                    <div class="plan-stat-label">Completion Rate</div>
                </div>
            </div>
            <?php if (!empty($planStats['popular_plans'])): ?>
                <h4 style="margin: 1.5rem 0 0.75rem; color: #64748b; font-size: 0.875rem;">Most Popular Plans</h4>
                <div class="popular-plans-list">
                    <?php foreach ($planStats['popular_plans'] as $plan): ?>
                        <div class="popular-plan-item">
                            <span class="plan-icon"><?= $plan['icon'] ?: '📖'; ?></span>
                            <span class="plan-title"><?= htmlspecialchars($plan['title']); ?></span>
                            <span class="plan-users"><?= $plan['user_count']; ?> users</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- User Activity -->
        <div class="card">
            <div class="card-header">
                <h2>User Activity</h2>
            </div>
            <div class="user-stats-grid">
                <div class="user-stat">
                    <div class="user-stat-value"><?= $userStats['active_this_week']; ?></div>
                    <div class="user-stat-label">Active This Week</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $userStats['with_streaks']; ?></div>
                    <div class="user-stat-label">Users With Streaks</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $userStats['longest_streak']; ?></div>
                    <div class="user-stat-label">Longest Streak (Days)</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-value"><?= $userStats['new_this_week']; ?></div>
                    <div class="user-stat-label">New This Week</div>
                </div>
            </div>
        </div>

        <!-- Device & Browser Breakdown -->
        <div class="card">
            <div class="card-header">
                <h2>Devices & Browsers</h2>
            </div>
            <div class="device-browser-grid">
                <div>
                    <h4 style="margin: 0 0 1rem; color: #64748b; font-size: 0.875rem;">Device Types</h4>
                    <div class="chart-container-small">
                        <canvas id="deviceChart"></canvas>
                    </div>
                </div>
                <div>
                    <h4 style="margin: 0 0 1rem; color: #64748b; font-size: 0.875rem;">Browsers</h4>
                    <div class="browser-list">
                        <?php foreach ($browserBreakdown as $browser): ?>
                            <div class="browser-item">
                                <span class="browser-name"><?= htmlspecialchars($browser['browser']); ?></span>
                                <span class="browser-count"><?= number_format($browser['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Column -->
    <div class="analytics-column">

        <!-- Popular Pages -->
        <div class="card">
            <div class="card-header">
                <h2>Popular Pages</h2>
            </div>
            <?php if (empty($popularPages)): ?>
                <p style="color: #64748b;">No page visit data yet.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th style="text-align: right;">Views</th>
                                <th style="text-align: right;">Unique</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularPages as $page): ?>
                                <tr>
                                    <td>
                                        <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="page-link">
                                            <?= htmlspecialchars(strlen($page['page_url']) > 40 ? substr($page['page_url'], 0, 40) . '...' : $page['page_url']); ?>
                                        </a>
                                    </td>
                                    <td style="text-align: right;"><?= number_format($page['visits']); ?></td>
                                    <td style="text-align: right;"><?= number_format($page['unique_visitors']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Traffic Sources -->
        <div class="card">
            <div class="card-header">
                <h2>Traffic Sources</h2>
            </div>
            <?php if (empty($trafficSources)): ?>
                <p style="color: #64748b;">No traffic source data yet.</p>
            <?php else: ?>
                <div class="source-list">
                    <?php foreach ($trafficSources as $source): ?>
                        <div class="source-item">
                            <span class="source-name"><?= htmlspecialchars($source['source']); ?></span>
                            <div class="source-bar-container">
                                <?php
                                $maxVisits = $trafficSources[0]['visits'];
                                $percentage = ($source['visits'] / $maxVisits) * 100;
                                ?>
                                <div class="source-bar" style="width: <?= $percentage; ?>%;"></div>
                            </div>
                            <span class="source-count"><?= number_format($source['visits']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Most Read Studies -->
        <div class="card">
            <div class="card-header">
                <h2>Most Read Studies</h2>
            </div>
            <?php if (empty($mostReadStudies)): ?>
                <p style="color: #64748b;">No reading data yet.</p>
            <?php else: ?>
                <div class="studies-list">
                    <?php foreach (array_slice($mostReadStudies, 0, 5) as $study): ?>
                        <div class="study-item">
                            <div class="study-info">
                                <span class="study-book"><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></span>
                                <?php if ($study['title']): ?>
                                    <span class="study-title"><?= htmlspecialchars($study['title']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="study-stats">
                                <span class="study-reads"><?= number_format($study['read_count']); ?> reads</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Forms & Newsletter -->
        <div class="card">
            <div class="card-header">
                <h2>Forms & Newsletter</h2>
            </div>
            <div class="forms-newsletter-grid">
                <div>
                    <h4 style="margin: 0 0 0.75rem; color: #64748b; font-size: 0.875rem;">Form Submissions</h4>
                    <?php if (empty($formStats['by_type'])): ?>
                        <p style="color: #94a3b8; font-size: 0.875rem;">No submissions yet</p>
                    <?php else: ?>
                        <div class="form-type-list">
                            <?php foreach ($formStats['by_type'] as $type): ?>
                                <div class="form-type-item">
                                    <span class="form-type-name"><?= htmlspecialchars(ucfirst($type['form_type'])); ?></span>
                                    <span class="form-type-count"><?= number_format($type['count']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 style="margin: 0 0 0.75rem; color: #64748b; font-size: 0.875rem;">Newsletter</h4>
                    <div class="newsletter-stats">
                        <div class="newsletter-stat">
                            <div class="newsletter-value"><?= number_format($newsletterStats['active']); ?></div>
                            <div class="newsletter-label">Active</div>
                        </div>
                        <div class="newsletter-stat">
                            <div class="newsletter-value"><?= number_format($newsletterStats['new_this_month']); ?></div>
                            <div class="newsletter-label">New This Month</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- User Registrations Chart -->
<div class="card">
    <div class="card-header">
        <h2>New User Registrations (Last 30 Days)</h2>
    </div>
    <div class="chart-container">
        <canvas id="registrationsChart" height="80"></canvas>
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
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Unique Visitors',
                data: <?= json_encode($chartUnique); ?>,
                borderColor: '#f472b6',
                backgroundColor: 'rgba(244, 114, 182, 0.1)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
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
            backgroundColor: ['#667eea', '#f472b6', '#34d399'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Registrations Chart
const regCtx = document.getElementById('registrationsChart').getContext('2d');
new Chart(regCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($regLabels); ?>,
        datasets: [{
            label: 'New Users',
            data: <?= json_encode($regData); ?>,
            backgroundColor: '#34d399',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
