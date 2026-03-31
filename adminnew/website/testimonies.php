<?php
$page_title = 'Testimonies';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Get current visibility setting
$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'testimonies_enabled'");
$stmt->execute();
$testimoniesEnabled = (bool)$stmt->fetchColumn();

// Handle toggle visibility
if (isset($_POST['toggle_visibility']) && verify_csrf($_POST['csrf_token'] ?? '')) {
    $newValue = $testimoniesEnabled ? '0' : '1';
    $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'testimonies_enabled'");
    $stmt->execute([$newValue]);
    $testimoniesEnabled = !$testimoniesEnabled;
    $success = $testimoniesEnabled ? 'Testimonies page is now visible to visitors' : 'Testimonies page is now hidden from visitors';
    log_activity($_SESSION['admin_user_id'], 'update', 'settings', null, 'Toggled testimonies visibility: ' . ($testimoniesEnabled ? 'enabled' : 'disabled'));
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM testimonies WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = 'Testimony deleted successfully';
        log_activity($_SESSION['admin_user_id'], 'delete', 'testimony', $id, 'Deleted testimony');
    }
}

// Handle save
if (isset($_POST['save_testimony']) && verify_csrf($_POST['csrf_token'] ?? '')) {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $data = [
        'person_name' => trim($_POST['person_name'] ?? ''),
        'person_role' => trim($_POST['person_role'] ?? ''),
        'person_image' => trim($_POST['person_image'] ?? ''),
        'testimony_type' => $_POST['testimony_type'] ?? 'general',
        'title' => trim($_POST['title'] ?? ''),
        'short_quote' => trim($_POST['short_quote'] ?? ''),
        'full_story' => trim($_POST['full_story'] ?? ''),
        'video_url' => trim($_POST['video_url'] ?? ''),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_published' => isset($_POST['is_published']) ? 1 : 0,
        'display_order' => (int)($_POST['display_order'] ?? 0),
    ];

    if (empty($data['person_name']) || empty($data['title']) || empty($data['full_story'])) {
        $error = 'Name, title, and story are required';
    } else {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE testimonies SET
                person_name = ?, person_role = ?, person_image = ?, testimony_type = ?,
                title = ?, short_quote = ?, full_story = ?, video_url = ?,
                is_featured = ?, is_published = ?, display_order = ?
                WHERE id = ?");
            $stmt->execute([
                $data['person_name'], $data['person_role'], $data['person_image'], $data['testimony_type'],
                $data['title'], $data['short_quote'], $data['full_story'], $data['video_url'],
                $data['is_featured'], $data['is_published'], $data['display_order'], $id
            ]);
            $success = 'Testimony updated successfully';
            log_activity($_SESSION['admin_user_id'], 'update', 'testimony', $id, 'Updated testimony: ' . $data['title']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO testimonies
                (person_name, person_role, person_image, testimony_type, title, short_quote, full_story, video_url, is_featured, is_published, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['person_name'], $data['person_role'], $data['person_image'], $data['testimony_type'],
                $data['title'], $data['short_quote'], $data['full_story'], $data['video_url'],
                $data['is_featured'], $data['is_published'], $data['display_order']
            ]);
            $success = 'Testimony added successfully';
            log_activity($_SESSION['admin_user_id'], 'create', 'testimony', $pdo->lastInsertId(), 'Added testimony: ' . $data['title']);
        }
    }
}

// Fetch testimonies
$testimonies = $pdo->query("SELECT * FROM testimonies ORDER BY display_order ASC, created_at DESC")->fetchAll();

// Edit mode
$editTestimony = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM testimonies WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTestimony = $stmt->fetch();
}
?>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Visibility Toggle -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem;">
        <div>
            <strong>Page Visibility</strong>
            <p style="margin: 0.25rem 0 0; color: var(--color-text-muted); font-size: 0.875rem;">
                <?php if ($testimoniesEnabled): ?>
                    <span style="color: #10b981;">●</span> Testimonies page is <strong>visible</strong> to visitors
                <?php else: ?>
                    <span style="color: #f59e0b;">●</span> Testimonies page is <strong>hidden</strong> from visitors (admin preview only)
                <?php endif; ?>
            </p>
        </div>
        <form method="post" style="margin: 0;">
            <?= csrf_field(); ?>
            <input type="hidden" name="toggle_visibility" value="1">
            <button type="submit" class="btn <?= $testimoniesEnabled ? 'btn-outline' : 'btn-primary'; ?>">
                <?= $testimoniesEnabled ? 'Hide Page' : 'Show Page'; ?>
            </button>
        </form>
    </div>
</div>

