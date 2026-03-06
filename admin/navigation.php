<?php
$page_title = 'Navigation Menu';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

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
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= $edit_item ? 'Edit' : 'Add'; ?> Menu Item</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_item): ?>
            <input type="hidden" name="id" value="<?= $edit_item['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Label</label>
            <input type="text" name="label" value="<?= htmlspecialchars($edit_item['label'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>URL</label>
            <input type="text" name="url" value="<?= htmlspecialchars($edit_item['url'] ?? ''); ?>" required>
            <div class="form-help">Example: /about or https://external.com</div>
        </div>

        <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="menu_order" value="<?= $edit_item['menu_order'] ?? 0; ?>">
        </div>

        <div class="form-group">
            <label>CSS Class (optional)</label>
            <input type="text" name="css_class" value="<?= htmlspecialchars($edit_item['css_class'] ?? ''); ?>">
            <div class="form-help">Examples: nav-link--cta, nav-link--ghost</div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="visible" value="1" <?= ($edit_item['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                <span>Visible in menu</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Menu Item</button>
            <?php if ($edit_item): ?>
                <a href="/admin/navigation.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>Navigation Menu</h2>
    </div>

    <?php if (empty($nav_items)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🧭</div>
            <p>No menu items yet</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
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
                            <td><code><?= htmlspecialchars($item['url']); ?></code></td>
                            <td><?= htmlspecialchars($item['css_class']); ?></td>
                            <td>
                                <?php if ($item['visible']): ?>
                                    <span class="badge badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $item['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="?delete=<?= $item['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
