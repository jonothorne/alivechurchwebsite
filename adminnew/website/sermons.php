<?php
/**
 * Sermons Management - New Admin
 */
$page_title = 'Sermons';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/SermonManager.php';

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);
$success = '';
$error = '';

// Determine view mode
$view = $_GET['view'] ?? 'series';
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

$pendingComments = $pdo->query("SELECT COUNT(*) FROM sermon_comments WHERE status = 'pending'")->fetchColumn();
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Sermons</h1>
        <p class="admin-page-subtitle">Manage sermon series and messages</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/sermons?view=series" class="admin-btn <?= $view === 'series' ? 'admin-btn-primary' : 'admin-btn-secondary'; ?>">Series</a>
        <a href="/adminnew/sermons?view=messages" class="admin-btn <?= $view === 'messages' ? 'admin-btn-primary' : 'admin-btn-secondary'; ?>">Messages</a>
        <a href="/adminnew/sermons/comments" class="admin-btn admin-btn-secondary">
            Comments
            <?php if ($pendingComments > 0): ?>
                <span class="admin-badge admin-badge-danger" style="margin-left: 0.25rem;"><?= $pendingComments; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<?php if ($view === 'series'): ?>
    <!-- SERMON SERIES VIEW -->

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title"><?= $edit_series ? 'Edit' : 'Add New'; ?> Sermon Series</h3>
        </div>
        <div class="admin-card-body">
            <form method="post">
                <?= csrf_field(); ?>
                <input type="hidden" name="series_form" value="1">
                <?php if ($edit_series): ?>
                    <input type="hidden" name="id" value="<?= $edit_series['id']; ?>">
                <?php endif; ?>

                <div class="admin-form-group">
                    <label class="admin-form-label">Series Title</label>
                    <input type="text" name="title" class="admin-form-input" value="<?= htmlspecialchars($edit_series['title'] ?? ''); ?>" required>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">URL Slug</label>
                    <input type="text" name="slug" class="admin-form-input" value="<?= htmlspecialchars($edit_series['slug'] ?? ''); ?>" placeholder="auto-generated-from-title">
                    <small class="admin-text-muted">Leave blank to auto-generate from title</small>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Description</label>
                    <textarea name="description" class="admin-form-textarea" rows="3"><?= htmlspecialchars($edit_series['description'] ?? ''); ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Cover Image URL</label>
                    <input type="text" name="image_url" class="admin-form-input" value="<?= htmlspecialchars($edit_series['image_url'] ?? ''); ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Start Date</label>
                        <input type="date" name="start_date" class="admin-form-input" value="<?= $edit_series['start_date'] ?? ''; ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">End Date</label>
                        <input type="date" name="end_date" class="admin-form-input" value="<?= $edit_series['end_date'] ?? ''; ?>">
                    </div>
                </div>

                <div class="admin-form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_featured" value="1" <?= ($edit_series['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Feature this series</span>
                    </label>
                </div>

                <div class="admin-form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="visible" value="1" <?= ($edit_series['visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Visible on website</span>
                    </label>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="admin-btn admin-btn-primary">Save Series</button>
                    <?php if ($edit_series): ?>
                        <a href="/adminnew/sermons?view=series" class="admin-btn admin-btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">All Sermon Series</h3>
        </div>

        <?php if (empty($all_series)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <h3 class="admin-empty-title">No sermon series yet</h3>
                <p class="admin-empty-text">Create your first series above</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
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
                                        <span class="admin-badge admin-badge-info" style="margin-left: 0.5rem;">Featured</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $series['message_count']; ?></td>
                                <td>
                                    <?php if (!empty($series['date_range'])): ?>
                                        <?= htmlspecialchars($series['date_range']); ?>
                                    <?php elseif (!empty($series['start_date'])): ?>
                                        <?= date('M Y', strtotime($series['start_date'])); ?>
                                    <?php else: ?>
                                        <span class="admin-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($series['visible']): ?>
                                        <span class="admin-badge admin-badge-success">Visible</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-danger">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="/adminnew/sermons?view=messages&series_id=<?= $series['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Messages</a>
                                        <a href="/adminnew/sermons?view=series&edit_series=<?= $series['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                        <a href="/adminnew/sermons?view=series&delete_series=<?= $series['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this series?')">Delete</a>
                                    </div>
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
        <div class="admin-card" style="margin-bottom: 1rem;">
            <div class="admin-card-body" style="padding: 1rem;">
                <a href="/adminnew/sermons?view=messages" class="admin-text-muted" style="text-decoration: none;">← All Messages</a>
                <h3 style="margin: 0.5rem 0 0 0;">Viewing: <?= htmlspecialchars($current_series['title']); ?></h3>
            </div>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <?php if (!$series_id): ?>
                <select onchange="if(this.value) window.location.href='/adminnew/sermons?view=messages&series_id='+this.value; else window.location.href='/adminnew/sermons?view=messages';" class="admin-form-select" style="width: auto;">
                    <option value="">All Series</option>
                    <?php foreach ($all_series as $s): ?>
                        <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <a href="/adminnew/sermons/edit<?= $series_id ? '&series_id=' . $series_id : ''; ?>" class="admin-btn admin-btn-primary">+ Add New Sermon</a>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title"><?= $series_id && isset($current_series) ? 'Messages in ' . htmlspecialchars($current_series['title']) : 'All Messages'; ?></h3>
        </div>

        <?php if (empty($sermons)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
                </div>
                <h3 class="admin-empty-title">No sermons yet</h3>
                <p class="admin-empty-text">Create your first sermon using the button above</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
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
                                        <span class="admin-badge admin-badge-info" style="margin-left: 0.5rem;">Featured</span>
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
                                        <span class="admin-badge admin-badge-success">Visible</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-danger">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="/adminnew/sermons/edit/<?= $sermon['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                        <?php if ($sermon['slug']): ?>
                                            <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                                        <?php endif; ?>
                                        <a href="/adminnew/sermons?view=messages&delete_sermon=<?= $sermon['id']; ?><?= $series_id ? '&series_id=' . $series_id : ''; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this sermon?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<style <?= csp_nonce(); ?>>
.admin-badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--admin-info);
}
.admin-alert {
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}
.admin-alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--admin-success);
    border: 1px solid var(--admin-success);
}
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
