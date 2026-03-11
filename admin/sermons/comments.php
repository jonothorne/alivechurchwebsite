<?php
$page_title = 'Sermon Comments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle actions
if (isset($_POST['approve']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE sermon_comments SET status = 'approved' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Comment approved.';
}

if (isset($_POST['spam']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE sermon_comments SET status = 'spam' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Comment marked as spam.';
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM sermon_comments WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Comment deleted.';
}

// Get filter
$statusFilter = $_GET['status'] ?? 'pending';

// Get comments with user data
$stmt = $pdo->prepare("SELECT c.*, s.title as sermon_title, s.slug as sermon_slug,
                              u.full_name as user_full_name, u.avatar as user_avatar, u.avatar_color as user_avatar_color
                       FROM sermon_comments c
                       JOIN sermons s ON c.sermon_id = s.id
                       LEFT JOIN users u ON c.user_id = u.id
                       WHERE c.status = ?
                       ORDER BY c.created_at DESC");
$stmt->execute([$statusFilter]);
$comments = $stmt->fetchAll();

// Get counts
$counts = [
    'pending' => $pdo->query("SELECT COUNT(*) FROM sermon_comments WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM sermon_comments WHERE status = 'approved'")->fetchColumn(),
    'spam' => $pdo->query("SELECT COUNT(*) FROM sermon_comments WHERE status = 'spam'")->fetchColumn(),
];
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/admin/sermons" style="color: #667eea; text-decoration: none;">&larr; Back to Sermons</a>
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
        <a href="/admin/sermons/comments?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : ''; ?>">
            Pending (<?= $counts['pending']; ?>)
        </a>
        <a href="/admin/sermons/comments?status=approved" class="filter-tab <?= $statusFilter === 'approved' ? 'active' : ''; ?>">
            Approved (<?= $counts['approved']; ?>)
        </a>
        <a href="/admin/sermons/comments?status=spam" class="filter-tab <?= $statusFilter === 'spam' ? 'active' : ''; ?>">
            Spam (<?= $counts['spam']; ?>)
        </a>
    </div>

    <?php if (empty($comments)): ?>
        <div class="empty-state" style="padding: 2rem;">
            <p style="color: #64748b;">No <?= $statusFilter; ?> comments.</p>
        </div>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment):
                $displayName = $comment['user_id'] ? ($comment['user_full_name'] ?? $comment['author_name']) : $comment['author_name'];
            ?>
                <div class="comment-item" style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <?php if ($comment['user_id']): ?>
                                <?php if ($comment['user_avatar']): ?>
                                    <img src="<?= htmlspecialchars($comment['user_avatar']); ?>" alt="" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: <?= htmlspecialchars($comment['user_avatar_color'] ?? '#4b2679'); ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.75rem;">
                                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($displayName); ?></strong>
                                <span class="badge badge-success" style="font-size: 0.7rem;">Member</span>
                            <?php else: ?>
                                <strong><?= htmlspecialchars($displayName); ?></strong>
                                <span style="color: #64748b; font-size: 0.875rem;">&lt;<?= htmlspecialchars($comment['author_email'] ?? ''); ?>&gt;</span>
                            <?php endif; ?>
                        </div>
                        <span style="color: #64748b; font-size: 0.875rem;">
                            <?= date('M j, Y \a\t g:ia', strtotime($comment['created_at'])); ?>
                        </span>
                    </div>
                    <p style="margin: 0.5rem 0; background: #f8fafc; padding: 0.75rem; border-radius: 0.5rem;">
                        <?= nl2br(htmlspecialchars($comment['content'])); ?>
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                        <a href="/sermon/<?= htmlspecialchars($comment['sermon_slug']); ?>" target="_blank" style="color: #667eea; font-size: 0.875rem;">
                            On: <?= htmlspecialchars($comment['sermon_title']); ?>
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
