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
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Manage Blog Posts</h2>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/admin/blog/categories" class="btn btn-outline">Categories</a>
            <a href="/admin/blog/comments" class="btn btn-outline">Comments</a>
            <a href="/admin/blog/edit" class="btn btn-primary">+ New Post</a>
        </div>
    </div>

    <!-- Filters -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div class="filter-tabs">
            <a href="/admin/blog" class="filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All (<?= $counts['all']; ?>)</a>
            <a href="/admin/blog?status=published" class="filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published (<?= $counts['published']; ?>)</a>
            <a href="/admin/blog?status=draft" class="filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts (<?= $counts['draft']; ?>)</a>
        </div>
        <select onchange="window.location.href='/admin/blog?category=' + this.value" style="padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #cbd5e1;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id']; ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
            <h3>No Blog Posts Yet</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Start sharing your church's story with the world.</p>
            <a href="/admin/blog/edit" class="btn btn-primary">Create First Post</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($post['title']); ?></strong>
                                <?php if ($post['pending_comments'] > 0): ?>
                                    <span class="badge badge-warning" style="margin-left: 0.5rem;"><?= $post['pending_comments']; ?> pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $post['category_name'] ? htmlspecialchars($post['category_name']) : '<span style="color: #94a3b8;">—</span>'; ?></td>
                            <td><?= $post['author_name'] ? htmlspecialchars($post['author_name']) : '<span style="color: #94a3b8;">—</span>'; ?></td>
                            <td>
                                <?php if ($post['status'] === 'published'): ?>
                                    <span class="badge badge-success">Published</span>
                                <?php elseif ($post['status'] === 'draft'): ?>
                                    <span class="badge badge-secondary">Draft</span>
                                <?php else: ?>
                                    <span class="badge"><?= ucfirst($post['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($post['published_at']): ?>
                                    <?= date('M j, Y', strtotime($post['published_at'])); ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Not published</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="/blog/<?= htmlspecialchars($post['slug']); ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                    <a href="/admin/blog/edit?id=<?= $post['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <?php if ($post['status'] === 'draft'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                            <button type="submit" name="publish" class="btn btn-sm btn-success">Publish</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                            <button type="submit" name="unpublish" class="btn btn-sm btn-outline">Unpublish</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this post?');">
                                        <input type="hidden" name="id" value="<?= $post['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
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
