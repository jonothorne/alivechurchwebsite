<?php
$page_title = 'Groups';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM groups_list WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'group', $id, 'Deleted group');
        $success = 'Group deleted successfully';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $description = $_POST['description'];
        $schedule = $_POST['schedule'];
        $location = $_POST['location'];
        $image_url = $_POST['image_url'];
        $signup_url = $_POST['signup_url'];
        $category = $_POST['category'];
        $display_order = (int)$_POST['display_order'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE groups_list SET title = ?, description = ?, schedule = ?, location = ?, image_url = ?, signup_url = ?, category = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $description, $schedule, $location, $image_url, $signup_url, $category, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'group', $id, 'Updated group: ' . $title);
            $success = 'Group updated successfully';
        } else {
            $stmt = $pdo->prepare("INSERT INTO groups_list (title, description, schedule, location, image_url, signup_url, category, display_order, visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $schedule, $location, $image_url, $signup_url, $category, $display_order, $visible]);
            $new_id = $pdo->lastInsertId();
            log_activity($_SESSION['admin_user_id'], 'create', 'group', $new_id, 'Created group: ' . $title);
            $success = 'Group created successfully';
        }
    }
}

// Fetch all groups
$groups = $pdo->query("SELECT * FROM groups_list ORDER BY display_order ASC, title ASC")->fetchAll();

// Get group for editing
$edit_group = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM groups_list WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_group = $stmt->fetch();
}

// Count stats
$total = count($groups);
$visible_count = count(array_filter($groups, fn($g) => $g['visible']));
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
        <span class="admin-greeting-text">Groups</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $total; ?></strong> Total</span>
        <span class="admin-inline-stat"><strong><?= $visible_count; ?></strong> Visible</span>
    </div>
</div>

<!-- Form Card -->
<div class="admin-card">
    <details <?= $edit_group ? 'open' : ''; ?>>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3><?= $edit_group ? 'Edit Group' : '+ Add Group'; ?></h3>
            <?php if ($edit_group): ?>
                <a href="/admin/groups.php" class="btn btn-xs btn-outline">Cancel</a>
            <?php endif; ?>
        </summary>
        <form method="post" class="admin-compact-form">
            <?= csrf_field(); ?>
            <?php if ($edit_group): ?>
                <input type="hidden" name="id" value="<?= $edit_group['id']; ?>">
            <?php endif; ?>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Group Name</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($edit_group['title'] ?? ''); ?>" required placeholder="e.g., Young Adults">
                </div>
                <div class="admin-form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">No category</option>
                        <option value="men" <?= ($edit_group['category'] ?? '') === 'men' ? 'selected' : ''; ?>>Men</option>
                        <option value="women" <?= ($edit_group['category'] ?? '') === 'women' ? 'selected' : ''; ?>>Women</option>
                        <option value="youth" <?= ($edit_group['category'] ?? '') === 'youth' ? 'selected' : ''; ?>>Youth</option>
                        <option value="young-adults" <?= ($edit_group['category'] ?? '') === 'young-adults' ? 'selected' : ''; ?>>Young Adults</option>
                        <option value="seniors" <?= ($edit_group['category'] ?? '') === 'seniors' ? 'selected' : ''; ?>>Seniors</option>
                        <option value="families" <?= ($edit_group['category'] ?? '') === 'families' ? 'selected' : ''; ?>>Families</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Schedule</label>
                    <input type="text" name="schedule" value="<?= htmlspecialchars($edit_group['schedule'] ?? ''); ?>" placeholder="Tuesdays at 7:00 PM">
                </div>
                <div class="admin-form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($edit_group['location'] ?? ''); ?>" placeholder="Room 201">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="description" rows="2"><?= htmlspecialchars($edit_group['description'] ?? ''); ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Image URL</label>
                    <input type="text" name="image_url" value="<?= htmlspecialchars($edit_group['image_url'] ?? ''); ?>" placeholder="/uploads/...">
                </div>
                <div class="admin-form-group">
                    <label>Signup URL</label>
                    <input type="text" name="signup_url" value="<?= htmlspecialchars($edit_group['signup_url'] ?? ''); ?>" placeholder="https://...">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group" style="flex: 0 0 100px;">
                    <label>Order</label>
                    <input type="number" name="display_order" value="<?= $edit_group['display_order'] ?? 0; ?>" min="0">
                </div>
                <div class="admin-form-group" style="flex: 0 0 auto; align-self: flex-end;">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="visible" value="1" <?= ($edit_group['visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Visible</span>
                    </label>
                </div>
                <div class="admin-form-group" style="flex: 1; align-self: flex-end; text-align: right;">
                    <button type="submit" class="btn btn-sm btn-primary">Save Group</button>
                </div>
            </div>
        </form>
    </details>
</div>

<!-- Groups List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Groups</h3>
        <span class="admin-muted-text"><?= $total; ?> groups</span>
    </div>

    <?php if (empty($groups)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">👥</span>
            <p>No groups yet. Add one above.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($groups as $group): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($group['title']); ?>
                            <?php if ($group['category']): ?>
                                <span class="admin-badge admin-badge-info"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $group['category']))); ?></span>
                            <?php endif; ?>
                            <?php if ($group['visible']): ?>
                                <span class="admin-badge admin-badge-success">Visible</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Hidden</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($group['schedule']): ?>
                                <?= htmlspecialchars($group['schedule']); ?>
                            <?php endif; ?>
                            <?php if ($group['location']): ?>
                                · <?= htmlspecialchars($group['location']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?edit=<?= $group['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <a href="?delete=<?= $group['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
