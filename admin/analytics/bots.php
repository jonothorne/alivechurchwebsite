<?php
$page_title = 'Bot Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/BotDetector.php';

$pdo = getDbConnection();
$botDetector = new BotDetector($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'today';
$validPeriods = ['today', 'yesterday', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'today';
}

// Fetch bot data
$stats = $botDetector->getStats($period);
$topBots = $botDetector->getTopBots($period, 20);
$byCategory = $botDetector->getByCategory($period);
$mostCrawled = $botDetector->getMostCrawledPages($period, 15);
$recentVisits = $botDetector->getRecentVisits(30);
$dailyVisits = $botDetector->getDailyVisits(30);
$suspiciousActivity = $botDetector->getSuspiciousActivity($period, 50);

// Prepare chart data
$chartLabels = [];
$chartGood = [];
$chartSuspicious = [];
$chartUnknown = [];

// Group daily visits by date
$dailyByDate = [];
foreach ($dailyVisits as $row) {
    $date = $row['date'];
    if (!isset($dailyByDate[$date])) {
        $dailyByDate[$date] = ['good' => 0, 'suspicious' => 0, 'unknown' => 0];
    }
    $dailyByDate[$date][$row['classification']] = (int)$row['visits'];
}

foreach ($dailyByDate as $date => $data) {
    $chartLabels[] = date('M j', strtotime($date));
    $chartGood[] = $data['good'];
    $chartSuspicious[] = $data['suspicious'];
    $chartUnknown[] = $data['unknown'];
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Bot & Crawler Activity</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=yesterday" class="admin-filter-tab <?= $period === 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Key Metrics -->
<div class="analytics-metrics" style="margin-bottom: 1.5rem;">
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['total']); ?></div>
        <div class="analytics-metric-label">Total Bot Visits</div>
    </div>
    <div class="analytics-metric" style="border-left: 3px solid #10b981;">
        <div class="analytics-metric-value" style="color: #10b981;"><?= number_format($stats['good']); ?></div>
        <div class="analytics-metric-label">Good Bots</div>
    </div>
    <div class="analytics-metric" style="border-left: 3px solid #f59e0b;">
        <div class="analytics-metric-value" style="color: #f59e0b;"><?= number_format($stats['unknown']); ?></div>
        <div class="analytics-metric-label">Unknown Bots</div>
    </div>
    <div class="analytics-metric" style="border-left: 3px solid #ef4444;">
        <div class="analytics-metric-value" style="color: #ef4444;"><?= number_format($stats['suspicious']); ?></div>
        <div class="analytics-metric-label">Suspicious</div>
    </div>
</div>

<!-- Bot Visits Chart -->
<?php if (!empty($chartLabels)): ?>
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <h3>Bot Activity Over Time</h3>
    <canvas id="botChart" height="120"></canvas>
</div>
<?php endif; ?>

<div class="admin-grid-2" style="margin-bottom: 1.5rem;">
    <!-- Top Bots -->
    <div class="admin-card">
        <h3>Top Bots</h3>
        <?php if (empty($topBots)): ?>
            <p class="text-muted">No bot visits recorded yet.</p>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Bot</th>
                            <th>Category</th>
                            <th>Visits</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topBots as $bot): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($bot['bot_name']); ?></strong>
                                    <?php if ($bot['bot_owner'] && $bot['bot_owner'] !== 'Unknown'): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($bot['bot_owner']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($bot['bot_category']); ?></td>
                                <td><?= number_format($bot['visits']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match($bot['classification']) {
                                        'good' => 'badge-success',
                                        'suspicious' => 'badge-danger',
                                        default => 'badge-warning'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>"><?= ucfirst($bot['classification']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- By Category -->
    <div class="admin-card">
        <h3>By Category</h3>
        <?php if (empty($byCategory)): ?>
            <p class="text-muted">No data yet.</p>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Visits</th>
                            <th>Unique Bots</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byCategory as $cat): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($cat['bot_category']); ?>
                                    <?php
                                    $badgeClass = match($cat['classification']) {
                                        'good' => 'badge-success',
                                        'suspicious' => 'badge-danger',
                                        default => 'badge-warning'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>" style="font-size: 0.65rem; margin-left: 0.25rem;"><?= ucfirst($cat['classification']); ?></span>
                                </td>
                                <td><?= number_format($cat['visits']); ?></td>
                                <td><?= number_format($cat['unique_bots']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Most Crawled Pages -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <h3>Most Crawled Pages</h3>
    <?php if (empty($mostCrawled)): ?>
        <p class="text-muted">No data yet.</p>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Visits</th>
                        <th>Bots</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mostCrawled as $page): ?>
                        <tr>
                            <td>
                                <code style="font-size: 0.8rem;"><?= htmlspecialchars(substr($page['request_url'], 0, 60)); ?><?= strlen($page['request_url']) > 60 ? '...' : ''; ?></code>
                            </td>
                            <td><?= number_format($page['visits']); ?></td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars(substr($page['bot_names'], 0, 50)); ?><?= strlen($page['bot_names']) > 50 ? '...' : ''; ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($suspiciousActivity)): ?>
<!-- Suspicious Activity -->
<div class="admin-card" style="margin-bottom: 1.5rem; border-left: 3px solid #ef4444;">
    <h3 style="color: #ef4444;">High-Volume Activity</h3>
    <p class="text-muted" style="margin-bottom: 1rem;">IPs with 50+ requests in this period. May indicate aggressive crawling or scanning.</p>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Bot</th>
                    <th>Requests</th>
                    <th>Unique Pages</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suspiciousActivity as $activity): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($activity['ip_address']); ?></code></td>
                        <td><?= htmlspecialchars($activity['bot_name']); ?></td>
                        <td><?= number_format($activity['visits']); ?></td>
                        <td><?= number_format($activity['unique_pages']); ?></td>
                        <td>
                            <?php
                            $badgeClass = match($activity['classification']) {
                                'good' => 'badge-success',
                                'suspicious' => 'badge-danger',
                                default => 'badge-warning'
                            };
                            ?>
                            <span class="badge <?= $badgeClass; ?>"><?= ucfirst($activity['classification']); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Recent Bot Visits -->
<div class="admin-card">
    <h3>Recent Bot Visits</h3>
    <?php if (empty($recentVisits)): ?>
        <p class="text-muted">No recent bot visits.</p>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Bot</th>
                        <th>URL</th>
                        <th>IP</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentVisits as $visit): ?>
                        <tr>
                            <td>
                                <small><?= date('M j, H:i', strtotime($visit['visited_at'])); ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($visit['bot_name']); ?>
                                <br><small class="text-muted"><?= htmlspecialchars($visit['bot_category']); ?></small>
                            </td>
                            <td>
                                <code style="font-size: 0.75rem;"><?= htmlspecialchars(substr($visit['request_url'], 0, 40)); ?><?= strlen($visit['request_url']) > 40 ? '...' : ''; ?></code>
                            </td>
                            <td><small><?= htmlspecialchars($visit['ip_address']); ?></small></td>
                            <td>
                                <?php
                                $badgeClass = match($visit['classification']) {
                                    'good' => 'badge-success',
                                    'suspicious' => 'badge-danger',
                                    default => 'badge-warning'
                                };
                                ?>
                                <span class="badge <?= $badgeClass; ?>"><?= ucfirst($visit['classification']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
.badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 9999px;
    text-transform: uppercase;
}
.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}
.badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}
.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}
.analytics-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}
.analytics-metric {
    background: var(--color-card-bg);
    padding: 1.25rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
}
.analytics-metric-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-text);
}
.analytics-metric-label {
    font-size: 0.875rem;
    color: var(--color-text-muted);
    margin-top: 0.25rem;
}
.admin-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}
@media (max-width: 900px) {
    .admin-grid-2 {
        grid-template-columns: 1fr;
    }
}
</style>

<?php if (!empty($chartLabels)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script <?= csp_nonce(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('botChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Good Bots',
                    data: <?= json_encode($chartGood); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderRadius: 4
                },
                {
                    label: 'Unknown',
                    data: <?= json_encode($chartUnknown); ?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    borderRadius: 4
                },
                {
                    label: 'Suspicious',
                    data: <?= json_encode($chartSuspicious); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false }
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
