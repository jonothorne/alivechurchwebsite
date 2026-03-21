<?php
/**
 * Analytics Sub-Navigation
 * Displays the navigation tabs for analytics pages
 */

$analytics_pages = [
    ['url' => '/admin/analytics', 'label' => 'Overview', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
    ['url' => '/admin/analytics/traffic', 'label' => 'Traffic', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
    ['url' => '/admin/analytics/geographic', 'label' => 'Geographic', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>'],
    ['url' => '/admin/analytics/behavior', 'label' => 'Behavior', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
    ['url' => '/admin/analytics/content', 'label' => 'Content', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'],
    ['url' => '/admin/analytics/realtime', 'label' => 'Real-time', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
];

$current_analytics_url = strtok($_SERVER['REQUEST_URI'], '?');
?>

<nav class="analytics-subnav">
    <?php foreach ($analytics_pages as $page): ?>
        <a href="<?= $page['url']; ?>" class="analytics-subnav-item<?= $current_analytics_url === $page['url'] ? ' active' : ''; ?>">
            <?= $page['icon']; ?>
            <span><?= $page['label']; ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<style <?= csp_nonce(); ?>>
.analytics-subnav {
    display: flex;
    gap: 0.25rem;
    background: var(--color-bg);
    padding: 0.5rem;
    border-radius: var(--radius-xl);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.analytics-subnav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    border-radius: var(--radius-lg);
    color: var(--color-text-muted);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
    transition: all var(--transition-base);
}
.analytics-subnav-item:hover {
    background: var(--color-card-bg);
    color: var(--color-text);
}
.analytics-subnav-item.active {
    background: var(--color-purple);
    color: #fff;
}
.analytics-subnav-item svg {
    flex-shrink: 0;
    opacity: 0.7;
}
.analytics-subnav-item.active svg {
    opacity: 1;
}
@media (max-width: 768px) {
    .analytics-subnav {
        padding: 0.375rem;
    }
    .analytics-subnav-item {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    .analytics-subnav-item span {
        display: none;
    }
    .analytics-subnav-item svg {
        width: 18px;
        height: 18px;
    }
}
</style>
