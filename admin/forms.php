<?php
$page_title = 'Form Submissions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Get admin user ID for logging
$admin_user_id = $_SESSION['admin_user']['id'] ?? null;

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM form_submissions WHERE id = ?");
    if ($stmt->execute([$id])) {
        if ($admin_user_id) {
            log_activity($admin_user_id, 'delete', 'form_submission', $id, 'Deleted form submission');
        }
        $success = 'Submission deleted';
    }
}

// Handle Mark as Read/Unread
if (isset($_GET['mark']) && is_numeric($_GET['mark'])) {
    $id = (int)$_GET['mark'];
    $processed = ($_GET['status'] ?? 'read') === 'read' ? TRUE : FALSE;

    $stmt = $pdo->prepare("UPDATE form_submissions SET processed = ? WHERE id = ?");
    $stmt->execute([$processed, $id]);
    if ($admin_user_id) {
        log_activity($admin_user_id, 'update', 'form_submission', $id, 'Marked as ' . ($processed ? 'read' : 'unread'));
    }
    $success = 'Status updated';
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Fetch submissions based on filter
if ($filter === 'unread') {
    $submissions = $pdo->query("SELECT * FROM form_submissions WHERE processed = FALSE ORDER BY submitted_at DESC")->fetchAll();
} elseif ($filter === 'read') {
    $submissions = $pdo->query("SELECT * FROM form_submissions WHERE processed = TRUE ORDER BY submitted_at DESC")->fetchAll();
} else {
    $submissions = $pdo->query("SELECT * FROM form_submissions ORDER BY submitted_at DESC")->fetchAll();
}

// Get submission for detail view
$detail_submission = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM form_submissions WHERE id = ?");
    $stmt->execute([$_GET['view']]);
    $detail_submission = $stmt->fetch();

    // Mark as read when viewed
    if ($detail_submission && !$detail_submission['processed']) {
        $stmt = $pdo->prepare("UPDATE form_submissions SET processed = TRUE WHERE id = ?");
        $stmt->execute([$_GET['view']]);
    }
}

// Count stats
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN processed = FALSE THEN 1 ELSE 0 END) as unread FROM form_submissions");
$stats = $stmt->fetch();
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
        <span class="admin-greeting-text">Form Submissions</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $stats['total']; ?></strong> Total</span>
        <span class="admin-inline-stat <?= $stats['unread'] > 0 ? 'admin-stat-alert' : ''; ?>"><strong><?= $stats['unread']; ?></strong> Unread</span>
        <span class="admin-inline-stat"><strong><?= $stats['total'] - $stats['unread']; ?></strong> Read</span>
    </div>
</div>

<!-- Detail View Modal (if viewing a submission) -->
<?php if ($detail_submission): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Submission Details</h3>
        <a href="/admin/forms.php?filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Back</a>
    </div>

    <div class="admin-detail-grid">
        <div class="admin-detail-item">
            <span class="admin-detail-label">Form</span>
            <span class="admin-detail-value"><?= htmlspecialchars($detail_submission['form_type']); ?></span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Submitted</span>
            <span class="admin-detail-value"><?= date('M j, Y g:i A', strtotime($detail_submission['submitted_at'])); ?></span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Status</span>
            <span class="admin-detail-value">
                <?php if (!$detail_submission['processed']): ?>
                    <span class="admin-badge admin-badge-danger">Unread</span>
                <?php else: ?>
                    <span class="admin-badge admin-badge-success">Read</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">IP</span>
            <span class="admin-detail-value"><code><?= htmlspecialchars($detail_submission['ip_address'] ?? 'N/A'); ?></code></span>
        </div>
    </div>

    <div class="admin-submission-data">
        <?php
        $form_data = json_decode($detail_submission['form_data'], true);
        if ($form_data):
            foreach ($form_data as $key => $value):
                if ($key === 'csrf_token') continue;
        ?>
            <div class="admin-data-row">
                <span class="admin-data-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))); ?></span>
                <span class="admin-data-value"><?= nl2br(htmlspecialchars($value)); ?></span>
            </div>
        <?php
            endforeach;
        else:
        ?>
            <p class="admin-muted-text">No data available</p>
        <?php endif; ?>
    </div>

    <div class="admin-detail-actions">
        <?php if (!$detail_submission['processed']): ?>
            <a href="?mark=<?= $detail_submission['id']; ?>&status=read&filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Mark Read</a>
        <?php else: ?>
            <a href="?mark=<?= $detail_submission['id']; ?>&status=unread&filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Mark Unread</a>
        <?php endif; ?>
        <a href="?delete=<?= $detail_submission['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-danger" data-confirm-delete>Delete</a>
    </div>
</div>
<?php endif; ?>

<!-- Submissions List -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="?filter=all" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=unread" class="admin-filter-tab <?= $filter === 'unread' ? 'active' : ''; ?>">Unread<?= $stats['unread'] > 0 ? ' (' . $stats['unread'] . ')' : ''; ?></a>
            <a href="?filter=read" class="admin-filter-tab <?= $filter === 'read' ? 'active' : ''; ?>">Read</a>
        </div>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">📝</span>
            <p>No submissions yet.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($submissions as $submission): ?>
                <?php
                $data = json_decode($submission['form_data'], true);
                $preview_fields = ['name', 'email', 'subject', 'message'];
                $preview = '';
                foreach ($preview_fields as $field) {
                    if (isset($data[$field])) {
                        $preview .= ($preview ? ' · ' : '') . htmlspecialchars(substr($data[$field], 0, 40));
                    }
                }
                if (!$preview) $preview = 'No preview';
                ?>
                <div class="admin-post-row <?= !$submission['processed'] ? 'admin-row-unread' : ''; ?>">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($submission['form_type']); ?>
                            <?php if (!$submission['processed']): ?>
                                <span class="admin-badge admin-badge-danger">New</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?= date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?> · <?= $preview; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?view=<?= $submission['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-primary">View</a>
                        <a href="?delete=<?= $submission['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
