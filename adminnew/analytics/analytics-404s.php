<?php
$page_title = '404 Errors';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/SeoAnalytics.php';

$pdo = getDbConnection();
$seo = new SeoAnalytics($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Fetch 404 data
$stats = $seo->get404Stats($period);
$top404s = $seo->getTop404s($period, 30);
$trend = $seo->get404Trend(30);
$googlebotCrawls = $seo->get404GooglebotCrawls(15);
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">404 Errors</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Summary Metrics -->
<div class="analytics-metrics" style="margin-bottom: 1.5rem;">
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['unique_urls']); ?></div>
        <div class="analytics-metric-label">Unique 404 URLs</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['total_hits']); ?></div>
        <div class="analytics-metric-label">Total Hits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['human_hits']); ?></div>
        <div class="analytics-metric-label">Human Hits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['bot_hits']); ?></div>
        <div class="analytics-metric-label">Bot Hits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['unresolved']); ?></div>
        <div class="analytics-metric-label">Unresolved</div>
    </div>
</div>

<!-- 404 Trend Chart (Last 30 Days) -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>404 Trend (Last 30 Days)</h3>
    </div>
    <?php if (empty($trend)): ?>
        <div class="admin-empty-state">
            <p>No 404 trend data yet.</p>
        </div>
    <?php else: ?>
        <?php $maxHits = max(array_column($trend, 'total_hits')); ?>
        <div class="trend-chart">
            <?php foreach ($trend as $day): ?>
                <?php $pct = $maxHits > 0 ? round(($day['total_hits'] / $maxHits) * 100) : 0; ?>
                <div class="trend-chart-bar-wrapper" title="<?= date('M j', strtotime($day['date'])); ?>: <?= number_format($day['total_hits']); ?> hits">
                    <div class="trend-chart-bar" style="height: <?= $pct; ?>%;"></div>
                    <div class="trend-chart-label"><?= date('j', strtotime($day['date'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Top 404 URLs -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Top 404 URLs</h3>
    </div>
    <?php if (empty($top404s)): ?>
        <div class="admin-empty-state">
            <p>No 404 errors recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th class="text-right">Hits</th>
                        <th class="text-right">Human</th>
                        <th class="text-right">Bot</th>
                        <th>Last Seen</th>
                        <th>Referrer</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top404s as $row): ?>
                        <tr>
                            <td>
                                <span class="four04-url"><?= htmlspecialchars($row['request_url']); ?></span>
                            </td>
                            <td class="text-right"><?= number_format($row['hits']); ?></td>
                            <td class="text-right"><?= number_format($row['human_hits']); ?></td>
                            <td class="text-right admin-muted"><?= number_format($row['bot_hits']); ?></td>
                            <td class="admin-muted"><?= date('M j', strtotime($row['last_seen'])); ?></td>
                            <td class="admin-muted">
                                <?php if (!empty($row['referrer'])): ?>
                                    <span class="four04-referrer" title="<?= htmlspecialchars($row['referrer']); ?>">
                                        <?= htmlspecialchars(parse_url($row['referrer'], PHP_URL_HOST) ?: $row['referrer']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="admin-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['resolved'])): ?>
                                    <span class="four04-badge four04-badge-resolved">Resolved</span>
                                <?php else: ?>
                                    <span class="four04-badge four04-badge-unresolved">Unresolved</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Googlebot 404s -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Googlebot 404s</h3>
        <span class="admin-muted">URLs Google is trying to crawl that return 404 - highest priority to fix</span>
    </div>
    <?php if (empty($googlebotCrawls)): ?>
        <div class="admin-empty-state">
            <p>No Googlebot 404s recorded in the last 30 days.</p>
        </div>
    <?php else: ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th class="text-right">Hits</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($googlebotCrawls as $crawl): ?>
                        <tr>
                            <td>
                                <span class="four04-url"><?= htmlspecialchars($crawl['request_url']); ?></span>
                            </td>
                            <td class="text-right"><?= number_format($crawl['hits']); ?></td>
                            <td class="admin-muted"><?= date('M j', strtotime($crawl['last_seen'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Help Note -->
<p class="admin-muted" style="font-size: 0.8rem; margin-top: 0.5rem;">
    Fix these by creating redirects in .htaccess or creating the missing content.
</p>

<style <?= csp_nonce(); ?>>
.analytics-table-wrapper {
    overflow-x: auto;
}
.four04-url {
    word-break: break-all;
    font-size: 0.875rem;
}
.four04-referrer {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    vertical-align: middle;
    font-size: 0.8rem;
}
.four04-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: var(--radius-lg);
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}
.four04-badge-resolved {
    background: rgba(34, 197, 94, 0.1);
    color: #16a34a;
    border: 1px solid rgba(34, 197, 94, 0.2);
}
.four04-badge-unresolved {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 120px;
    padding: 1rem;
}
.trend-chart-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}
.trend-chart-bar {
    width: 100%;
    min-height: 2px;
    background: var(--color-purple, #7c3aed);
    border-radius: 2px 2px 0 0;
    transition: opacity 0.2s;
}
.trend-chart-bar-wrapper:hover .trend-chart-bar {
    opacity: 0.7;
}
.trend-chart-label {
    font-size: 0.6rem;
    color: var(--color-text-muted);
    margin-top: 4px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
