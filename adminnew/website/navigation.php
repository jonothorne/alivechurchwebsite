<?php
/**
 * Navigation Management - New Admin
 */
$page_title = 'Navigation Menu';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM navigation WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'navigation', $id, 'Deleted menu item');
        $success = 'Menu item deleted';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $label = $_POST['label'];
        $url = $_POST['url'];
        $menu_order = (int)$_POST['menu_order'];
        $css_class = $_POST['css_class'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE navigation SET label = ?, url = ?, menu_order = ?, css_class = ?, visible = ? WHERE id = ?");
            $stmt->execute([$label, $url, $menu_order, $css_class, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'navigation', $id, 'Updated menu: ' . $label);
            $success = 'Menu item updated';
        } else {
            $stmt = $pdo->prepare("INSERT INTO navigation (label, url, menu_order, css_class, visible) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$label, $url, $menu_order, $css_class, $visible]);
            log_activity($_SESSION['admin_user_id'], 'create', 'navigation', $pdo->lastInsertId(), 'Created menu: ' . $label);
            $success = 'Menu item created';
        }
    }
}

$nav_items = $pdo->query("SELECT * FROM navigation ORDER BY menu_order")->fetchAll();

$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM navigation WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_item = $stmt->fetch();
}
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Navigation Menu</h1>
        <p class="admin-page-subtitle">Manage site navigation</p>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><?= $edit_item ? 'Edit' : 'Add'; ?> Menu Item</h3>
    </div>
    <div class="admin-card-body">
        <form method="post">
            <?= csrf_field(); ?>
            <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?= $edit_item['id']; ?>">
            <?php endif; ?>

            <div class="admin-form-group">
                <label class="admin-form-label">Label</label>
                <input type="text" name="label" class="admin-form-input" value="<?= htmlspecialchars($edit_item['label'] ?? ''); ?>" required>
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label">URL</label>
                <input type="text" name="url" class="admin-form-input" value="<?= htmlspecialchars($edit_item['url'] ?? ''); ?>" required>
                <small class="admin-text-muted">Example: /about or https://external.com</small>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="admin-form-group">
                    <label class="admin-form-label">Display Order</label>
                    <input type="number" name="menu_order" class="admin-form-input" value="<?= $edit_item['menu_order'] ?? 0; ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">CSS Class (optional)</label>
                    <input type="text" name="css_class" class="admin-form-input" value="<?= htmlspecialchars($edit_item['css_class'] ?? ''); ?>">
                    <small class="admin-text-muted">Examples: nav-link--cta, nav-link--ghost</small>
                </div>
            </div>

            <div class="admin-form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="visible" value="1" <?= ($edit_item['visible'] ?? 1) ? 'checked' : ''; ?>>
                    <span>Visible in menu</span>
                </label>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="admin-btn admin-btn-primary">Save Menu Item</button>
                <?php if ($edit_item): ?>
                    <a href="/adminnew/navigation" class="admin-btn admin-btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Navigation Menu</h3>
    </div>

    <?php if (empty($nav_items)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </div>
            <h3 class="admin-empty-title">No menu items yet</h3>
            <p class="admin-empty-text">Add your first menu item above.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Label</th>
                        <th>URL</th>
                        <th>CSS Class</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nav_items as $item): ?>
                        <tr>
                            <td><?= $item['menu_order']; ?></td>
                            <td><strong><?= htmlspecialchars($item['label']); ?></strong></td>
                            <td><code style="font-size: 0.8125rem;"><?= htmlspecialchars($item['url']); ?></code></td>
                            <td>
                                <?php if ($item['css_class']): ?>
                                    <code style="font-size: 0.75rem;"><?= htmlspecialchars($item['css_class']); ?></code>
                                <?php else: ?>
                                    <span class="admin-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['visible']): ?>
                                    <span class="admin-badge admin-badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="/adminnew/navigation?edit=<?= $item['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                    <a href="/adminnew/navigation?delete=<?= $item['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this menu item?')">Delete</a>
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
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
