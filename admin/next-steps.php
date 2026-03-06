<?php
$page_title = 'Next Steps';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM next_steps WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'next_step', $id, 'Deleted next step');
        $success = 'Next step deleted successfully';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $copy = $_POST['copy'];
        $link = $_POST['link'];
        $icon = $_POST['icon'];
        $display_order = (int)$_POST['display_order'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE next_steps SET title = ?, copy = ?, link = ?, icon = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $copy, $link, $icon, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'next_step', $id, 'Updated next step: ' . $title);
            $success = 'Next step updated successfully';
        } else {
            $stmt = $pdo->prepare("INSERT INTO next_steps (title, copy, link, icon, display_order, visible) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $copy, $link, $icon, $display_order, $visible]);
            $new_id = $pdo->lastInsertId();
            log_activity($_SESSION['admin_user_id'], 'create', 'next_step', $new_id, 'Created next step: ' . $title);
            $success = 'Next step created successfully';
        }
    }
}

// Fetch all next steps
$steps = $pdo->query("SELECT * FROM next_steps ORDER BY display_order ASC")->fetchAll();

// Get step for editing
$edit_step = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM next_steps WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_step = $stmt->fetch();
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
        <h2><?= $edit_step ? 'Edit' : 'Add New'; ?> Next Step</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_step): ?>
            <input type="hidden" name="id" value="<?= $edit_step['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($edit_step['title'] ?? ''); ?>" required>
            <div class="form-help">The name of this step (e.g., "Visit a Service", "Join a Group")</div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="copy" rows="3"><?= htmlspecialchars($edit_step['copy'] ?? ''); ?></textarea>
            <div class="form-help">Brief explanation of what this step involves</div>
        </div>

        <div class="form-group">
            <label>Link URL (optional)</label>
            <input type="text" name="link" value="<?= htmlspecialchars($edit_step['link'] ?? ''); ?>" placeholder="/visit or https://example.com">
            <div class="form-help">Where the "Take This Step" button should link to</div>
        </div>

        <div class="form-group">
            <label>Icon (optional)</label>
            <input type="text" name="icon" value="<?= htmlspecialchars($edit_step['icon'] ?? ''); ?>" placeholder="🚶 or fas fa-church">
            <div class="form-help">Emoji or CSS class for icon display</div>
        </div>

        <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="display_order" value="<?= $edit_step['display_order'] ?? 0; ?>" min="0">
            <div class="form-help">Lower numbers appear first</div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="visible" value="1" <?= ($edit_step['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                <span>Visible on website</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Next Step</button>
            <?php if ($edit_step): ?>
                <a href="/admin/next-steps.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Next Steps</h2>
    </div>

    <?php if (empty($steps)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🚶</div>
            <h3>No next steps yet</h3>
            <p>Create your first step above</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Link</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($steps as $step): ?>
                        <tr>
                            <td><strong><?= $step['display_order']; ?></strong></td>
                            <td><strong><?= htmlspecialchars($step['title']); ?></strong></td>
                            <td><?= htmlspecialchars(substr($step['copy'], 0, 60)); ?><?= strlen($step['copy']) > 60 ? '...' : ''; ?></td>
                            <td>
                                <?php if ($step['link']): ?>
                                    <code><?= htmlspecialchars($step['link']); ?></code>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($step['visible']): ?>
                                    <span class="badge badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $step['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="?delete=<?= $step['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
