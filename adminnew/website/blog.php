<?php
/**
 * Blog Management - New Admin
 */
$page_title = 'Blog Posts';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

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
    $pdo->exec("UPDATE blog_posts SET is_featured = 0");
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

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Blog Posts</h1>
        <p class="admin-page-subtitle"><?= $counts['all']; ?> total posts</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/blog/categories" class="admin-btn admin-btn-secondary">Categories</a>
        <a href="/adminnew/blog/comments" class="admin-btn admin-btn-secondary">Comments</a>
        <a href="/adminnew/blog/edit" class="admin-btn admin-btn-primary">+ New Post</a>
    </div>
</div>

<div class="admin-card">
    <!-- Filters -->
    <div class="admin-card-header" style="flex-wrap: wrap; gap: 1rem;">
        <div class="admin-filter-tabs">
            <a href="/adminnew/blog" class="admin-filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All (<?= $counts['all']; ?>)</a>
            <a href="/adminnew/blog?status=published" class="admin-filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published (<?= $counts['published']; ?>)</a>
            <a href="/adminnew/blog?status=draft" class="admin-filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts (<?= $counts['draft']; ?>)</a>
        </div>
        <select onchange="window.location.href=this.value" class="admin-form-select" style="width: auto;">
            <option value="/adminnew/blog" <?= empty($categoryFilter) ? 'selected' : ''; ?>>All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="/adminnew/blog?category=<?= $cat['id']; ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($posts)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <h3 class="admin-empty-title">No posts yet</h3>
            <p class="admin-empty-text">Create your first blog post to get started.</p>
            <a href="/adminnew/blog/edit" class="admin-btn admin-btn-primary">+ New Post</a>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php if ($post['is_featured']): ?>
                                        <span style="color: var(--admin-warning);" title="Featured Post">&#9733;</span>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($post['title']); ?></strong>
                                    <?php if ($post['pending_comments'] > 0): ?>
                                        <span class="admin-badge admin-badge-warning"><?= $post['pending_comments']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($post['category_name'] ?? '—'); ?></td>
                            <td><?= htmlspecialchars($post['author_name'] ?? '—'); ?></td>
                            <td>
                                <?php if ($post['published_at']): ?>
                                    <?= date('M j, Y', strtotime($post['published_at'])); ?>
                                <?php else: ?>
                                    <span class="admin-text-muted">Not published</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($post['status'] === 'published'): ?>
                                    <span class="admin-badge admin-badge-success">Live</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <?php if ($post['is_featured']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                            <button type="submit" name="unfeature" class="admin-btn admin-btn-sm admin-btn-secondary" title="Remove from featured" style="color: var(--admin-warning);">&#9733;</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                            <button type="submit" name="feature" class="admin-btn admin-btn-sm admin-btn-secondary" title="Set as featured">&#9734;</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="/adminnew/blog/edit/<?= $post['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                    <a href="/blog/<?= htmlspecialchars($post['slug']); ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                                    <?php if ($post['status'] === 'draft'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                            <button type="submit" name="publish" class="admin-btn admin-btn-sm admin-btn-success">Publish</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                            <button type="submit" name="unpublish" class="admin-btn admin-btn-sm admin-btn-secondary">Unpub</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this post?')">
                                        <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                        <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
.admin-filter-tab:hover {
    color: var(--admin-text);
}
.admin-filter-tab.active {
    background: var(--admin-card-bg);
    color: var(--admin-text);
    box-shadow: var(--admin-shadow-sm);
}
.admin-badge-secondary {
    background: var(--admin-bg);
    color: var(--admin-text-muted);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
