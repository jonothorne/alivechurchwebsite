<?php
/**
 * Pages Management - New Admin
 */
$page_title = 'Pages';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/cms/TemplateEngine.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("SELECT slug FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();

    if ($page) {
        $stmt = $pdo->prepare("DELETE FROM content_blocks WHERE page_slug = ?");
        $stmt->execute([$page['slug']]);

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
            $stmt = $pdo->prepare("UPDATE pages SET slug = ?, title = ?, meta_description = ?, template = ?, layout = ?, hero_style = ?, published = ? WHERE id = ?");
            $stmt->execute([$slug, $title, $meta_description, $template, $layout, $hero_style, $published, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'page', $id, 'Updated page: ' . $title);
            $success = 'Page updated successfully';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $error = 'A page with this URL slug already exists';
            } else {
                $stmt = $pdo->prepare("INSERT INTO pages (slug, title, meta_description, template, layout, hero_style, published, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$slug, $title, $meta_description, $template, $layout, $hero_style, $published, $_SESSION['admin_user_id']]);
                $new_id = $pdo->lastInsertId();
                log_activity($_SESSION['admin_user_id'], 'create', 'page', $new_id, 'Created page: ' . $title);
                $success = 'Page created successfully. <a href="/' . htmlspecialchars($slug) . '" target="_blank">Click here to start editing</a>';
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

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Pages</h1>
        <p class="admin-page-subtitle"><?= count($pages); ?> pages</p>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><?= $edit_page ? 'Edit' : 'Add'; ?> Page</h3>
        <?php if ($edit_page): ?>
            <div style="display: flex; gap: 0.5rem;">
                <a href="/adminnew/pages" class="admin-btn admin-btn-sm admin-btn-secondary">Cancel</a>
                <a href="/<?= htmlspecialchars($edit_page['slug']); ?>" class="admin-btn admin-btn-sm admin-btn-primary" target="_blank">Edit Content</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="admin-card-body">
        <form method="post">
            <?= csrf_field(); ?>
            <?php if ($edit_page): ?>
                <input type="hidden" name="id" value="<?= $edit_page['id']; ?>">
            <?php endif; ?>

            <div class="page-form-grid">
                <div class="admin-form-group">
                    <label class="admin-form-label">Title *</label>
                    <input type="text" name="title" class="admin-form-input" value="<?= htmlspecialchars($edit_page['title'] ?? ''); ?>" required placeholder="Page Title">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">URL Slug *</label>
                    <input type="text" name="slug" class="admin-form-input" value="<?= htmlspecialchars($edit_page['slug'] ?? ''); ?>" required pattern="[a-z0-9\-]+" placeholder="page-url">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Template</label>
                    <select name="template" class="admin-form-select">
                        <?php foreach ($templates as $key => $tpl): ?>
                            <option value="<?= $key; ?>" <?= ($edit_page['template'] ?? 'default') === $key ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($tpl['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Status</label>
                    <select name="published" class="admin-form-select">
                        <option value="1" <?= ($edit_page['published'] ?? 1) ? 'selected' : ''; ?>>Live</option>
                        <option value="0" <?= !($edit_page['published'] ?? 1) ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
            </div>

            <div class="page-form-grid-2">
                <div class="admin-form-group">
                    <label class="admin-form-label">Meta Description</label>
                    <input type="text" name="meta_description" class="admin-form-input" value="<?= htmlspecialchars($edit_page['meta_description'] ?? ''); ?>" placeholder="Brief description for search engines (150-160 chars)">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Hero Style</label>
                    <select name="hero_style" class="admin-form-select">
                        <?php foreach ($heroStyles as $key => $name): ?>
                            <option value="<?= $key; ?>" <?= ($edit_page['hero_style'] ?? 'standard') === $key ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="admin-btn admin-btn-primary"><?= $edit_page ? 'Update Page' : 'Create Page'; ?></button>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">All Pages</h3>
    </div>

    <?php if (empty($pages)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <h3 class="admin-empty-title">No pages yet</h3>
            <p class="admin-empty-text">Create your first page above.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Template</th>
                        <th>Updated</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($page['title']); ?></strong>
                                <br><code style="font-size: 0.75rem; color: var(--admin-text-muted);">/<?= htmlspecialchars($page['slug']); ?></code>
                            </td>
                            <td><?= htmlspecialchars($templates[$page['template']]['name'] ?? $page['template']); ?></td>
                            <td><?= date('M j, Y', strtotime($page['updated_at'] ?? $page['created_at'])); ?></td>
                            <td>
                                <?php if ($page['published']): ?>
                                    <span class="admin-badge admin-badge-success">Live</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="/<?= htmlspecialchars($page['slug']); ?>" class="admin-btn admin-btn-sm admin-btn-primary" target="_blank">Edit</a>
                                    <a href="/adminnew/pages?edit=<?= $page['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Settings</a>
                                    <a href="/adminnew/pages?delete=<?= $page['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this page?')">×</a>
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
.page-form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 120px;
    gap: 1rem;
    margin-bottom: 1rem;
}
.page-form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 200px;
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 768px) {
    .page-form-grid,
    .page-form-grid-2 {
        grid-template-columns: 1fr;
    }
}
.admin-badge-secondary {
    background: var(--admin-bg);
    color: var(--admin-text-muted);
}
.admin-alert {
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}
.admin-alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--admin-success);
    border: 1px solid var(--admin-success);
}
.admin-alert a {
    color: inherit;
    font-weight: 600;
}
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
