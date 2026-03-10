<?php
$page_title = 'Pages';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/cms/TemplateEngine.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Get page slug before deleting
    $stmt = $pdo->prepare("SELECT slug FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();

    if ($page) {
        // Delete associated content blocks
        $stmt = $pdo->prepare("DELETE FROM content_blocks WHERE page_slug = ?");
        $stmt->execute([$page['slug']]);

        // Delete the page
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
        if ($stmt->execute([$id])) {
            log_activity($_SESSION['admin_user_id'], 'delete', 'page', $id, 'Deleted page: ' . $page['slug']);
            $success = 'Page deleted successfully';
        }
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $slug = trim(strtolower(preg_replace('/[^a-z0-9\-]/', '-', $_POST['slug'])));
        $title = $_POST['title'];
        $meta_description = $_POST['meta_description'];
        $template = $_POST['template'];
        $layout = $_POST['layout'] ?? 'default';
        $hero_style = $_POST['hero_style'] ?? 'standard';
        $published = isset($_POST['published']) ? 1 : 0;

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE pages SET slug = ?, title = ?, meta_description = ?, template = ?, layout = ?, hero_style = ?, published = ? WHERE id = ?");
            $stmt->execute([$slug, $title, $meta_description, $template, $layout, $hero_style, $published, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'page', $id, 'Updated page: ' . $title);
            $success = 'Page updated successfully';
        } else {
            // Check if slug exists
            $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $error = 'A page with this URL slug already exists';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO pages (slug, title, meta_description, template, layout, hero_style, published, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$slug, $title, $meta_description, $template, $layout, $hero_style, $published, $_SESSION['admin_user_id']]);
                $new_id = $pdo->lastInsertId();
                log_activity($_SESSION['admin_user_id'], 'create', 'page', $new_id, 'Created page: ' . $title);
                $success = 'Page created successfully. <a href="/' . htmlspecialchars($slug) . '">Click here to start editing</a>';
            }
        }
    }
}

// Fetch all pages
$pages = $pdo->query("SELECT p.*, u.username FROM pages p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC")->fetchAll();

// Get page for editing
$edit_page = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_page = $stmt->fetch();
}

// Get available templates
$templates = TemplateEngine::getTemplates();
$heroStyles = TemplateEngine::getHeroStyles();
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3><?= $edit_page ? 'Edit' : 'Add'; ?> Page</h3>
        <?php if ($edit_page): ?>
            <div class="admin-card-actions">
                <a href="/admin/pages" class="btn btn-sm btn-outline">Cancel</a>
                <a href="/<?= htmlspecialchars($edit_page['slug']); ?>" class="btn btn-sm btn-primary" target="_blank">Edit Content</a>
            </div>
        <?php endif; ?>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_page): ?>
            <input type="hidden" name="id" value="<?= $edit_page['id']; ?>">
        <?php endif; ?>

        <!-- Row 1: Title, Slug, Template, Status -->
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 100px; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($edit_page['title'] ?? ''); ?>" required placeholder="Page Title">
            </div>
            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($edit_page['slug'] ?? ''); ?>" required pattern="[a-z0-9\-]+" placeholder="page-url">
            </div>
            <div class="form-group">
                <label>Template</label>
                <select name="template">
                    <?php foreach ($templates as $key => $tpl): ?>
                        <option value="<?= $key; ?>" <?= ($edit_page['template'] ?? 'default') === $key ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($tpl['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="published">
                    <option value="1" <?= ($edit_page['published'] ?? 1) ? 'selected' : ''; ?>>Live</option>
                    <option value="0" <?= !($edit_page['published'] ?? 1) ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>
        </div>

        <!-- Row 2: Meta + Hero -->
        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 0.5rem; align-items: end; margin-bottom: 0.75rem;">
            <div class="form-group">
                <label>Meta Description</label>
                <input type="text" name="meta_description" value="<?= htmlspecialchars($edit_page['meta_description'] ?? ''); ?>" placeholder="Brief description for search engines (150-160 chars)">
            </div>
            <div class="form-group">
                <label>Hero Style</label>
                <select name="hero_style">
                    <?php foreach ($heroStyles as $key => $name): ?>
                        <option value="<?= $key; ?>" <?= ($edit_page['hero_style'] ?? 'standard') === $key ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?= $edit_page ? 'Update' : 'Create'; ?></button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Pages</h3>
        <span style="color: var(--color-text-muted); font-size: 0.875rem;"><?= count($pages); ?> pages</span>
    </div>

    <?php if (empty($pages)): ?>
        <p class="admin-muted-text">No pages yet. Create one above.</p>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($pages as $page): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($page['title']); ?>
                            <code style="font-size: 0.65rem; color: var(--color-text-muted);">/<?= htmlspecialchars($page['slug']); ?></code>
                        </div>
                        <div class="admin-post-meta">
                            <?= htmlspecialchars($templates[$page['template']]['name'] ?? $page['template']); ?> ·
                            <?= date('M j', strtotime($page['updated_at'] ?? $page['created_at'])); ?>
                        </div>
                    </div>
                    <div class="admin-post-status">
                        <?php if ($page['published']): ?>
                            <span class="admin-badge admin-badge-success">Live</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-secondary">Draft</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-post-actions">
                        <a href="/<?= htmlspecialchars($page['slug']); ?>" class="btn btn-xs btn-primary" target="_blank">Edit</a>
                        <a href="?edit=<?= $page['id']; ?>" class="btn btn-xs btn-outline">Settings</a>
                        <a href="?delete=<?= $page['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete this page?')">×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
