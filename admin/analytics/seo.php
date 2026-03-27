<?php
$page_title = 'SEO Analytics';
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

// Fetch overview data
$stats404 = $seo->get404Stats($period);
$googlebotStats = $seo->getGooglebotStats($period);
$referrerStats = $seo->getReferrerStats($period);
$gscConnected = $seo->isGscConnected();

$top404s = $seo->getTop404s($period, 5);
$topReferrers = $seo->getReferrerDomains($period, 5);

if ($gscConnected) {
    $gscStats = $seo->getGscStats($period);
    $gscTopQueries = $seo->getGscTopQueries($period, 5);
    $gscOpportunities = $seo->getGscOpportunities($period, 5);
}
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">SEO Overview</h2>
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
        <div class="analytics-metric-value"><?= number_format($stats404['total_hits']); ?></div>
        <div class="analytics-metric-label">Total 404s</div>
        <div class="analytics-metric-sub"><?= number_format($stats404['unresolved']); ?> unresolved</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($googlebotStats['total_crawls']); ?></div>
        <div class="analytics-metric-label">Googlebot Crawls</div>
        <div class="analytics-metric-sub"><?= number_format($googlebotStats['crawls_per_day'], 1); ?> crawls/day</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($referrerStats['unique_domains']); ?></div>
        <div class="analytics-metric-label">Referring Domains</div>
        <div class="analytics-metric-sub"><?= number_format($referrerStats['referred_visits']); ?> referred visits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value seo-gsc-status <?= $gscConnected ? 'seo-gsc-connected' : 'seo-gsc-disconnected'; ?>">
            <?= $gscConnected ? 'Connected' : 'Not Connected'; ?>
        </div>
        <div class="analytics-metric-label">GSC Status</div>
        <?php if ($gscConnected): ?>
            <div class="analytics-metric-sub"><?= number_format($gscStats['total_clicks']); ?> clicks</div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Wins -->
<div class="analytics-grid">
    <!-- Top Unresolved 404s -->
    <div class="analytics-col">
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Top Unresolved 404s</h3>
                <a href="/admin/analytics/404s?period=<?= $period; ?>" class="seo-view-all">View all</a>
            </div>
            <?php
            $unresolved404s = array_filter($top404s, function($item) {
                return empty($item['resolved']);
            });
            $unresolved404s = array_slice($unresolved404s, 0, 5);
            ?>
            <?php if (empty($unresolved404s)): ?>
                <div class="admin-empty-state">
                    <p>No unresolved 404s found.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($unresolved404s as $error): ?>
                        <div class="analytics-list-item">
                            <div class="analytics-list-title">
                                <?= htmlspecialchars($error['request_url']); ?>
                                <small class="admin-muted"><?= number_format($error['human_hits']); ?> human / <?= number_format($error['bot_hits']); ?> bot</small>
                            </div>
                            <div class="analytics-list-stats">
                                <span class="seo-hit-count"><?= number_format($error['hits']); ?> hits</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Referring Domains -->
    <div class="analytics-col">
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Top Referring Domains</h3>
                <a href="/admin/analytics/referrers?period=<?= $period; ?>" class="seo-view-all">View all</a>
            </div>
            <?php if (empty($topReferrers)): ?>
                <div class="admin-empty-state">
                    <p>No referring domains yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($topReferrers as $referrer): ?>
                        <div class="analytics-list-item">
                            <div class="analytics-list-title">
                                <?= htmlspecialchars($referrer['referrer_domain']); ?>
                                <small class="admin-muted"><?= number_format($referrer['pages_linked_to']); ?> pages linked</small>
                            </div>
                            <div class="analytics-list-stats">
                                <span><?= number_format($referrer['visits']); ?> visits</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($gscConnected): ?>
