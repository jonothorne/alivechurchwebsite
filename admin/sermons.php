<?php
$page_title = 'Sermons';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
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
    $stmt = $pdo->prepare("DELETE FROM sermons WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'sermon', $id, 'Deleted sermon');
        $success = 'Sermon deleted';
    }
}

// Handle Add/Edit Series
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['series_form'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $description = $_POST['description'];
        $artwork_url = $_POST['artwork_url'];
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE sermon_series SET title = ?, description = ?, artwork_url = ?, start_date = ?, end_date = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $description, $artwork_url, $start_date, $end_date, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'sermon_series', $id, 'Updated series: ' . $title);
            $success = 'Series updated successfully';
        } else {
            $stmt = $pdo->prepare("INSERT INTO sermon_series (title, description, artwork_url, start_date, end_date, visible) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $artwork_url, $start_date, $end_date, $visible]);
            log_activity($_SESSION['admin_user_id'], 'create', 'sermon_series', $pdo->lastInsertId(), 'Created series: ' . $title);
            $success = 'Series created successfully';
        }
    }
}

// Handle Add/Edit Sermon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sermon_form'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $series_id = $_POST['series_id'];
        $title = $_POST['title'];
        $speaker = $_POST['speaker'];
        $sermon_date = $_POST['sermon_date'];
        $description = $_POST['description'];
        $video_url = $_POST['video_url'];
        $audio_url = $_POST['audio_url'];
        $duration = $_POST['duration'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE sermons SET series_id = ?, title = ?, speaker = ?, sermon_date = ?, description = ?, video_url = ?, audio_url = ?, duration = ?, visible = ? WHERE id = ?");
            $stmt->execute([$series_id, $title, $speaker, $sermon_date, $description, $video_url, $audio_url, $duration, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'sermon', $id, 'Updated sermon: ' . $title);
            $success = 'Sermon updated successfully';
        } else {
            $stmt = $pdo->prepare("INSERT INTO sermons (series_id, title, speaker, sermon_date, description, video_url, audio_url, duration, visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$series_id, $title, $speaker, $sermon_date, $description, $video_url, $audio_url, $duration, $visible]);
            log_activity($_SESSION['admin_user_id'], 'create', 'sermon', $pdo->lastInsertId(), 'Created sermon: ' . $title);
            $success = 'Sermon created successfully';
        }
    }
}

// Fetch data based on view
if ($view === 'series') {
    $all_series = $pdo->query("SELECT * FROM sermon_series ORDER BY display_order ASC, created_at DESC")->fetchAll();
    $edit_series = null;
    if (isset($_GET['edit_series']) && is_numeric($_GET['edit_series'])) {
        $stmt = $pdo->prepare("SELECT * FROM sermon_series WHERE id = ?");
        $stmt->execute([$_GET['edit_series']]);
        $edit_series = $stmt->fetch();
    }
} else {
    // Messages view
    $all_series = $pdo->query("SELECT id, title FROM sermon_series ORDER BY start_date DESC")->fetchAll();

    if ($series_id) {
        $stmt = $pdo->prepare("SELECT * FROM sermons WHERE series_id = ? ORDER BY sermon_date DESC");
        $stmt->execute([$series_id]);
        $sermons = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT title FROM sermon_series WHERE id = ?");
        $stmt->execute([$series_id]);
        $current_series = $stmt->fetch();
    } else {
        $sermons = $pdo->query("SELECT s.*, ss.title as series_title FROM sermons s LEFT JOIN sermon_series ss ON s.series_id = ss.id ORDER BY s.sermon_date DESC")->fetchAll();
    }

    $edit_sermon = null;
    if (isset($_GET['edit_sermon']) && is_numeric($_GET['edit_sermon'])) {
        $stmt = $pdo->prepare("SELECT * FROM sermons WHERE id = ?");
        $stmt->execute([$_GET['edit_sermon']]);
        $edit_sermon = $stmt->fetch();
    }
}
?>

