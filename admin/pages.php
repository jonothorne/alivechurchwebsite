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
    <div class="alert alert-success"><?= $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 2rem;">
    <div style="padding: 1.5rem;">
        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            Inline Page Editing
        </h3>
        <p style="margin: 0; opacity: 0.95; font-size: 0.9375rem;">
            Create pages here, then click <strong>"Edit Content"</strong> to edit the page content directly on the live site. Just click any text to start editing!
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><?= $edit_page ? 'Edit' : 'Add New'; ?> Page</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_page): ?>
            <input type="hidden" name="id" value="<?= $edit_page['id']; ?>">
        <?php endif; ?>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Page Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($edit_page['title'] ?? ''); ?>" required>
                <div class="form-help">The title shown in browser tabs and search results</div>
            </div>

            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($edit_page['slug'] ?? ''); ?>" required pattern="[a-z0-9\-]+" placeholder="about-us">
                <div class="form-help">Example: "about-us" becomes /about-us</div>
            </div>
        </div>

        <div class="form-group">
            <label>Meta Description</label>
            <textarea name="meta_description" rows="2"><?= htmlspecialchars($edit_page['meta_description'] ?? ''); ?></textarea>
            <div class="form-help">Brief description for search engines (150-160 characters)</div>
        </div>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label>Template</label>
                <select name="template">
                    <?php foreach ($templates as $key => $tpl): ?>
                        <option value="<?= $key; ?>" <?= ($edit_page['template'] ?? 'default') === $key ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($tpl['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">Page layout template</div>
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
                <div class="form-help">Hero section style</div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="published">
                    <option value="1" <?= ($edit_page['published'] ?? 1) ? 'selected' : ''; ?>>Published</option>
                    <option value="0" <?= !($edit_page['published'] ?? 1) ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary"><?= $edit_page ? 'Update Page' : 'Create Page'; ?></button>
            <?php if ($edit_page): ?>
                <a href="/admin/pages" class="btn btn-outline">Cancel</a>
                <a href="/<?= htmlspecialchars($edit_page['slug']); ?>" class="btn btn-outline" target="_blank">Edit Content</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Pages</h2>
        <span style="color: #64748b; font-size: 0.875rem;"><?= count($pages); ?> pages</span>
    </div>

    <?php if (empty($pages)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📄</div>
            <h3>No pages yet</h3>
            <p>Create your first page above</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>URL</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($page['title']); ?></strong></td>
                            <td><code>/<?= htmlspecialchars($page['slug']); ?></code></td>
                            <td>
                                <span class="badge"><?= htmlspecialchars($templates[$page['template']]['name'] ?? $page['template']); ?></span>
                            </td>
                            <td>
                                <?php if ($page['published']): ?>
                                    <span class="badge badge-success">Published</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($page['updated_at'] ?? $page['created_at'])); ?></td>
                            <td class="table-actions">
                                <a href="/<?= htmlspecialchars($page['slug']); ?>" class="btn btn-sm btn-primary" target="_blank">Edit Content</a>
                                <a href="?edit=<?= $page['id']; ?>" class="btn btn-sm btn-outline">Settings</a>
                                <a href="?delete=<?= $page['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this page?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card-grid" style="grid-template-columns: 1fr 1fr; margin-top: 2rem;">
    <div class="card" style="background: #f0fdf4; border: 1px solid #86efac;">
        <div style="padding: 1.5rem;">
            <h3 style="color: #166534; margin-bottom: 0.5rem; font-size: 1rem;">Template Guide</h3>
            <ul style="color: #166534; margin: 0; padding-left: 1.25rem; font-size: 0.875rem;">
                <li><strong>Default:</strong> Standard page with centered content</li>
                <li><strong>Full Width:</strong> Full-width hero and content sections</li>
                <li><strong>Sidebar:</strong> Main content with right sidebar</li>
                <li><strong>Landing:</strong> Marketing page with features grid</li>
                <li><strong>Blank:</strong> Empty canvas for custom layouts</li>
            </ul>
        </div>
    </div>

    <div class="card" style="background: #eff6ff; border: 1px solid #93c5fd;">
        <div style="padding: 1.5rem;">
            <h3 style="color: #1e40af; margin-bottom: 0.5rem; font-size: 1rem;">Keyboard Shortcuts</h3>
            <ul style="color: #1e40af; margin: 0; padding-left: 1.25rem; font-size: 0.875rem;">
                <li><strong>Ctrl + S:</strong> Save current edit</li>
                <li><strong>Ctrl + B:</strong> Bold text</li>
                <li><strong>Ctrl + I:</strong> Italic text</li>
                <li><strong>Escape:</strong> Cancel editing</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
