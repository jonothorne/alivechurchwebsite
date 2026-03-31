<?php
/**
 * Blog Categories - New Admin
 */
$page_title = 'Blog Categories';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle delete
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM blog_categories WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Category deleted.';
}

// Handle add/edit
if (isset($_POST['save'])) {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']) ?: strtolower(preg_replace('/[^a-z0-9-]+/', '-', $name));
    $description = trim($_POST['description']);

    if (empty($name)) {
        $error_message = 'Name is required.';
    } else {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE blog_categories SET name = ?, slug = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $description, $id]);
            $success_message = 'Category updated.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO blog_categories (name, slug, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $description]);
            $success_message = 'Category added.';
        }
    }
}

// Get categories
$categories = $pdo->query("SELECT c.*, COUNT(p.id) as post_count
                           FROM blog_categories c
                           LEFT JOIN blog_posts p ON c.id = p.category_id
                           GROUP BY c.id
                           ORDER BY c.name")->fetchAll();
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Blog Categories</h1>
        <p class="admin-page-subtitle">Organize your blog posts</p>
    </div>
    <a href="/adminnew/blog" class="admin-btn admin-btn-secondary">&larr; Back to Blog</a>
</div>

<div class="admin-grid admin-grid-2">
    <!-- Add Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Add Category</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div class="admin-form-group">
                    <label class="admin-form-label">Name *</label>
                    <input type="text" name="name" class="admin-form-input" required placeholder="e.g., Devotionals">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Slug</label>
                    <input type="text" name="slug" class="admin-form-input" placeholder="auto-generated">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Description</label>
                    <textarea name="description" class="admin-form-textarea" rows="2" placeholder="Brief description..."></textarea>
                </div>
                <button type="submit" name="save" class="admin-btn admin-btn-primary">Add Category</button>
            </form>
        </div>
    </div>

    <!-- Categories List -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Categories</h3>
        </div>
        <?php if (empty($categories)): ?>
            <div class="admin-empty-state">
                <p>No categories yet.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Posts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($cat['name']); ?></strong>
                                    <br><code class="admin-text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($cat['slug']); ?></code>
                                </td>
                                <td><?= $cat['post_count']; ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?')">
                                        <input type="hidden" name="id" value="<?= $cat['id']; ?>">
                                        <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
