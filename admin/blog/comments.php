<?php
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

// Get comments
$stmt = $pdo->prepare("SELECT c.*, p.title as post_title, p.slug as post_slug
                       FROM blog_comments c
                       JOIN blog_posts p ON c.post_id = p.id
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

<div style="margin-bottom: 1.5rem;">
    <a href="/admin/blog" style="color: #667eea; text-decoration: none;">&larr; Back to Blog Posts</a>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Manage Comments</h2>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs" style="margin-bottom: 1.5rem;">
        <a href="/admin/blog/comments?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : ''; ?>">
            Pending (<?= $counts['pending']; ?>)
        </a>
        <a href="/admin/blog/comments?status=approved" class="filter-tab <?= $statusFilter === 'approved' ? 'active' : ''; ?>">
            Approved (<?= $counts['approved']; ?>)
        </a>
        <a href="/admin/blog/comments?status=spam" class="filter-tab <?= $statusFilter === 'spam' ? 'active' : ''; ?>">
            Spam (<?= $counts['spam']; ?>)
        </a>
    </div>

    <?php if (empty($comments)): ?>
        <div class="empty-state" style="padding: 2rem;">
            <p style="color: #64748b;">No <?= $statusFilter; ?> comments.</p>
        </div>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item" style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <div>
                            <strong><?= htmlspecialchars($comment['author_name']); ?></strong>
                            <span style="color: #64748b; font-size: 0.875rem;">&lt;<?= htmlspecialchars($comment['author_email']); ?>&gt;</span>
                        </div>
                        <span style="color: #64748b; font-size: 0.875rem;">
                            <?= date('M j, Y \a\t g:ia', strtotime($comment['created_at'])); ?>
                        </span>
                    </div>
                    <p style="margin: 0.5rem 0; background: #f8fafc; padding: 0.75rem; border-radius: 0.5rem;">
                        <?= nl2br(htmlspecialchars($comment['content'])); ?>
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                        <a href="/blog/<?= htmlspecialchars($comment['post_slug']); ?>" target="_blank" style="color: #667eea; font-size: 0.875rem;">
                            On: <?= htmlspecialchars($comment['post_title']); ?>
                        </a>
                        <div class="table-actions">
                            <?php if ($statusFilter !== 'approved'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $comment['id']; ?>">
                                    <button type="submit" name="approve" class="btn btn-sm btn-success">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($statusFilter !== 'spam'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $comment['id']; ?>">
                                    <button type="submit" name="spam" class="btn btn-sm btn-outline">Spam</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this comment?');">
                                <input type="hidden" name="id" value="<?= $comment['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.filter-tabs {
    display: flex;
    gap: 0.25rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 0.5rem;
    width: fit-content;
}
.filter-tab {
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: #64748b;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
}
.filter-tab:hover {
    color: #1e293b;
}
.filter-tab.active {
    background: #fff;
    color: #1e293b;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