<!-- GSC Data -->
<div class="analytics-grid">
    <!-- Top Search Queries -->
    <div class="analytics-col">
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Top Search Queries</h3>
                <a href="/admin/analytics/gsc?period=<?= $period; ?>" class="seo-view-all">View all</a>
            </div>
            <?php if (empty($gscTopQueries)): ?>
                <div class="admin-empty-state">
                    <p>No search query data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Query</th>
                                <th class="text-right">Clicks</th>
                                <th class="text-right">Impressions</th>
                                <th class="text-right">Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gscTopQueries as $query): ?>
                                <tr>
                                    <td><?= htmlspecialchars($query['query']); ?></td>
                                    <td class="text-right"><?= number_format($query['total_clicks']); ?></td>
                                    <td class="text-right admin-muted"><?= number_format($query['total_impressions']); ?></td>
                                    <td class="text-right admin-muted"><?= number_format($query['avg_position'], 1); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- GSC Opportunities -->
    <div class="analytics-col">
        <div class="admin-card" style="margin-bottom: 1.5rem;">
            <div class="admin-card-header">
                <h3>Quick Win Opportunities</h3>
                <span class="admin-muted">Ranking 8-20, worth optimising</span>
            </div>
            <?php if (empty($gscOpportunities)): ?>
                <div class="admin-empty-state">
                    <p>No opportunities found yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($gscOpportunities as $opp): ?>
                        <div class="analytics-list-item">
                            <div class="analytics-list-title">
                                <?= htmlspecialchars($opp['query']); ?>
                                <small class="admin-muted">Pos <?= number_format($opp['avg_position'], 1); ?> &middot; <?= number_format($opp['total_impressions']); ?> impressions</small>
                            </div>
                            <div class="analytics-list-stats">
                                <span><?= number_format($opp['total_clicks']); ?> clicks</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sub-page Links -->
<div class="admin-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <h3>SEO Tools</h3>
        <span class="admin-muted">Detailed analysis pages</span>
    </div>
    <div class="seo-nav-grid">
        <a href="/admin/analytics/landing-pages?period=<?= $period; ?>" class="seo-nav-item">
            <div class="seo-nav-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="seo-nav-label">Landing Pages</div>
            <div class="seo-nav-desc">Search engine entry points &amp; bounce rates</div>
        </a>
        <a href="/admin/analytics/404s?period=<?= $period; ?>" class="seo-nav-item">
            <div class="seo-nav-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div class="seo-nav-label">404 Errors</div>
            <div class="seo-nav-desc"><?= number_format($stats404['unresolved']); ?> unresolved errors to fix</div>
        </a>
        <a href="/admin/analytics/trends?period=<?= $period; ?>" class="seo-nav-item">
            <div class="seo-nav-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="seo-nav-label">Trends</div>
            <div class="seo-nav-desc">Page traffic trends &amp; growth patterns</div>
        </a>
        <a href="/admin/analytics/referrers?period=<?= $period; ?>" class="seo-nav-item">
            <div class="seo-nav-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </div>
            <div class="seo-nav-label">Referrers</div>
            <div class="seo-nav-desc"><?= number_format($referrerStats['unique_domains']); ?> domains linking to you</div>
        </a>
        <a href="/admin/analytics/googlebot?period=<?= $period; ?>" class="seo-nav-item">
            <div class="seo-nav-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
            <div class="seo-nav-label">Googlebot</div>
            <div class="seo-nav-desc"><?= number_format($googlebotStats['unique_pages']); ?> pages crawled</div>
        </a>
        <a href="/admin/analytics/gsc?period=<?= $period; ?>" class="seo-nav-item <?= !$gscConnected ? 'seo-nav-item-disabled' : ''; ?>">
            <div class="seo-nav-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div class="seo-nav-label">Search Console</div>
            <div class="seo-nav-desc"><?= $gscConnected ? 'Queries, clicks &amp; impressions' : 'Not connected'; ?></div>
        </a>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.seo-gsc-status {
    font-size: 1.25rem;
    font-weight: 600;
}
.seo-gsc-connected {
    color: var(--color-green, #22c55e);
}
.seo-gsc-disconnected {
    color: var(--color-text-muted);
}
.seo-hit-count {
    font-weight: 500;
    color: var(--color-red, #ef4444);
}
.seo-view-all {
    font-size: 0.8125rem;
    color: var(--color-purple);
    text-decoration: none;
}
.seo-view-all:hover {
    text-decoration: underline;
}
.seo-nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
}
.seo-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.25rem 1rem;
    background: var(--color-bg);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: var(--color-text);
    transition: all 0.15s ease;
    border: 1px solid transparent;
}
.seo-nav-item:hover {
    border-color: var(--color-purple);
    transform: translateY(-2px);
}
.seo-nav-item-disabled {
    opacity: 0.5;
    pointer-events: none;
}
.seo-nav-icon {
    color: var(--color-purple);
    margin-bottom: 0.75rem;
}
.seo-nav-label {
    font-weight: 600;
    font-size: 0.9375rem;
    margin-bottom: 0.25rem;
}
.seo-nav-desc {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}
.analytics-list-title small {
    display: block;
    font-weight: 400;
}
.analytics-table-wrapper {
    overflow-x: auto;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
