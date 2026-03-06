<?php
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

<div style="margin-bottom: 1.5rem;">
    <a href="/admin/blog" style="color: #667eea; text-decoration: none;">&larr; Back to Blog Posts</a>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card-grid" style="grid-template-columns: 1fr 1fr;">
    <!-- Add/Edit Form -->
    <div class="card">
        <div class="card-header">
            <h2>Add Category</h2>
        </div>
        <form method="POST">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g., Devotionals">
            </div>
            <div class="form-group">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" placeholder="auto-generated">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2" placeholder="Brief description..."></textarea>
            </div>
            <button type="submit" name="save" class="btn btn-primary">Add Category</button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="card">
        <div class="card-header">
            <h2>Categories</h2>
        </div>
        <?php if (empty($categories)): ?>
            <p style="color: #64748b;">No categories yet.</p>
        <?php else: ?>
            <table>
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
                                <br><code style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($cat['slug']); ?></code>
                            </td>
                            <td><?= $cat['post_count']; ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                    <input type="hidden" name="id" value="<?= $cat['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
