<?php
$page_title = 'Real-time Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Analytics.php';
require_once __DIR__ . '/../../includes/GeoIP.php';

$pdo = getDbConnection();
$analytics = new Analytics($pdo);

// Fetch real-time data
$realTimeStats = $analytics->getRealTimeStats();
$recentPageViews = $analytics->getRecentPageViews(30);
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Header -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Real-time</h2>
    <div class="realtime-status">
        <span class="realtime-dot"></span>
        <span>Live</span>
    </div>
</div>

<!-- Real-time Metrics -->
<div class="analytics-metrics realtime-metrics" style="margin-bottom: 1.5rem;">
    <div class="analytics-metric realtime-metric-active">
        <div class="analytics-metric-value" id="activeNow"><?= $realTimeStats['active_now']; ?></div>
        <div class="analytics-metric-label">Active Now</div>
        <div class="analytics-metric-sub">Last 5 minutes</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value" id="visitors30"><?= $realTimeStats['visitors_30min']; ?></div>
        <div class="analytics-metric-label">Visitors (30 min)</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value" id="pageviews30"><?= $realTimeStats['pageviews_30min']; ?></div>
        <div class="analytics-metric-label">Page Views (30 min)</div>
    </div>
</div>

<!-- Top Pages Right Now -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3>Active Pages</h3>
        <span class="admin-muted">Pages being viewed right now</span>
    </div>
    <div id="topPagesNow">
        <?php if (empty($realTimeStats['top_pages_now'])): ?>
            <div class="admin-empty-state">
                <p>No active visitors right now.</p>
            </div>
        <?php else: ?>
            <div class="analytics-list">
                <?php foreach ($realTimeStats['top_pages_now'] as $page): ?>
                    <div class="analytics-list-item">
                        <a href="<?= htmlspecialchars($page['page_url']); ?>" target="_blank" class="analytics-list-title">
                            <?= htmlspecialchars($page['page_url']); ?>
                        </a>
                        <div class="realtime-badge"><?= $page['views']; ?> viewing</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Activity Feed -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Activity Feed</h3>
        <span class="admin-muted">Last 30 minutes</span>
    </div>
    <div class="realtime-feed" id="activityFeed">
        <?php if (empty($recentPageViews)): ?>
            <div class="admin-empty-state">
                <p>No recent activity.</p>
            </div>
        <?php else: ?>
            <?php
            $currentSession = null;
            foreach ($recentPageViews as $view):
                $isNewSession = $view['session_id'] !== $currentSession;
                $currentSession = $view['session_id'];
                $timeAgo = time() - strtotime($view['visited_at']);
                $timeAgoText = $timeAgo < 60 ? 'Just now' : ($timeAgo < 3600 ? floor($timeAgo / 60) . 'm ago' : floor($timeAgo / 3600) . 'h ago');
            ?>
                <div class="realtime-feed-item<?= $isNewSession ? ' realtime-feed-item-new' : ''; ?>">
                    <div class="realtime-feed-icon">
                        <?php if ($view['device_type'] === 'mobile'): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        <?php elseif ($view['device_type'] === 'tablet'): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="realtime-feed-content">
                        <div class="realtime-feed-page">
                            <a href="<?= htmlspecialchars($view['page_url']); ?>" target="_blank">
                                <?= htmlspecialchars(strlen($view['page_url']) > 50 ? '...' . substr($view['page_url'], -47) : $view['page_url']); ?>
                            </a>
                        </div>
                        <div class="realtime-feed-meta">
                            <?php if ($view['country_code']): ?>
                                <span><?= GeoIP::getCountryFlag($view['country_code']); ?></span>
                            <?php endif; ?>
                            <?php if ($view['city']): ?>
                                <span><?= htmlspecialchars($view['city']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="realtime-feed-time"><?= $timeAgoText; ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.realtime-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #10b981;
    font-weight: 500;
}
.realtime-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}
.realtime-metrics .realtime-metric-active {
    background: linear-gradient(135deg, var(--color-purple), var(--color-magenta));
    color: #fff;
}
.realtime-metric-active .analytics-metric-label,
.realtime-metric-active .analytics-metric-sub {
    color: rgba(255, 255, 255, 0.8);
}
.realtime-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-radius: var(--radius-lg);
    font-size: 0.8rem;
    font-weight: 500;
}

/* Activity Feed */
.realtime-feed {
    max-height: 500px;
    overflow-y: auto;
}
.realtime-feed-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--color-border);
}
.realtime-feed-item:last-child {
    border-bottom: none;
}
.realtime-feed-item-new {
    background: rgba(75, 38, 121, 0.05);
    margin: 0 -1rem;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-lg);
}
.realtime-feed-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-bg);
    border-radius: var(--radius-lg);
    color: var(--color-text-muted);
    flex-shrink: 0;
}
.realtime-feed-content {
    flex: 1;
    min-width: 0;
}
.realtime-feed-page a {
    color: var(--color-text);
    text-decoration: none;
    font-weight: 500;
    word-break: break-all;
}
.realtime-feed-page a:hover {
    color: var(--color-purple);
}
.realtime-feed-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: var(--color-text-muted);
}
.realtime-feed-time {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    white-space: nowrap;
}
</style>

<!-- Auto-refresh script -->
<script <?= csp_nonce(); ?>>
// Refresh real-time data every 30 seconds
setInterval(function() {
    fetch('/admin/api/analytics-realtime.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('activeNow').textContent = data.active_now;
            document.getElementById('visitors30').textContent = data.visitors_30min;
            document.getElementById('pageviews30').textContent = data.pageviews_30min;
        })
        .catch(err => console.log('Failed to refresh analytics'));
}, 30000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
