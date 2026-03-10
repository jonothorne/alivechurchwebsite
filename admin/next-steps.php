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

// Count stats
$total = count($steps);
$visible_count = count(array_filter($steps, fn($s) => $s['visible']));
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
        <span class="admin-greeting-text">Next Steps</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $total; ?></strong> Total</span>
        <span class="admin-inline-stat"><strong><?= $visible_count; ?></strong> Visible</span>
    </div>
</div>

<!-- Form Card -->
<div class="admin-card">
    <details <?= $edit_step ? 'open' : ''; ?>>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3><?= $edit_step ? 'Edit Step' : '+ Add Step'; ?></h3>
            <?php if ($edit_step): ?>
                <a href="/admin/next-steps.php" class="btn btn-xs btn-outline">Cancel</a>
            <?php endif; ?>
        </summary>
        <form method="post" class="admin-compact-form">
            <?= csrf_field(); ?>
            <?php if ($edit_step): ?>
                <input type="hidden" name="id" value="<?= $edit_step['id']; ?>">
            <?php endif; ?>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($edit_step['title'] ?? ''); ?>" required placeholder="e.g., Visit a Service">
                </div>
                <div class="admin-form-group" style="flex: 0 0 80px;">
                    <label>Icon</label>
                    <input type="text" name="icon" value="<?= htmlspecialchars($edit_step['icon'] ?? ''); ?>" placeholder="🚶">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="copy" rows="2"><?= htmlspecialchars($edit_step['copy'] ?? ''); ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Link URL</label>
                    <input type="text" name="link" value="<?= htmlspecialchars($edit_step['link'] ?? ''); ?>" placeholder="/visit or https://...">
                </div>
                <div class="admin-form-group" style="flex: 0 0 80px;">
                    <label>Order</label>
                    <input type="number" name="display_order" value="<?= $edit_step['display_order'] ?? 0; ?>" min="0">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group" style="flex: 0 0 auto;">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="visible" value="1" <?= ($edit_step['visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Visible</span>
                    </label>
                </div>
                <div class="admin-form-group" style="flex: 1; text-align: right;">
                    <button type="submit" class="btn btn-sm btn-primary">Save Step</button>
                </div>
            </div>
        </form>
    </details>
</div>

<!-- Steps List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Steps</h3>
        <span class="admin-muted-text"><?= $total; ?> steps</span>
    </div>

    <?php if (empty($steps)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">🚶</span>
            <p>No next steps yet. Add one above.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($steps as $step): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?php if ($step['icon']): ?>
                                <span><?= $step['icon']; ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($step['title']); ?>
                            <?php if ($step['visible']): ?>
                                <span class="admin-badge admin-badge-success">Visible</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Hidden</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($step['link']): ?>
                                <code><?= htmlspecialchars($step['link']); ?></code> ·
                            <?php endif; ?>
                            <?= htmlspecialchars(substr($step['copy'], 0, 50)); ?><?= strlen($step['copy']) > 50 ? '...' : ''; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?edit=<?= $step['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <a href="?delete=<?= $step['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
