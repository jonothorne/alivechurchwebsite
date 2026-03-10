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
            $stmt = $pdo->prepare("UPDATE ministries SET title = ?, summary = ?, description = ?, image_url = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $summary, $description, $image_url, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'ministry', $id, 'Updated ministry: ' . $title);
            $success = 'Ministry updated successfully';
        } else {
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

// Count stats
$total = count($ministries);
$visible_count = count(array_filter($ministries, fn($m) => $m['visible']));
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Ministries</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $total; ?></strong> Total</span>
        <span class="admin-inline-stat"><strong><?= $visible_count; ?></strong> Visible</span>
    </div>
</div>

<!-- Form Card -->
<div class="admin-card">
    <details <?= $edit_ministry ? 'open' : ''; ?>>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3><?= $edit_ministry ? 'Edit Ministry' : '+ Add Ministry'; ?></h3>
            <?php if ($edit_ministry): ?>
                <a href="/admin/ministries.php" class="btn btn-xs btn-outline">Cancel</a>
            <?php endif; ?>
        </summary>
        <form method="post" class="admin-compact-form">
            <?= csrf_field(); ?>
            <?php if ($edit_ministry): ?>
                <input type="hidden" name="id" value="<?= $edit_ministry['id']; ?>">
            <?php endif; ?>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Ministry Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($edit_ministry['title'] ?? ''); ?>" required placeholder="e.g., Youth Ministry">
                </div>
                <div class="admin-form-group">
                    <label>Image URL</label>
                    <input type="text" name="image_url" value="<?= htmlspecialchars($edit_ministry['image_url'] ?? ''); ?>" placeholder="/uploads/...">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Summary</label>
                <input type="text" name="summary" value="<?= htmlspecialchars($edit_ministry['summary'] ?? ''); ?>" required placeholder="Short description for cards">
            </div>

            <div class="admin-form-group">
                <label>Full Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($edit_ministry['description'] ?? ''); ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group" style="flex: 0 0 100px;">
                    <label>Order</label>
                    <input type="number" name="display_order" value="<?= $edit_ministry['display_order'] ?? 0; ?>" min="0">
                </div>
                <div class="admin-form-group" style="flex: 0 0 auto; align-self: flex-end;">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="visible" value="1" <?= ($edit_ministry['visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Visible</span>
                    </label>
                </div>
                <div class="admin-form-group" style="flex: 1; align-self: flex-end; text-align: right;">
                    <button type="submit" class="btn btn-sm btn-primary">Save Ministry</button>
                </div>
            </div>
        </form>
    </details>
</div>

<!-- Ministries List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Ministries</h3>
        <span class="admin-muted-text"><?= $total; ?> ministries</span>
    </div>

    <?php if (empty($ministries)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">⛪</span>
            <p>No ministries yet. Add one above.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($ministries as $ministry): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($ministry['title']); ?>
                            <?php if ($ministry['visible']): ?>
                                <span class="admin-badge admin-badge-success">Visible</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Hidden</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?= htmlspecialchars(substr($ministry['summary'], 0, 80)); ?><?= strlen($ministry['summary']) > 80 ? '...' : ''; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?edit=<?= $ministry['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <a href="?delete=<?= $ministry['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
