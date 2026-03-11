<?php
$page_title = 'Sermons';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/SermonManager.php';

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);
$success = '';
$error = '';

// Determine view mode
$view = $_GET['view'] ?? 'series'; // 'series' or 'messages'
$series_id = $_GET['series_id'] ?? null;

// Handle Delete Series
if (isset($_GET['delete_series']) && is_numeric($_GET['delete_series'])) {
    $id = (int)$_GET['delete_series'];
    $stmt = $pdo->prepare("DELETE FROM sermon_series WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'sermon_series', $id, 'Deleted sermon series');
        $success = 'Sermon series deleted';
    }
}

// Handle Delete Sermon
if (isset($_GET['delete_sermon']) && is_numeric($_GET['delete_sermon'])) {
    $id = (int)$_GET['delete_sermon'];
    $sermonManager->deleteSermon($id);
    log_activity($_SESSION['admin_user_id'], 'delete', 'sermon', $id, 'Deleted sermon');
    $success = 'Sermon deleted';
}

// Handle Add/Edit Series
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['series_form'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $slug = $_POST['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($title)));
        $description = $_POST['description'];
        $image_url = $_POST['image_url'];
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $visible = isset($_POST['visible']) ? 1 : 0;

        // Build date_range string
        $date_range = '';
        if ($start_date) {
            $date_range = date('F Y', strtotime($start_date));
            if ($end_date && date('Y-m', strtotime($start_date)) !== date('Y-m', strtotime($end_date))) {
                $date_range .= ' - ' . date('F Y', strtotime($end_date));
            }
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE sermon_series SET title = ?, slug = ?, description = ?, image_url = ?, date_range = ?, start_date = ?, end_date = ?, is_featured = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $slug, $description, $image_url, $date_range, $start_date, $end_date, $is_featured, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'sermon_series', $id, 'Updated series: ' . $title);
            $success = 'Series updated successfully';
        } else {
            $stmt = $pdo->prepare("INSERT INTO sermon_series (title, slug, description, image_url, date_range, start_date, end_date, is_featured, visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $description, $image_url, $date_range, $start_date, $end_date, $is_featured, $visible]);
            log_activity($_SESSION['admin_user_id'], 'create', 'sermon_series', $pdo->lastInsertId(), 'Created series: ' . $title);
            $success = 'Series created successfully';
        }
    }
}

