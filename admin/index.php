<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get quick stats
$stats = [
    'pages' => $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn() ?: 0,
    'blog_posts' => $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn() ?: 0,
    'media' => $pdo->query("SELECT COUNT(*) FROM media")->fetchColumn() ?: 0,
    'submissions' => $pdo->query("SELECT COUNT(*) FROM form_submissions WHERE processed = 0")->fetchColumn() ?: 0,
];

// Get combined recent activity (edits + submissions)
$recent_activity = $pdo->query("
    (SELECT 'edit' as type, page_slug as title, block_key as detail, updated_at as activity_time, u.username
     FROM content_blocks cb
     LEFT JOIN users u ON cb.updated_by = u.id
     ORDER BY updated_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'submission' as type, form_type as title, '' as detail, submitted_at as activity_time, '' as username
     FROM form_submissions WHERE processed = 0
     ORDER BY submitted_at DESC LIMIT 3)
    ORDER BY activity_time DESC LIMIT 6
")->fetchAll();

// Get pages for quick edit
$pages = $pdo->query("SELECT slug, title FROM pages WHERE published = 1 ORDER BY title LIMIT 8")->fetchAll();
?>

<!-- Compact Dashboard Header with Stats -->
<div class="admin-dashboard-header">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Hi, <?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']); ?></span>
        <a href="/" class="btn btn-sm btn-primary" target="_blank">Edit Site</a>
    </div>
    <div class="admin-inline-stats">
        <a href="/admin/pages" class="admin-inline-stat">
            <strong><?= $stats['pages']; ?></strong> Pages
        </a>
        <a href="/admin/blog" class="admin-inline-stat">
            <strong><?= $stats['blog_posts']; ?></strong> Posts
        </a>
        <a href="/admin/media" class="admin-inline-stat">
            <strong><?= $stats['media']; ?></strong> Media
        </a>
        <?php if ($stats['submissions'] > 0): ?>
        <a href="/admin/forms" class="admin-inline-stat admin-inline-stat-alert">
            <strong><?= $stats['submissions']; ?></strong> New Forms
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Two Column Layout: Quick Edit + Activity -->
<div class="admin-dashboard-grid">
    <!-- Quick Edit Pages -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Quick Edit</h3>
            <a href="/admin/pages" class="admin-link-muted">All pages</a>
        </div>
        <div class="admin-page-links">
            <a href="/" class="admin-page-link" target="_blank">
                <span class="admin-page-link-icon">🏠</span>
                <span>Home</span>
            </a>
            <?php foreach ($pages as $page): ?>
            <a href="/<?= htmlspecialchars($page['slug']); ?>" class="admin-page-link" target="_blank">
                <span class="admin-page-link-icon">📄</span>
                <span><?= htmlspecialchars($page['title']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Activity Stream -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Recent Activity</h3>
        </div>
        <?php if (empty($recent_activity)): ?>
            <p class="admin-muted-text">No recent activity</p>
        <?php else: ?>
            <div class="admin-compact-activity">
                <?php foreach ($recent_activity as $item): ?>
                    <div class="admin-activity-row">
                        <span class="admin-activity-icon-sm"><?= $item['type'] === 'edit' ? '✏️' : '📩'; ?></span>
                        <span class="admin-activity-main">
                            <?php if ($item['type'] === 'edit'): ?>
                                <a href="/<?= $item['title'] === 'home' ? '' : htmlspecialchars($item['title']); ?>" target="_blank"><?= htmlspecialchars($item['title']); ?></a><?php if ($item['detail']): ?><span class="admin-muted"> / <?= htmlspecialchars($item['detail']); ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-warning"><?= htmlspecialchars($item['title']); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="admin-activity-time-inline"><?= date('M j, g:ia', strtotime($item['activity_time'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Compact Tip -->
<div class="admin-tip">
    <strong>Tip:</strong> Click any page above to edit content directly on the live site. Press <kbd>Ctrl+S</kbd> to save.
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
