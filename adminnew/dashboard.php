<?php
/**
 * New Admin Dashboard
 * Overview of church management system
 */

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Get stats (with fallbacks for tables that may not exist yet)
function safeCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        return 0;
    }
}

$stats = [
    'members' => safeCount($pdo, "SELECT COUNT(*) FROM users WHERE is_member = 1"),
    'households' => safeCount($pdo, "SELECT COUNT(*) FROM households"),
    'groups' => safeCount($pdo, "SELECT COUNT(*) FROM church_groups WHERE active = 1"),
    'events' => safeCount($pdo, "SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()"),
];

// Get recent activity (with fallback)
try {
    $recent_activity = $pdo->query("
        (SELECT 'user' as type, full_name as title, 'joined' as action, created_at as activity_time
         FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'form' as type, form_type as title, 'submitted' as action, submitted_at as activity_time
         FROM form_submissions WHERE processed = 0
         ORDER BY submitted_at DESC LIMIT 5)
        ORDER BY activity_time DESC LIMIT 10
    ")->fetchAll();
} catch (PDOException $e) {
    $recent_activity = [];
}

// Quick links based on common tasks
$quick_links = [
    ['icon' => 'user-plus', 'label' => 'Add Person', 'url' => '/adminnew/people/edit'],
    ['icon' => 'home', 'label' => 'Add Household', 'url' => '/adminnew/people/households&action=add'],
    ['icon' => 'calendar', 'label' => 'Add Event', 'url' => '/adminnew/events/edit'],
    ['icon' => 'edit', 'label' => 'New Blog Post', 'url' => '/adminnew/blog/edit'],
    ['icon' => 'users', 'label' => 'View Groups', 'url' => '/adminnew/groups'],
    ['icon' => 'inbox', 'label' => 'Form Submissions', 'url' => '/adminnew/forms'],
];

$icons = [
    'user-plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
    'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'inbox' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
];

$activity_icons = [
    'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'form' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Welcome back, <?= htmlspecialchars(explode(' ', $current_user['full_name'])[0]); ?></h1>
        <p class="admin-page-subtitle">Here's what's happening at your church</p>
    </div>
    <div class="admin-page-actions">
        <a href="/" class="admin-btn admin-btn-secondary" target="_blank">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            View Site
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['members']); ?></div>
            <div class="admin-stat-label">Members</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['households']); ?></div>
            <div class="admin-stat-label">Households</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/>
                <path d="M12 8v4M8.5 14.5L5.5 16.5M15.5 14.5l3 2"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['groups']); ?></div>
            <div class="admin-stat-label">Active Groups</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['events']); ?></div>
            <div class="admin-stat-label">Upcoming Events</div>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--admin-spacing-lg);">
    <!-- Quick Actions -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Quick Actions</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-quick-links">
                <?php foreach ($quick_links as $link): ?>
                <a href="<?= htmlspecialchars($link['url']); ?>" class="admin-quick-link">
                    <?= $icons[$link['icon']]; ?>
                    <span><?= htmlspecialchars($link['label']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Recent Activity</h3>
        </div>
        <div class="admin-card-body">
            <?php if (empty($recent_activity)): ?>
            <div class="admin-empty-state">
                <p class="admin-text-muted">No recent activity</p>
            </div>
            <?php else: ?>
            <ul class="admin-activity-feed">
                <?php foreach ($recent_activity as $item): ?>
                <li class="admin-activity-item">
                    <div class="admin-activity-icon">
                        <?= $activity_icons[$item['type']] ?? $activity_icons['user']; ?>
                    </div>
                    <div class="admin-activity-content">
                        <p class="admin-activity-text">
                            <strong><?= htmlspecialchars($item['title']); ?></strong>
                            <?= htmlspecialchars($item['action']); ?>
                        </p>
                        <span class="admin-activity-time"><?= date('M j, g:i a', strtotime($item['activity_time'])); ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Module Overview Section -->
<div class="admin-card admin-mt-lg">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Church Management Modules</h3>
    </div>
    <div class="admin-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--admin-spacing-lg);">
            <!-- People Module -->
            <div style="border: 1px solid var(--admin-border); border-radius: var(--admin-radius-lg); padding: var(--admin-spacing-lg);">
                <div style="display: flex; align-items: center; gap: var(--admin-spacing-sm); margin-bottom: var(--admin-spacing-sm);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--admin-primary)" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <h4 style="margin: 0; font-weight: 600;">People</h4>
                    <span class="admin-badge admin-badge-success">Active</span>
                </div>
                <p class="admin-text-muted" style="margin: 0 0 var(--admin-spacing-md); font-size: 0.875rem;">
                    Manage members, households, contact info, and membership status.
                </p>
                <a href="/adminnew/people" class="admin-btn admin-btn-secondary admin-btn-sm">Manage People</a>
            </div>

            <!-- Groups Module -->
            <div style="border: 1px solid var(--admin-border); border-radius: var(--admin-radius-lg); padding: var(--admin-spacing-lg);">
                <div style="display: flex; align-items: center; gap: var(--admin-spacing-sm); margin-bottom: var(--admin-spacing-sm);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--admin-info)" stroke-width="2">
                        <circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/>
                        <path d="M12 8v4M8.5 14.5L5.5 16.5M15.5 14.5l3 2"/>
                    </svg>
                    <h4 style="margin: 0; font-weight: 600;">Groups</h4>
                    <span class="admin-badge admin-badge-warning">Coming Soon</span>
                </div>
                <p class="admin-text-muted" style="margin: 0 0 var(--admin-spacing-md); font-size: 0.875rem;">
                    Small groups, ministries, and volunteer teams.
                </p>
                <a href="/adminnew/groups" class="admin-btn admin-btn-secondary admin-btn-sm" style="opacity: 0.5;">View Groups</a>
            </div>

            <!-- Services Module -->
            <div style="border: 1px solid var(--admin-border); border-radius: var(--admin-radius-lg); padding: var(--admin-spacing-lg);">
                <div style="display: flex; align-items: center; gap: var(--admin-spacing-sm); margin-bottom: var(--admin-spacing-sm);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--admin-success)" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <h4 style="margin: 0; font-weight: 600;">Services</h4>
                    <span class="admin-badge admin-badge-warning">Coming Soon</span>
                </div>
                <p class="admin-text-muted" style="margin: 0 0 var(--admin-spacing-md); font-size: 0.875rem;">
                    Service planning, scheduling, and volunteer coordination.
                </p>
                <a href="/adminnew/services" class="admin-btn admin-btn-secondary admin-btn-sm" style="opacity: 0.5;">Plan Services</a>
            </div>

            <!-- Giving Module -->
            <div style="border: 1px solid var(--admin-border); border-radius: var(--admin-radius-lg); padding: var(--admin-spacing-lg);">
                <div style="display: flex; align-items: center; gap: var(--admin-spacing-sm); margin-bottom: var(--admin-spacing-sm);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--admin-warning)" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <h4 style="margin: 0; font-weight: 600;">Giving</h4>
                    <span class="admin-badge admin-badge-warning">Coming Soon</span>
                </div>
                <p class="admin-text-muted" style="margin: 0 0 var(--admin-spacing-md); font-size: 0.875rem;">
                    Track donations, generate giving statements, and manage funds.
                </p>
                <a href="/adminnew/giving" class="admin-btn admin-btn-secondary admin-btn-sm" style="opacity: 0.5;">View Giving</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
