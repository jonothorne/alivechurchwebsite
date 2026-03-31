<?php
/**
 * Blog Comments - New Admin
 */
$page_title = 'Blog Comments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle actions
if (isset($_POST['approve']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE blog_comments SET status = 'approved' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Comment approved.';
}

if (isset($_POST['spam']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE blog_comments SET status = 'spam' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Comment marked as spam.';
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Comment deleted.';
}

// Get filter
$statusFilter = $_GET['status'] ?? 'pending';

// Get comments with user data
$stmt = $pdo->prepare("SELECT c.*, p.title as post_title, p.slug as post_slug,
                              u.full_name as user_full_name, u.avatar as user_avatar, u.avatar_color as user_avatar_color
                       FROM blog_comments c
                       JOIN blog_posts p ON c.post_id = p.id
                       LEFT JOIN users u ON c.user_id = u.id
                       WHERE c.status = ?
                       ORDER BY c.created_at DESC");
$stmt->execute([$statusFilter]);
$comments = $stmt->fetchAll();

// Get counts
$counts = [
    'pending' => $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'approved'")->fetchColumn(),
    'spam' => $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'spam'")->fetchColumn(),
];
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Blog Comments</h1>
        <p class="admin-page-subtitle">Moderate user comments</p>
    </div>
    <a href="/adminnew/blog" class="admin-btn admin-btn-secondary">&larr; Back to Blog</a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs">
            <a href="/adminnew/blog/comments&status=pending" class="admin-filter-tab <?= $statusFilter === 'pending' ? 'active' : ''; ?>">
                Pending (<?= $counts['pending']; ?>)
            </a>
            <a href="/adminnew/blog/comments&status=approved" class="admin-filter-tab <?= $statusFilter === 'approved' ? 'active' : ''; ?>">
                Approved (<?= $counts['approved']; ?>)
            </a>
            <a href="/adminnew/blog/comments&status=spam" class="admin-filter-tab <?= $statusFilter === 'spam' ? 'active' : ''; ?>">
                Spam (<?= $counts['spam']; ?>)
            </a>
        </div>
    </div>

    <?php if (empty($comments)): ?>
        <div class="admin-empty-state">
            <p>No <?= $statusFilter; ?> comments.</p>
        </div>
    <?php else: ?>
        <div class="admin-comments-list">
            <?php foreach ($comments as $comment):
                $displayName = $comment['user_id'] ? ($comment['user_full_name'] ?? $comment['author_name']) : $comment['author_name'];
            ?>
                <div class="admin-comment-item">
                    <div class="admin-comment-header">
                        <div class="admin-comment-author">
                            <?php if ($comment['user_id']): ?>
                                <?php if ($comment['user_avatar']): ?>
                                    <img src="<?= htmlspecialchars($comment['user_avatar']); ?>" alt="" class="admin-avatar-sm">
                                <?php else: ?>
                                    <div class="admin-avatar-sm" style="background: <?= htmlspecialchars($comment['user_avatar_color'] ?? '#4b2679'); ?>;">
                                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($displayName); ?></strong>
                                <span class="admin-badge admin-badge-success">Member</span>
                            <?php else: ?>
                                <strong><?= htmlspecialchars($displayName); ?></strong>
                                <span class="admin-text-muted">&lt;<?= htmlspecialchars($comment['author_email'] ?? ''); ?>&gt;</span>
                            <?php endif; ?>
                        </div>
                        <span class="admin-text-muted">
                            <?= date('M j, Y \a\t g:ia', strtotime($comment['created_at'])); ?>
                        </span>
                    </div>
                    <p class="admin-comment-content">
                        <?= nl2br(htmlspecialchars($comment['content'])); ?>
                    </p>
                    <div class="admin-comment-footer">
                        <a href="/blog/<?= htmlspecialchars($comment['post_slug']); ?>" target="_blank" class="admin-link">
                            On: <?= htmlspecialchars($comment['post_title']); ?>
                        </a>
                        <div class="admin-table-actions">
                            <?php if ($statusFilter !== 'approved'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $comment['id']; ?>">
                                    <button type="submit" name="approve" class="admin-btn admin-btn-sm admin-btn-success">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($statusFilter !== 'spam'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $comment['id']; ?>">
                                    <button type="submit" name="spam" class="admin-btn admin-btn-sm admin-btn-secondary">Spam</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this comment?')">
                                <input type="hidden" name="id" value="<?= $comment['id']; ?>">
                                <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
.admin-filter-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--admin-bg);
    padding: 0.25rem;
    border-radius: var(--admin-radius);
}
.admin-filter-tab {
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: var(--admin-text-muted);
    border-radius: var(--admin-radius-sm);
    font-weight: 500;
    font-size: 0.875rem;
    transition: all var(--admin-transition);
}
.admin-filter-tab:hover { color: var(--admin-text); }
.admin-filter-tab.active {
    background: var(--admin-card-bg);
    color: var(--admin-text);
    box-shadow: var(--admin-shadow-sm);
}
.admin-comments-list { padding: 0; }
.admin-comment-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--admin-border);
}
.admin-comment-item:last-child { border-bottom: none; }
.admin-comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.admin-comment-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.admin-avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.75rem;
    object-fit: cover;
}
.admin-comment-content {
    margin: 0.5rem 0;
    background: var(--admin-bg);
    padding: 0.75rem;
    border-radius: var(--admin-radius);
}
.admin-comment-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.admin-link { color: var(--admin-primary); font-size: 0.875rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
