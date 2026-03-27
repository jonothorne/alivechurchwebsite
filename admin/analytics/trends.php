<?php
$page_title = 'Traffic Trends';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/SeoAnalytics.php';

$pdo = getDbConnection();
$seo = new SeoAnalytics($pdo);

// Get selected time period
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if (!in_array($days, [7, 30, 90])) {
    $days = 30;
}

// Fetch trend data
$trends = $seo->getPageTrafficTrends($days, 40);
$movers = $seo->getMovers($days, 10);
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Traffic Trends</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?days=7" class="admin-filter-tab <?= $days === 7 ? 'active' : ''; ?>">7 Days</a>
        <a href="?days=30" class="admin-filter-tab <?= $days === 30 ? 'active' : ''; ?>">30 Days</a>
        <a href="?days=90" class="admin-filter-tab <?= $days === 90 ? 'active' : ''; ?>">90 Days</a>
    </div>
</div>

<p class="admin-muted" style="margin-bottom: 1.5rem;">Compare page traffic between the current and previous period to spot rising and declining content.</p>

<!-- Movers: Rising & Declining -->
<div class="analytics-grid" style="margin-bottom: 1.5rem;">
    <!-- Rising Pages -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Rising Pages</h3>
            </div>
            <?php if (empty($movers['risers'])): ?>
                <div class="admin-empty-state">
                    <p>No rising pages in this period.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($movers['risers'] as $page): ?>
                        <div class="analytics-list-item">
                            <span class="analytics-list-title"><?= htmlspecialchars($page['page_url']); ?></span>
                            <div class="analytics-list-stats">
                                <span><?= number_format($page['current_views']); ?> views</span>
                                <span class="trends-change trends-up">&#9650; <?= number_format(abs($page['change_pct']), 1); ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Declining Pages -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Declining Pages</h3>
            </div>
            <?php if (empty($movers['fallers'])): ?>
                <div class="admin-empty-state">
                    <p>No declining pages in this period.</p>
                </div>
            <?php else: ?>
                <div class="analytics-list">
                    <?php foreach ($movers['fallers'] as $page): ?>
                        <div class="analytics-list-item">
                            <span class="analytics-list-title"><?= htmlspecialchars($page['page_url']); ?></span>
                            <div class="analytics-list-stats">
                                <span><?= number_format($page['current_views']); ?> views</span>
                                <span class="trends-change trends-down">&#9660; <?= number_format(abs($page['change_pct']), 1); ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Full Trends Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Pages</h3>
        <span class="admin-muted">Comparing last <?= $days; ?> days vs previous <?= $days; ?> days</span>
    </div>
    <?php if (empty($trends)): ?>
        <div class="admin-empty-state">
            <p>No traffic data yet.</p>
        </div>
    <?php else: ?>
        <div class="analytics-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Page URL</th>
                        <th class="text-right">Current Views</th>
                        <th class="text-right">Previous Views</th>
                        <th class="text-right">Change</th>
                        <th class="text-right">Direction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trends as $page): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="analytics-page-link">
                                    <?= htmlspecialchars($page['page_url']); ?>
                                </a>
                            </td>
                            <td class="text-right"><?= number_format($page['current_views']); ?></td>
                            <td class="text-right admin-muted"><?= number_format($page['previous_views']); ?></td>
                            <td class="text-right">
                                <?php if ($page['change_pct'] > 0): ?>
                                    <span class="trends-change trends-up">&#9650; <?= number_format(abs($page['change_pct']), 1); ?>%</span>
                                <?php elseif ($page['change_pct'] < 0): ?>
                                    <span class="trends-change trends-down">&#9660; <?= number_format(abs($page['change_pct']), 1); ?>%</span>
                                <?php else: ?>
                                    <span class="trends-change trends-stable">0.0%</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($page['direction'] === 'rising'): ?>
                                    <span class="trends-badge trends-badge-rising">rising</span>
                                <?php elseif ($page['direction'] === 'falling'): ?>
                                    <span class="trends-badge trends-badge-falling">falling</span>
                                <?php else: ?>
                                    <span class="trends-badge trends-badge-stable">stable</span>
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
.trends-change {
    font-weight: 500;
    font-size: 0.875rem;
}
.trends-up {
    color: #10b981;
}
.trends-down {
    color: #ef4444;
}
.trends-stable {
    color: #6b7280;
}
.trends-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.trends-badge-rising {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}
.trends-badge-falling {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}
.trends-badge-stable {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
