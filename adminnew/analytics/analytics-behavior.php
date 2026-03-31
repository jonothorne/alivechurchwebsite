<?php
$page_title = 'Behavior Analytics';
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

// Fetch behavior data
$sessionStats = $analytics->getSessionStats($period);
$newVsReturning = $analytics->getNewVsReturning($period);
$exitPages = $analytics->getExitPages($period, 10);
$entryPages = $analytics->getEntryPages($period, 10);
$heatmapData = $analytics->getTrafficHeatmap($period);
$peakTimes = $analytics->getPeakTrafficTimes($period);

$dayNames = ['', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Behavior</h2>
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
        <div class="analytics-metric-value"><?= $sessionStats['bounce_rate']; ?>%</div>
        <div class="analytics-metric-label">Bounce Rate</div>
        <div class="analytics-metric-sub">Single page visits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= $sessionStats['avg_duration_formatted']; ?></div>
        <div class="analytics-metric-label">Avg. Session</div>
        <div class="analytics-metric-sub"><?= round($sessionStats['avg_duration']); ?> seconds</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= $sessionStats['avg_pages_per_session']; ?></div>
        <div class="analytics-metric-label">Pages/Session</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($sessionStats['total_sessions']); ?></div>
        <div class="analytics-metric-label">Total Sessions</div>
    </div>
</div>

<!-- New vs Returning -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>New vs Returning Visitors</h3>
    </div>
    <div class="analytics-new-returning">
        <div class="analytics-nr-chart">
            <div class="analytics-nr-bar">
                <div class="analytics-nr-bar-new" style="width: <?= $newVsReturning['new_percent']; ?>%;"></div>
                <div class="analytics-nr-bar-returning" style="width: <?= $newVsReturning['returning_percent']; ?>%;"></div>
            </div>
        </div>
        <div class="analytics-nr-legend">
            <div class="analytics-nr-item">
                <span class="analytics-nr-dot analytics-nr-dot-new"></span>
                <span class="analytics-nr-label">New Visitors</span>
                <span class="analytics-nr-value"><?= number_format($newVsReturning['new_visitors']); ?> (<?= $newVsReturning['new_percent']; ?>%)</span>
            </div>
            <div class="analytics-nr-item">
                <span class="analytics-nr-dot analytics-nr-dot-returning"></span>
                <span class="analytics-nr-label">Returning Visitors</span>
                <span class="analytics-nr-value"><?= number_format($newVsReturning['returning_visitors']); ?> (<?= $newVsReturning['returning_percent']; ?>%)</span>
            </div>
        </div>
    </div>
</div>

<!-- Traffic Heatmap -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Traffic by Time</h3>
        <span class="admin-muted">Peak: <?= $peakTimes['peak_day']; ?> at <?= $peakTimes['peak_hour']; ?></span>
    </div>
    <div class="analytics-heatmap-wrapper">
        <div class="analytics-heatmap">
            <div class="analytics-heatmap-row analytics-heatmap-header">
                <div class="analytics-heatmap-label"></div>
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <div class="analytics-heatmap-hour"><?= $h; ?></div>
                <?php endfor; ?>
            </div>
            <?php for ($day = 1; $day <= 7; $day++): ?>
                <div class="analytics-heatmap-row">
                    <div class="analytics-heatmap-label"><?= $dayNames[$day]; ?></div>
                    <?php for ($hour = 0; $hour < 24; $hour++):
                        $value = $heatmapData['heatmap'][$day][$hour] ?? 0;
                        $intensity = $heatmapData['max_value'] > 0 ? $value / $heatmapData['max_value'] : 0;
                        // Ensure minimum visibility for cells with data
                        $alpha = $value > 0 ? max(0.15, round($intensity, 2)) : 0.05;
                    ?>
                        <div class="analytics-heatmap-cell" data-value="<?= $value; ?>" data-alpha="<?= $alpha; ?>" title="<?= $dayNames[$day]; ?> <?= sprintf('%02d:00', $hour); ?>: <?= $value; ?> visits"></div>
                    <?php endfor; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="analytics-heatmap-legend">
            <span>Less</span>
            <div class="analytics-heatmap-scale">
                <div style="background: rgba(139, 92, 246, 0);"></div>
                <div style="background: rgba(139, 92, 246, 0.25);"></div>
                <div style="background: rgba(139, 92, 246, 0.5);"></div>
                <div style="background: rgba(139, 92, 246, 0.75);"></div>
                <div style="background: rgba(139, 92, 246, 1);"></div>
            </div>
            <span>More</span>
        </div>
    </div>
</div>

<div class="analytics-grid">
    <!-- Entry Pages -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Entry Pages</h3>
                <span class="admin-muted">Where visitors land</span>
            </div>
            <?php if (empty($entryPages)): ?>
                <div class="admin-empty-state">
                    <p>No session data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($entryPages as $page): ?>
                        <div class="analytics-list-item">
                            <a href="<?= htmlspecialchars($page['entry_page']); ?>" target="_blank" class="analytics-list-title">
                                <?= htmlspecialchars(strlen($page['entry_page']) > 40 ? '...' . substr($page['entry_page'], -37) : $page['entry_page']); ?>
                            </a>
                            <div class="analytics-list-stats">
                                <span><?= number_format($page['entries']); ?> entries</span>
                                <span class="admin-muted"><?= $page['bounce_rate']; ?>% bounce</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Exit Pages -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Exit Pages</h3>
                <span class="admin-muted">Where visitors leave</span>
            </div>
            <?php if (empty($exitPages)): ?>
                <div class="admin-empty-state">
                    <p>No session data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($exitPages as $page): ?>
                        <div class="analytics-list-item">
                            <a href="<?= htmlspecialchars($page['exit_page']); ?>" target="_blank" class="analytics-list-title">
                                <?= htmlspecialchars(strlen($page['exit_page']) > 40 ? '...' . substr($page['exit_page'], -37) : $page['exit_page']); ?>
                            </a>
                            <div class="analytics-list-stats">
                                <span><?= number_format($page['exits']); ?> exits</span>
                                <span class="admin-muted"><?= $page['exit_rate']; ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script <?= csp_nonce(); ?>>
// Apply heatmap colors via JavaScript to work around CSP blocking inline styles
document.querySelectorAll('.analytics-heatmap-cell').forEach(function(cell) {
    var alpha = parseFloat(cell.getAttribute('data-alpha')) || 0.05;
    cell.style.backgroundColor = 'rgba(139, 92, 246, ' + alpha + ')';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