<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- View Switcher -->
<div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem;">
    <a href="?view=series" class="btn <?= $view === 'series' ? 'btn-primary' : 'btn-outline'; ?>">📚 Sermon Series</a>
    <a href="?view=messages" class="btn <?= $view === 'messages' ? 'btn-primary' : 'btn-outline'; ?>">🎤 Individual Messages</a>
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
                <label>Description</label>
                <textarea name="description" rows="3" class="wysiwyg"><?= htmlspecialchars($edit_series['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Artwork URL</label>
                <input type="text" name="artwork_url" value="<?= htmlspecialchars($edit_series['artwork_url'] ?? ''); ?>">
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
                            <th>Date Range</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_series as $series): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($series['title']); ?></strong></td>
                                <td>
                                    <?php if (!empty($series['date_range'])): ?>
                                        <?= htmlspecialchars($series['date_range']); ?>
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
                                    <a href="?view=messages&series_id=<?= $series['id']; ?>" class="btn btn-sm btn-outline">View Messages</a>
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

    <div class="card">
        <div class="card-header">
            <h2><?= $edit_sermon ? 'Edit' : 'Add New'; ?> Sermon</h2>
        </div>

        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="sermon_form" value="1">
            <?php if ($edit_sermon): ?>
                <input type="hidden" name="id" value="<?= $edit_sermon['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Series</label>
                <select name="series_id" required>
                    <option value="">Select a series...</option>
                    <?php foreach ($all_series as $series): ?>
                        <option value="<?= $series['id']; ?>" <?= ($edit_sermon['series_id'] ?? $series_id ?? '') == $series['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($series['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Message Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($edit_sermon['title'] ?? ''); ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Speaker</label>
                    <input type="text" name="speaker" value="<?= htmlspecialchars($edit_sermon['speaker'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Sermon Date</label>
                    <input type="date" name="sermon_date" value="<?= $edit_sermon['sermon_date'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($edit_sermon['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Video URL</label>
                <input type="text" name="video_url" value="<?= htmlspecialchars($edit_sermon['video_url'] ?? ''); ?>" placeholder="https://youtube.com/watch?v=...">
                <div class="form-help">YouTube or Vimeo URL</div>
            </div>

            <div class="form-group">
                <label>Audio URL</label>
                <input type="text" name="audio_url" value="<?= htmlspecialchars($edit_sermon['audio_url'] ?? ''); ?>" placeholder="https://example.com/sermon.mp3">
                <div class="form-help">Direct link to audio file (MP3)</div>
            </div>

            <div class="form-group">
                <label>Duration</label>
                <input type="text" name="duration" value="<?= htmlspecialchars($edit_sermon['duration'] ?? ''); ?>" placeholder="45 mins">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="visible" value="1" <?= ($edit_sermon['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                    <span>Visible on website</span>
                </label>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">Save Sermon</button>
                <?php if ($edit_sermon): ?>
                    <a href="?view=messages<?= $series_id ? '&series_id=' . $series_id : ''; ?>" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?= $series_id && isset($current_series) ? 'Messages in ' . htmlspecialchars($current_series['title']) : 'All Messages'; ?></h2>
        </div>

        <?php if (empty($sermons)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎤</div>
                <h3>No sermons yet</h3>
                <p>Create your first sermon above</p>
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
                                <td><strong><?= htmlspecialchars($sermon['title']); ?></strong></td>
                                <?php if (!$series_id): ?>
                                    <td><?= htmlspecialchars($sermon['series_title'] ?? 'No Series'); ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($sermon['speaker'] ?? '—'); ?></td>
                                <td><?= date('M j, Y', strtotime($sermon['sermon_date'])); ?></td>
                                <td><?= htmlspecialchars($sermon['duration'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($sermon['visible']): ?>
                                        <span class="badge badge-success">Visible</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <a href="?view=messages&edit_sermon=<?= $sermon['id']; ?><?= $series_id ? '&series_id=' . $series_id : ''; ?>" class="btn btn-sm btn-outline">Edit</a>
                                    <a href="?view=messages&delete_sermon=<?= $sermon['id']; ?><?= $series_id ? '&series_id=' . $series_id : ''; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