<div class="admin-split">
    <!-- Testimonies List -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>All Testimonies (<?= count($testimonies); ?>)</h3>
            <a href="/testimonies" target="_blank" class="btn btn-outline btn-sm">Preview Page</a>
        </div>

        <?php if (empty($testimonies)): ?>
        <div class="admin-empty-state">
            <p>No testimonies yet. Add your first story!</p>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Order</th>
                        <th>Person</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonies as $t): ?>
                    <tr>
                        <td><?= $t['display_order']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <?php if ($t['person_image']): ?>
                                <img src="<?= htmlspecialchars($t['person_image']); ?>"
                                     alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <?php endif; ?>
                                <div>
                                    <strong><?= htmlspecialchars($t['person_name']); ?></strong>
                                    <?php if ($t['is_featured']): ?>
                                    <span style="background: var(--color-magenta); color: white; font-size: 0.625rem; padding: 0.125rem 0.375rem; border-radius: 999px; margin-left: 0.5rem;">FEATURED</span>
                                    <?php endif; ?>
                                    <?php if ($t['person_role']): ?>
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted);"><?= htmlspecialchars($t['person_role']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($t['title']); ?></td>
                        <td><span class="admin-badge"><?= ucfirst($t['testimony_type']); ?></span></td>
                        <td>
                            <?php if ($t['is_published']): ?>
                            <span style="color: #10b981;">● Published</span>
                            <?php else: ?>
                            <span style="color: #94a3b8;">● Draft</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?edit=<?= $t['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
                            <a href="?delete=<?= $t['id']; ?>" class="btn btn-outline btn-sm"
                               onclick="return confirm('Delete this testimony?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><?= $editTestimony ? 'Edit Testimony' : 'Add Testimony'; ?></h3>
            <?php if ($editTestimony): ?>
            <a href="/adminnew/testimonies" class="btn btn-outline btn-sm">+ New</a>
            <?php endif; ?>
        </div>

        <form method="post" class="admin-form">
            <?= csrf_field(); ?>
            <input type="hidden" name="save_testimony" value="1">
            <?php if ($editTestimony): ?>
            <input type="hidden" name="id" value="<?= $editTestimony['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="person_name">Person's Name *</label>
                <input type="text" name="person_name" id="person_name" required
                       value="<?= htmlspecialchars($editTestimony['person_name'] ?? ''); ?>"
                       placeholder="e.g., Sarah Johnson">
            </div>

            <div class="form-group">
                <label for="person_role">Role/Description</label>
                <input type="text" name="person_role" id="person_role"
                       value="<?= htmlspecialchars($editTestimony['person_role'] ?? ''); ?>"
                       placeholder="e.g., Member since 2020, Youth Leader">
            </div>

            <div class="form-group">
                <label for="person_image">Photo URL</label>
                <input type="text" name="person_image" id="person_image"
                       value="<?= htmlspecialchars($editTestimony['person_image'] ?? ''); ?>"
                       placeholder="/uploads/testimonies/sarah.jpg">
                <small>Upload photos to media library first, then paste URL here</small>
            </div>

            <div class="form-group">
                <label for="testimony_type">Story Type *</label>
                <select name="testimony_type" id="testimony_type">
                    <option value="salvation" <?= ($editTestimony['testimony_type'] ?? '') === 'salvation' ? 'selected' : ''; ?>>Salvation Story</option>
                    <option value="transformation" <?= ($editTestimony['testimony_type'] ?? '') === 'transformation' ? 'selected' : ''; ?>>Life Change</option>
                    <option value="healing" <?= ($editTestimony['testimony_type'] ?? '') === 'healing' ? 'selected' : ''; ?>>Healing</option>
                    <option value="serve" <?= ($editTestimony['testimony_type'] ?? '') === 'serve' ? 'selected' : ''; ?>>Why I Serve</option>
                    <option value="general" <?= ($editTestimony['testimony_type'] ?? 'general') === 'general' ? 'selected' : ''; ?>>General</option>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Story Title *</label>
                <input type="text" name="title" id="title" required
                       value="<?= htmlspecialchars($editTestimony['title'] ?? ''); ?>"
                       placeholder="e.g., From Addiction to Freedom">
            </div>

            <div class="form-group">
                <label for="short_quote">Pull Quote</label>
                <textarea name="short_quote" id="short_quote" rows="2"
                          placeholder="A powerful quote from their story (shown on cards)"><?= htmlspecialchars($editTestimony['short_quote'] ?? ''); ?></textarea>
                <small>Keep this short and impactful - it appears on the testimony card</small>
            </div>

            <div class="form-group">
                <label for="full_story">Full Story *</label>
                <textarea name="full_story" id="full_story" rows="10" required
                          placeholder="Share the full testimony..."><?= htmlspecialchars($editTestimony['full_story'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="video_url">Video URL (optional)</label>
                <input type="text" name="video_url" id="video_url"
                       value="<?= htmlspecialchars($editTestimony['video_url'] ?? ''); ?>"
                       placeholder="https://youtube.com/watch?v=...">
            </div>

            <div class="form-group">
                <label for="display_order">Display Order</label>
                <input type="number" name="display_order" id="display_order"
                       value="<?= htmlspecialchars($editTestimony['display_order'] ?? '0'); ?>"
                       min="0" style="width: 100px;">
                <small>Lower numbers appear first</small>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_featured" value="1"
                           <?= ($editTestimony['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                    <span>Featured (shown prominently at top)</span>
                </label>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_published" value="1"
                           <?= ($editTestimony['is_published'] ?? 0) ? 'checked' : ''; ?>>
                    <span>Published (visible on website)</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editTestimony ? 'Update Testimony' : 'Add Testimony'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
