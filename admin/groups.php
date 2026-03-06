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
            // Update
            $stmt = $pdo->prepare("UPDATE groups_list SET title = ?, description = ?, schedule = ?, location = ?, image_url = ?, signup_url = ?, category = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $description, $schedule, $location, $image_url, $signup_url, $category, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'group', $id, 'Updated group: ' . $title);
            $success = 'Group updated successfully';
        } else {
            // Insert
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
?>

<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= $edit_group ? 'Edit' : 'Add New'; ?> Group</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_group): ?>
            <input type="hidden" name="id" value="<?= $edit_group['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Group Name</label>
            <input type="text" name="title" value="<?= htmlspecialchars($edit_group['title'] ?? ''); ?>" required>
            <div class="form-help">The name of the group (e.g., "Young Adults", "Men's Bible Study")</div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4" class="wysiwyg"><?= htmlspecialchars($edit_group['description'] ?? ''); ?></textarea>
            <div class="form-help">What the group is about, who it's for, what to expect</div>
        </div>

        <div class="form-group">
            <label>Schedule</label>
            <input type="text" name="schedule" value="<?= htmlspecialchars($edit_group['schedule'] ?? ''); ?>" placeholder="Tuesdays at 7:00 PM">
            <div class="form-help">When the group meets (e.g., "Tuesdays 7:00 PM", "Every other Friday")</div>
        </div>

        <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" value="<?= htmlspecialchars($edit_group['location'] ?? ''); ?>">
            <div class="form-help">Where the group meets (e.g., "Main Campus - Room 201", "Online via Zoom")</div>
        </div>

        <div class="form-group">
            <label>Image URL (optional)</label>
            <input type="text" name="image_url" value="<?= htmlspecialchars($edit_group['image_url'] ?? ''); ?>">
            <div class="form-help">URL to group image</div>
        </div>

        <div class="form-group">
            <label>Signup URL (optional)</label>
            <input type="text" name="signup_url" value="<?= htmlspecialchars($edit_group['signup_url'] ?? ''); ?>">
            <div class="form-help">Link to signup form or more info</div>
        </div>

        <div class="form-group">
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

        <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="display_order" value="<?= $edit_group['display_order'] ?? 0; ?>" min="0">
            <div class="form-help">Lower numbers appear first</div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="visible" value="1" <?= ($edit_group['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                <span>Visible on website</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Group</button>
            <?php if ($edit_group): ?>
                <a href="/admin/groups.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Groups</h2>
    </div>

    <?php if (empty($groups)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <h3>No groups yet</h3>
            <p>Create your first group above</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Schedule</th>
                        <th>Location</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($group['title']); ?></strong></td>
                            <td>
                                <?php if ($group['schedule']): ?>
                                    <?= htmlspecialchars($group['schedule']); ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($group['location'] ?: '—'); ?></td>
                            <td>
                                <?php if ($group['category']): ?>
                                    <span class="badge" style="background: #3b82f6; color: white;"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $group['category']))); ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($group['visible']): ?>
                                    <span class="badge badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $group['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="?delete=<?= $group['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
