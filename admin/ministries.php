<?php
$page_title = 'Ministries';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM ministries WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'ministry', $id, 'Deleted ministry');
        $success = 'Ministry deleted successfully';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $summary = $_POST['summary'];
        $description = $_POST['description'];
        $image_url = $_POST['image_url'];
        $display_order = (int)$_POST['display_order'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE ministries SET title = ?, summary = ?, description = ?, image_url = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $summary, $description, $image_url, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'ministry', $id, 'Updated ministry: ' . $title);
            $success = 'Ministry updated successfully';
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO ministries (title, summary, description, image_url, display_order, visible) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $summary, $description, $image_url, $display_order, $visible]);
            $new_id = $pdo->lastInsertId();
            log_activity($_SESSION['admin_user_id'], 'create', 'ministry', $new_id, 'Created ministry: ' . $title);
            $success = 'Ministry created successfully';
        }
    }
}

// Fetch all ministries
$ministries = $pdo->query("SELECT * FROM ministries ORDER BY display_order ASC")->fetchAll();

// Get ministry for editing
$edit_ministry = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ministries WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_ministry = $stmt->fetch();
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
        <h2><?= $edit_ministry ? 'Edit' : 'Add New'; ?> Ministry</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_ministry): ?>
            <input type="hidden" name="id" value="<?= $edit_ministry['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Ministry Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($edit_ministry['title'] ?? ''); ?>" required>
            <div class="form-help">The name of the ministry (e.g., "Youth Ministry", "Worship Team")</div>
        </div>

        <div class="form-group">
            <label>Summary</label>
            <textarea name="summary" rows="2" required><?= htmlspecialchars($edit_ministry['summary'] ?? ''); ?></textarea>
            <div class="form-help">Short description (1-2 sentences) shown in preview cards</div>
        </div>

        <div class="form-group">
            <label>Full Description</label>
            <textarea name="description" rows="6" class="wysiwyg"><?= htmlspecialchars($edit_ministry['description'] ?? ''); ?></textarea>
            <div class="form-help">Full details about the ministry, what they do, when they meet, etc.</div>
        </div>

        <div class="form-group">
            <label>Image URL</label>
            <input type="text" name="image_url" value="<?= htmlspecialchars($edit_ministry['image_url'] ?? ''); ?>">
            <div class="form-help">Full URL to ministry image (use Media Library or external URL)</div>
        </div>

        <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="display_order" value="<?= $edit_ministry['display_order'] ?? 0; ?>" min="0">
            <div class="form-help">Lower numbers appear first</div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="visible" value="1" <?= ($edit_ministry['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                <span>Visible on website</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Ministry</button>
            <?php if ($edit_ministry): ?>
                <a href="/admin/ministries.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Ministries</h2>
    </div>

    <?php if (empty($ministries)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">⛪</div>
            <h3>No ministries yet</h3>
            <p>Create your first ministry above</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Title</th>
                        <th>Summary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ministries as $ministry): ?>
                        <tr>
                            <td><?= $ministry['display_order']; ?></td>
                            <td><strong><?= htmlspecialchars($ministry['title']); ?></strong></td>
                            <td><?= htmlspecialchars(substr($ministry['summary'], 0, 80)); ?><?= strlen($ministry['summary']) > 80 ? '...' : ''; ?></td>
                            <td>
                                <?php if ($ministry['visible']): ?>
                                    <span class="badge badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $ministry['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="?delete=<?= $ministry['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
