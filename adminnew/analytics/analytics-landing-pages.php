<?php
$page_title = 'Landing Pages';
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

// Fetch landing page data
$landingPages = $seo->getLandingPages($period, 30);
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Landing Pages</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<p class="admin-muted" style="margin-bottom: 1.5rem;">Pages where visitors arrive from search engines (Google, Bing, etc). Focus on pages with high bounce rates or declining trends.</p>

<!-- Landing Pages Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Search Engine Landing Pages</h3>
    </div>
    <?php if (empty($landingPages)): ?>
        <div class="admin-empty-state">
            <p>No search engine landing page data yet. Data will appear as visitors arrive from Google, Bing, and other search engines.</p>
        </div>
    <?php else: ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Page URL</th>
                        <th class="text-right">Search Entries</th>
                        <th class="text-right">Bounce Rate</th>
                        <th class="text-right">Avg Duration</th>
                        <th class="text-right">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($landingPages as $page): ?>
                        <?php
                            $bounceRate = (float)$page['bounce_rate'];
                            if ($bounceRate < 40) {
                                $bounceColor = '#10b981';
                            } elseif ($bounceRate <= 60) {
                                $bounceColor = '#f59e0b';
                            } else {
                                $bounceColor = '#ef4444';
                            }

                            $duration = (int)$page['avg_duration'];
                            $minutes = floor($duration / 60);
                            $seconds = $duration % 60;

                            $trend = $page['trend'];
                            $changePct = $trend['change_pct'];
                            $direction = $trend['direction'];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($page['entry_page']); ?>" target="_blank" class="analytics-page-link">
                                    <?= htmlspecialchars($page['entry_page']); ?>
                                </a>
                            </td>
                            <td class="text-right"><?= number_format($page['entries']); ?></td>
                            <td class="text-right" style="color: <?= $bounceColor; ?>; font-weight: 500;"><?= $bounceRate; ?>%</td>
                            <td class="text-right admin-muted"><?= $minutes; ?>m <?= $seconds; ?>s</td>
                            <td class="text-right">
                                <?php if ($direction === 'rising'): ?>
                                    <span style="color: #10b981;">&uarr; +<?= abs($changePct); ?>%</span>
                                <?php elseif ($direction === 'falling'): ?>
                                    <span style="color: #ef4444;">&darr; -<?= abs($changePct); ?>%</span>
                                <?php else: ?>
                                    <span style="color: #6b7280;">&mdash; 0%</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
