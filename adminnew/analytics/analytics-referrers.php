<?php
$page_title = 'Referrer Domains';
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

// Fetch referrer data
$domains = $seo->getReferrerDomains($period, 30);
$stats = $seo->getReferrerStats($period);

$referralRate = $stats['total_visits'] > 0
    ? round($stats['referred_visits'] / $stats['total_visits'] * 100, 1)
    : 0;
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Referrer Domains</h2>
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
        <div class="analytics-metric-value"><?= number_format($stats['unique_domains']); ?></div>
        <div class="analytics-metric-label">Unique Domains</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['referred_visits']); ?></div>
        <div class="analytics-metric-label">Referred Visits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($stats['total_visits']); ?></div>
        <div class="analytics-metric-label">Total Visits</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= $referralRate; ?>%</div>
        <div class="analytics-metric-label">Referral Rate</div>
    </div>
</div>

<p class="admin-muted" style="margin-bottom: 1.5rem;">External websites linking to your site. These backlinks are valuable for SEO — the more quality domains linking to you, the better.</p>

<!-- Referrer Domains Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Referring Domains</h3>
    </div>
    <?php if (empty($domains)): ?>
        <div class="admin-empty-state">
            <p>No external referrer data yet. This excludes search engines and self-referrals.</p>
        </div>
    <?php else: ?>
        <?php $maxVisits = $domains[0]['visits'] ?? 1; ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th class="text-right">Visits</th>
                        <th class="text-right">Unique Sessions</th>
                        <th class="text-right">Pages Linked To</th>
                        <th class="text-right">First Seen</th>
                        <th class="text-right">Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <?php $barWidth = round($domain['visits'] / $maxVisits * 100); ?>
                        <tr>
                            <td>
                                <a href="https://<?= htmlspecialchars($domain['domain']); ?>" target="_blank" rel="noopener" class="referrer-domain-link">
                                    <strong><?= htmlspecialchars($domain['domain']); ?></strong>
                                </a>
                                <div class="referrer-bar-track">
                                    <div class="referrer-bar-fill" style="width: <?= $barWidth; ?>%;"></div>
                                </div>
                            </td>
                            <td class="text-right"><?= number_format($domain['visits']); ?></td>
                            <td class="text-right admin-muted"><?= number_format($domain['unique_sessions']); ?></td>
                            <td class="text-right"><?= number_format($domain['pages_linked_to']); ?></td>
                            <td class="text-right admin-muted"><?= date('M j, Y', strtotime($domain['first_seen'])); ?></td>
                            <td class="text-right admin-muted"><?= date('M j', strtotime($domain['last_seen'])); ?></td>
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
.referrer-domain-link {
    color: var(--color-text);
    text-decoration: none;
    display: block;
}
.referrer-domain-link:hover {
    color: var(--color-purple);
}
.referrer-bar-track {
    height: 4px;
    background: var(--color-bg);
    border-radius: 2px;
    margin-top: 0.375rem;
    width: 100%;
    max-width: 200px;
}
.referrer-bar-fill {
    height: 100%;
    background: var(--color-purple);
    border-radius: 2px;
    min-width: 2px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
