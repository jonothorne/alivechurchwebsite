<?php
$page_title = 'Googlebot Analysis';
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

// Fetch Googlebot data
$stats = $seo->getGooglebotStats($period);
$mostCrawled = $seo->getGooglebotMostCrawled($period, 20);
$ignoredPages = $seo->getGooglebotIgnoredPages(30, 15);
$stalePages = $seo->getGooglebotStalePages(14, 15);
$crawlFrequency = $seo->getGooglebotCrawlFrequency(30);

$maxCrawls = 0;
foreach ($crawlFrequency as $day) {
    if ($day['crawls'] > $maxCrawls) {
        $maxCrawls = $day['crawls'];
    }
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Googlebot Analysis</h2>
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
        <div class="analytics-metric-value"><?= number_format($stats['total_crawls']); ?></div>
        <div class="analytics-metric-label">Total Crawls</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['unique_pages']); ?></div>
        <div class="analytics-metric-label">Unique Pages Crawled</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['crawls_per_day'], 1); ?></div>
        <div class="analytics-metric-label">Crawls/Day</div>
    </div>
</div>

<!-- Crawl Frequency Chart -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Daily Crawl Volume</h3>
        <span class="admin-muted">Last 30 days</span>
    </div>
    <?php if (empty($crawlFrequency)): ?>
        <div class="admin-empty-state">
            <p>No crawl frequency data yet.</p>
        </div>
    <?php else: ?>
        <div class="googlebot-chart-container">
            <?php $chartHeight = 136; ?>
            <div class="googlebot-chart-bars">
                <?php foreach ($crawlFrequency as $day): ?>
                    <?php $barHeight = $maxCrawls > 0 ? max(2, round(($day['crawls'] / $maxCrawls) * $chartHeight)) : 2; ?>
                    <div class="googlebot-bar" style="height:<?= $barHeight; ?>px" title="<?= htmlspecialchars($day['date']); ?>: <?= number_format($day['crawls']); ?> crawls"></div>
                <?php endforeach; ?>
            </div>
            <div class="googlebot-chart-labels">
                <?php foreach ($crawlFrequency as $i => $day): ?>
                    <span><?= $i % 7 === 0 ? date('M j', strtotime($day['date'])) : ''; ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Two Column Layout -->
<div class="analytics-grid">
    <!-- Left Column: Most Crawled Pages -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Most Crawled Pages</h3>
            </div>
            <?php if (empty($mostCrawled)): ?>
                <div class="admin-empty-state">
                    <p>No crawl data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th class="text-right">Crawls</th>
                                <th class="text-right">Last Crawled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mostCrawled as $page): ?>
                                <tr>
                                    <td>
                                        <a href="<?= htmlspecialchars($page['request_url']); ?>" target="_blank" class="analytics-page-link">
                                            <?= htmlspecialchars($page['request_url']); ?>
                                        </a>
                                    </td>
                                    <td class="text-right"><?= number_format($page['crawls'] ?? 0); ?></td>
                                    <td class="text-right admin-muted"><?= $page['last_crawled'] ? date('M j', strtotime($page['last_crawled'])) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Ignored + Stale -->
    <div class="analytics-col">
        <!-- Ignored by Google -->
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Ignored by Google</h3>
            </div>
            <?php if (empty($ignoredPages)): ?>
                <div class="admin-empty-state">
                    <p>No ignored pages found.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($ignoredPages as $page): ?>
                        <div class="analytics-list-item">
                            <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="analytics-list-title analytics-page-link">
                                <?= htmlspecialchars($page['page_url']); ?>
                            </a>
                            <span><?= number_format($page['human_views']); ?> views</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="admin-muted" style="margin-top: 0.75rem; font-size: 0.8rem; padding: 0 1rem 1rem;">
                    These pages get visitors but Google hasn't crawled them recently.
                </p>
            <?php endif; ?>
        </div>

        <!-- Stale Pages -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Stale Pages</h3>
            </div>
            <?php if (empty($stalePages)): ?>
                <div class="admin-empty-state">
                    <p>No stale pages found.</p>
                </div>
            <?php else: ?>
                <div class="analytics-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th class="text-right">Days Ago</th>
                                <th class="text-right">Last Crawled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stalePages as $page): ?>
                                <tr>
                                    <td>
                                        <a href="<?= htmlspecialchars($page['request_url']); ?>" target="_blank" class="analytics-page-link">
                                            <?= htmlspecialchars($page['request_url']); ?>
                                        </a>
                                    </td>
                                    <td class="text-right"><?= number_format($page['days_since_crawl']); ?></td>
                                    <td class="text-right admin-muted"><?= date('M j', strtotime($page['last_crawled'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="admin-muted" style="margin-top: 0.75rem; font-size: 0.8rem; padding: 0 1rem 1rem;">
                    Consider updating these pages or submitting them to Google Search Console.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.analytics-table-wrapper {
    overflow-x: auto;
}
.analytics-page-link {
    color: var(--color-text);
    text-decoration: none;
    word-break: break-all;
}
.analytics-page-link:hover {
    color: var(--color-purple);
}
.googlebot-chart-container {
    padding: 1rem;
}
.googlebot-chart-bars {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 136px;
}
.googlebot-bar {
    flex: 1;
    background: var(--admin-primary, #6366f1);
    border-radius: 2px 2px 0 0;
    min-width: 0;
}
.googlebot-bar:hover {
    opacity: 0.8;
}
.googlebot-chart-labels {
    display: flex;
    gap: 2px;
    margin-top: 0.375rem;
}
.googlebot-chart-labels span {
    flex: 1;
    text-align: center;
    font-size: 0.65rem;
    color: var(--color-text-muted);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
