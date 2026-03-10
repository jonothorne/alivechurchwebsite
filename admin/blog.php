<?php
$page_title = 'Blog Posts';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Post deleted successfully.';
}

// Handle status change
if (isset($_POST['publish']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'published', published_at = COALESCE(published_at, NOW()) WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Post published successfully.';
}

if (isset($_POST['unpublish']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'draft' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Post unpublished.';
}

// Handle feature toggle
if (isset($_POST['feature']) && isset($_POST['id'])) {
    // First, unfeature all posts
    $pdo->exec("UPDATE blog_posts SET is_featured = 0");
    // Then feature the selected one
    $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = 1 WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Post set as featured.';
}

if (isset($_POST['unfeature']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = 0 WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Post unfeatured.';
}

// Get filter
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryFilter;
}

$whereClause = implode(' AND ', $where);

// Get posts
$sql = "SELECT p.*, c.name as category_name, u.full_name as author_name,
               (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND status = 'pending') as pending_comments
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE $whereClause
        ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name")->fetchAll();

// Get counts
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn(),
    'draft' => $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn(),
];
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Manage Blog Posts</h3>
        <div class="admin-card-actions">
            <a href="/admin/blog/categories" class="btn btn-outline btn-sm">Categories</a>
            <a href="/admin/blog/comments" class="btn btn-outline btn-sm">Comments</a>
            <a href="/admin/blog/edit" class="btn btn-primary btn-sm">+ New Post</a>
        </div>
    </div>

    <!-- Filters -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
        <div class="admin-filter-tabs">
            <a href="/admin/blog" class="admin-filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All (<?= $counts['all']; ?>)</a>
            <a href="/admin/blog?status=published" class="admin-filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published (<?= $counts['published']; ?>)</a>
            <a href="/admin/blog?status=draft" class="admin-filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts (<?= $counts['draft']; ?>)</a>
        </div>
        <select onchange="window.location.href='/admin/blog?category=' + this.value" style="padding: 0.5rem 1rem; border-radius: var(--radius-lg); border: 1px solid var(--color-border-strong); background: var(--color-bg-elevated); color: var(--color-text);">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id']; ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($posts)): ?>
        <div class="admin-empty-state">
            <p>No posts yet. <a href="/admin/blog/edit">Create your first post</a></p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($posts as $post): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?php if ($post['is_featured']): ?>
                                <span class="admin-featured-star" title="Featured Post">&#9733;</span>
                            <?php endif; ?>
                            <?= htmlspecialchars($post['title']); ?>
                            <?php if ($post['pending_comments'] > 0): ?>
                                <span class="admin-badge admin-badge-warning"><?= $post['pending_comments']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($post['category_name']): ?>
                                <span><?= htmlspecialchars($post['category_name']); ?></span> ·
                            <?php endif; ?>
                            <?php if ($post['author_name']): ?>
                                <?= htmlspecialchars($post['author_name']); ?> ·
                            <?php endif; ?>
                            <?php if ($post['published_at']): ?>
                                <?= date('M j, Y', strtotime($post['published_at'])); ?>
                            <?php else: ?>
                                <span class="admin-muted">Not published</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-status">
                        <?php if ($post['status'] === 'published'): ?>
                            <span class="admin-badge admin-badge-success">Live</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-secondary">Draft</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-post-actions">
                        <?php if ($post['is_featured']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                <button type="submit" name="unfeature" class="btn btn-xs btn-featured" title="Remove from featured">&#9733;</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                <button type="submit" name="feature" class="btn btn-xs btn-outline" title="Set as featured">&#9734;</button>
                            </form>
                        <?php endif; ?>
                        <a href="/admin/blog/edit?id=<?= $post['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <a href="/blog/<?= htmlspecialchars($post['slug']); ?>" target="_blank" class="btn btn-xs btn-outline">View</a>
                        <?php if ($post['status'] === 'draft'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                <button type="submit" name="publish" class="btn btn-xs btn-success">Publish</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                <button type="submit" name="unpublish" class="btn btn-xs btn-outline">Unpub</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete?');">
                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                            <button type="submit" name="delete" class="btn btn-xs btn-danger">×</button>
                        </form>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
