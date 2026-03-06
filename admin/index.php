<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get quick stats
$stats = [
    'pages' => $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn() ?: 0,
    'content_blocks' => $pdo->query("SELECT COUNT(*) FROM content_blocks")->fetchColumn() ?: 0,
    'media' => $pdo->query("SELECT COUNT(*) FROM media")->fetchColumn() ?: 0,
    'submissions' => $pdo->query("SELECT COUNT(*) FROM form_submissions WHERE processed = 0")->fetchColumn() ?: 0,
];

// Get recently edited content
$recent_edits = $pdo->query("
    SELECT cb.page_slug, cb.block_key, cb.updated_at, u.username
    FROM content_blocks cb
    LEFT JOIN users u ON cb.updated_by = u.id
    ORDER BY cb.updated_at DESC
    LIMIT 8
")->fetchAll();

// Get recent form submissions
$recent_submissions = $pdo->query("
    SELECT form_type, submitted_at, processed
    FROM form_submissions
    ORDER BY submitted_at DESC
    LIMIT 5
")->fetchAll();
?>

<!-- Welcome Banner -->
<div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; margin-bottom: 2rem;">
    <h2 style="color: #fff; margin: 0 0 0.5rem;">Welcome, <?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']); ?>!</h2>
    <p style="opacity: 0.9; margin-bottom: 1.5rem;">
        Edit your website content directly on the live pages. Navigate to any page and click on text to start editing.
    </p>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="/" class="btn" style="background: #fff; color: #667eea;">Start Editing Website</a>
        <a href="/admin/media" class="btn" style="background: rgba(255,255,255,0.2); color: #fff;">Media Library</a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Pages</div>
        <div class="stat-value"><?= $stats['pages']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Content Edits</div>
        <div class="stat-value"><?= $stats['content_blocks']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Media Files</div>
        <div class="stat-value"><?= $stats['media']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">New Submissions</div>
        <div class="stat-value"><?= $stats['submissions']; ?></div>
        <?php if ($stats['submissions'] > 0): ?>
            <a href="/admin/forms" style="font-size: 0.75rem; color: #667eea;">View →</a>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Edit Links -->
<div class="card">
    <div class="card-header">
        <h2>Quick Edit Pages</h2>
    </div>
    <p style="color: #64748b; margin-bottom: 1rem;">Click a page to start editing its content directly:</p>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.75rem;">
        <a href="/" class="btn btn-outline" style="justify-content: flex-start;">Home</a>
        <a href="/visit" class="btn btn-outline" style="justify-content: flex-start;">Visit</a>
        <a href="/watch" class="btn btn-outline" style="justify-content: flex-start;">Watch</a>
        <a href="/connect" class="btn btn-outline" style="justify-content: flex-start;">Connect</a>
        <a href="/events" class="btn btn-outline" style="justify-content: flex-start;">Events</a>
        <a href="/give" class="btn btn-outline" style="justify-content: flex-start;">Give</a>
    </div>
</div>

<div class="card-grid" style="grid-template-columns: 1fr 1fr;">
    <!-- Recent Edits -->
    <div class="card">
        <div class="card-header">
            <h2>Recent Content Edits</h2>
        </div>
        <?php if (empty($recent_edits)): ?>
            <p style="color: #64748b;">No content edits yet. Start editing pages to see activity here.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach ($recent_edits as $edit): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                        <div>
                            <a href="/<?= $edit['page_slug'] === 'home' ? '' : htmlspecialchars($edit['page_slug']); ?>" style="font-weight: 500; color: #1e293b;">
                                <?= htmlspecialchars($edit['page_slug']); ?>
                            </a>
                            <span style="color: #94a3b8; font-size: 0.875rem;">/ <?= htmlspecialchars($edit['block_key']); ?></span>
                        </div>
                        <span style="color: #64748b; font-size: 0.75rem;">
                            <?= date('M j, g:ia', strtotime($edit['updated_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Submissions -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Recent Submissions</h2>
            <a href="/admin/forms" style="color: #667eea; font-size: 0.875rem;">View all →</a>
        </div>
        <?php if (empty($recent_submissions)): ?>
            <p style="color: #64748b;">No form submissions yet.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach ($recent_submissions as $sub): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge <?= $sub['processed'] ? '' : 'badge-primary'; ?>">
                                <?= htmlspecialchars($sub['form_type']); ?>
                            </span>
                            <?php if (!$sub['processed']): ?>
                                <span style="color: #f59e0b; font-size: 0.75rem;">New</span>
                            <?php endif; ?>
                        </div>
                        <span style="color: #64748b; font-size: 0.75rem;">
                            <?= date('M j, g:ia', strtotime($sub['submitted_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- How It Works -->
<div class="card" style="background: #f0fdf4; border: 1px solid #86efac;">
    <h3 style="color: #166534; margin-bottom: 1rem;">How Inline Editing Works</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem;">
        <div>
            <div style="font-size: 1.5rem; color: #166534; font-weight: 700;">1</div>
            <h4 style="margin: 0.25rem 0; color: #166534;">Go to any page</h4>
            <p style="color: #166534; font-size: 0.875rem; margin: 0;">Navigate to your website while logged in.</p>
        </div>
        <div>
            <div style="font-size: 1.5rem; color: #166534; font-weight: 700;">2</div>
            <h4 style="margin: 0.25rem 0; color: #166534;">Click to edit</h4>
            <p style="color: #166534; font-size: 0.875rem; margin: 0;">Click any text with a dashed outline to edit it.</p>
        </div>
        <div>
            <div style="font-size: 1.5rem; color: #166534; font-weight: 700;">3</div>
            <h4 style="margin: 0.25rem 0; color: #166534;">Save changes</h4>
            <p style="color: #166534; font-size: 0.875rem; margin: 0;">Press Ctrl+S or click Save. Changes are instant!</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
