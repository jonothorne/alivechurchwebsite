<?php
/**
 * Analytics Sub-Navigation for New Admin
 */

$analytics_pages = [
    ['url' => '/adminnew/analytics', 'label' => 'Overview', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
    ['url' => '/adminnew/analytics/traffic', 'label' => 'Traffic', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
    ['url' => '/adminnew/analytics/geographic', 'label' => 'Geographic', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>'],
    ['url' => '/adminnew/analytics/behavior', 'label' => 'Behavior', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
    ['url' => '/adminnew/analytics/content', 'label' => 'Content', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'],
    ['url' => '/adminnew/analytics/bots', 'label' => 'Bots', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>'],
    ['url' => '/adminnew/analytics/seo', 'label' => 'SEO', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>'],
    ['url' => '/adminnew/analytics/realtime', 'label' => 'Real-time', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
    ['url' => '/adminnew/analytics/indexing', 'label' => 'Indexing', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>'],
];

$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$current_path = rtrim($current_path, '/');
?>

<nav class="analytics-subnav">
    <?php foreach ($analytics_pages as $page):
        $page_path = rtrim($page['url'], '/');
        $is_active = ($current_path === $page_path) ||
                     ($page_path === '/adminnew/analytics' && $current_path === '/adminnew/analytics');
    ?>
        <a href="<?= $page['url']; ?>" class="analytics-subnav-item<?= $is_active ? ' active' : ''; ?>">
            <?= $page['icon']; ?>
            <span><?= $page['label']; ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<style>
.analytics-subnav {
    display: flex;
    gap: 0.25rem;
    background: var(--admin-bg);
    padding: 0.5rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1.5rem;
    overflow-x: auto;
}
.analytics-subnav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    border-radius: var(--admin-radius-sm);
    color: var(--admin-text-muted);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
    transition: all var(--admin-transition);
}
.analytics-subnav-item:hover {
    background: var(--admin-card-bg);
    color: var(--admin-text);
}
.analytics-subnav-item.active {
    background: var(--admin-primary);
    color: #fff;
}
.analytics-subnav-item svg { flex-shrink: 0; opacity: 0.7; }
.analytics-subnav-item.active svg { opacity: 1; }
@media (max-width: 768px) {
    .analytics-subnav-item span { display: none; }
}
</style>