// Fetch data based on view
if ($view === 'series') {
    $all_series = $pdo->query("SELECT * FROM sermon_series ORDER BY display_order ASC, start_date DESC, created_at DESC")->fetchAll();
    $edit_series = null;
    if (isset($_GET['edit_series']) && is_numeric($_GET['edit_series'])) {
        $stmt = $pdo->prepare("SELECT * FROM sermon_series WHERE id = ?");
        $stmt->execute([$_GET['edit_series']]);
        $edit_series = $stmt->fetch();
    }
} else {
    // Messages view
    $all_series = $pdo->query("SELECT id, title FROM sermon_series ORDER BY start_date DESC, created_at DESC")->fetchAll();

    if ($series_id) {
        $stmt = $pdo->prepare("
            SELECT s.*, u.full_name as speaker_name
            FROM sermons s
            LEFT JOIN users u ON s.speaker_user_id = u.id
            WHERE s.series_id = ?
            ORDER BY s.sermon_date DESC, s.display_order ASC
        ");
        $stmt->execute([$series_id]);
        $sermons = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT title FROM sermon_series WHERE id = ?");
        $stmt->execute([$series_id]);
        $current_series = $stmt->fetch();
    } else {
        $sermons = $pdo->query("
            SELECT s.*, ss.title as series_title, u.full_name as speaker_name
            FROM sermons s
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            LEFT JOIN users u ON s.speaker_user_id = u.id
            ORDER BY s.sermon_date DESC, s.created_at DESC
        ")->fetchAll();
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- View Switcher -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; align-items: center;">
    <a href="?view=series" class="btn <?= $view === 'series' ? 'btn-primary' : 'btn-outline'; ?>">Sermon Series</a>
    <a href="?view=messages" class="btn <?= $view === 'messages' ? 'btn-primary' : 'btn-outline'; ?>">Individual Messages</a>
    <?php
    $pendingComments = $pdo->query("SELECT COUNT(*) FROM sermon_comments WHERE status = 'pending'")->fetchColumn();
    ?>
    <a href="/admin/sermons/comments" class="btn btn-outline" style="margin-left: auto;">
        Comments
        <?php if ($pendingComments > 0): ?>
            <span style="background: #ef4444; color: white; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; margin-left: 0.5rem;"><?= $pendingComments; ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($view === 'series'): ?>
    <!-- SERMON SERIES VIEW -->

    <div class="card">
        <div class="card-header">
            <h2><?= $edit_series ? 'Edit' : 'Add New'; ?> Sermon Series</h2>
        </div>

        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="series_form" value="1">
            <?php if ($edit_series): ?>
                <input type="hidden" name="id" value="<?= $edit_series['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Series Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($edit_series['title'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($edit_series['slug'] ?? ''); ?>" placeholder="auto-generated-from-title">
                <div class="form-help">Leave blank to auto-generate from title</div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($edit_series['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Cover Image URL</label>
                <input type="text" name="image_url" value="<?= htmlspecialchars($edit_series['image_url'] ?? ''); ?>">
                <div class="form-help">URL to series artwork/cover image</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $edit_series['start_date'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= $edit_series['end_date'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_featured" value="1" <?= ($edit_series['is_featured'] ?? 0) ? 'checked' : ''; ?> style="width: auto;">
                    <span>Feature this series</span>
                </label>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="visible" value="1" <?= ($edit_series['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                    <span>Visible on website</span>
                </label>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">Save Series</button>
                <?php if ($edit_series): ?>
                    <a href="?view=series" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>All Sermon Series</h2>
        </div>

        <?php if (empty($all_series)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h3>No sermon series yet</h3>
                <p>Create your first series above</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Messages</th>
                            <th>Date Range</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_series as $series): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($series['title']); ?></strong>
                                    <?php if ($series['is_featured']): ?>
                                        <span class="badge badge-info" style="margin-left: 0.5rem;">Featured</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $series['message_count']; ?></td>
                                <td>
                                    <?php if (!empty($series['date_range'])): ?>
                                        <?= htmlspecialchars($series['date_range']); ?>
                                    <?php elseif (!empty($series['start_date'])): ?>
                                        <?= date('M Y', strtotime($series['start_date'])); ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($series['visible']): ?>
                                        <span class="badge badge-success">Visible</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <a href="?view=messages&series_id=<?= $series['id']; ?>" class="btn btn-sm btn-outline">Messages</a>
                                    <a href="?view=series&edit_series=<?= $series['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                    <a href="?view=series&delete_series=<?= $series['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- INDIVIDUAL MESSAGES VIEW -->

    <?php if ($series_id && isset($current_series)): ?>
        <div style="background: #f1f5f9; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
            <a href="?view=messages" style="color: #64748b; text-decoration: none;">← All Messages</a>
            <h3 style="margin: 0.5rem 0 0 0; color: #1e293b;">Viewing: <?= htmlspecialchars($current_series['title']); ?></h3>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <?php if (!$series_id): ?>
                <select id="series-filter" onchange="filterBySeries(this.value)" style="padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid #e2e8f0;">
                    <option value="">All Series</option>
                    <?php foreach ($all_series as $s): ?>
                        <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <a href="/admin/sermons/edit.php<?= $series_id ? '?series_id=' . $series_id : ''; ?>" class="btn btn-primary">+ Add New Sermon</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?= $series_id && isset($current_series) ? 'Messages in ' . htmlspecialchars($current_series['title']) : 'All Messages'; ?></h2>
        </div>

        <?php if (empty($sermons)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎤</div>
                <h3>No sermons yet</h3>
                <p>Create your first sermon using the button above</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <?php if (!$series_id): ?><th>Series</th><?php endif; ?>
                            <th>Speaker</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sermons as $sermon): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($sermon['title']); ?></strong>
                                    <?php if ($sermon['is_featured']): ?>
                                        <span class="badge badge-info" style="margin-left: 0.5rem;">Featured</span>
                                    <?php endif; ?>
                                    <?php if (!empty($sermon['youtube_video_id'])): ?>
                                        <span style="color: #ef4444; margin-left: 0.25rem;" title="YouTube">▶</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (!$series_id): ?>
                                    <td><?= htmlspecialchars($sermon['series_title'] ?? '—'); ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($sermon['speaker_name'] ?? $sermon['speaker'] ?? '—'); ?></td>
                                <td><?= $sermon['sermon_date'] ? date('M j, Y', strtotime($sermon['sermon_date'])) : '—'; ?></td>
                                <td><?= htmlspecialchars($sermon['length'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($sermon['visible']): ?>
                                        <span class="badge badge-success">Visible</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <a href="/admin/sermons/edit.php?id=<?= $sermon['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                    <?php if ($sermon['slug']): ?>
                                        <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                    <?php endif; ?>
                                    <a href="?view=messages&delete_sermon=<?= $sermon['id']; ?><?= $series_id ? '&series_id=' . $series_id : ''; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function filterBySeries(seriesId) {
        if (seriesId) {
            window.location.href = '?view=messages&series_id=' + seriesId;
        } else {
            window.location.href = '?view=messages';
        }
    }
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
