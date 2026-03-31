<?php
/**
 * Form Submissions - New Admin
 */
$page_title = 'Form Submissions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

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

// Fetch submissions
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

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Form Submissions</h1>
        <p class="admin-page-subtitle"><?= $stats['total']; ?> total, <?= $stats['unread']; ?> unread</p>
    </div>
</div>

<?php if ($detail_submission): ?>
<!-- Detail View -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Submission Details</h3>
        <a href="/adminnew/forms?filter=<?= $filter; ?>" class="admin-btn admin-btn-secondary">Back</a>
    </div>
    <div class="admin-card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Form</span>
                <span class="detail-value"><?= htmlspecialchars($detail_submission['form_type']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Submitted</span>
                <span class="detail-value"><?= date('M j, Y g:i A', strtotime($detail_submission['submitted_at'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <?php if (!$detail_submission['processed']): ?>
                        <span class="admin-badge admin-badge-danger">Unread</span>
                    <?php else: ?>
                        <span class="admin-badge admin-badge-success">Read</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">IP Address</span>
                <span class="detail-value"><code><?= htmlspecialchars($detail_submission['ip_address'] ?? 'N/A'); ?></code></span>
            </div>
        </div>

        <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--admin-border);">

        <h4 style="margin-bottom: 1rem;">Form Data</h4>
        <?php
        $form_data = json_decode($detail_submission['form_data'], true);
        if ($form_data):
            foreach ($form_data as $key => $value):
                if ($key === 'csrf_token') continue;
        ?>
            <div class="data-row">
                <span class="data-label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))); ?></span>
                <span class="data-value"><?= nl2br(htmlspecialchars($value)); ?></span>
            </div>
        <?php
            endforeach;
        else:
        ?>
            <p class="admin-text-muted">No data available</p>
        <?php endif; ?>

        <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
            <?php if (!$detail_submission['processed']): ?>
                <a href="/adminnew/forms?mark=<?= $detail_submission['id']; ?>&status=read&filter=<?= $filter; ?>" class="admin-btn admin-btn-secondary">Mark Read</a>
            <?php else: ?>
                <a href="/adminnew/forms?mark=<?= $detail_submission['id']; ?>&status=unread&filter=<?= $filter; ?>" class="admin-btn admin-btn-secondary">Mark Unread</a>
            <?php endif; ?>
            <a href="/adminnew/forms?delete=<?= $detail_submission['id']; ?>&filter=<?= $filter; ?>" class="admin-btn admin-btn-danger" onclick="return confirm('Delete this submission?')">Delete</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Submissions List -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs">
            <a href="/adminnew/forms?filter=all" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="/adminnew/forms?filter=unread" class="admin-filter-tab <?= $filter === 'unread' ? 'active' : ''; ?>">Unread<?= $stats['unread'] > 0 ? ' (' . $stats['unread'] . ')' : ''; ?></a>
            <a href="/adminnew/forms?filter=read" class="admin-filter-tab <?= $filter === 'read' ? 'active' : ''; ?>">Read</a>
        </div>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            </div>
            <h3 class="admin-empty-title">No submissions yet</h3>
            <p class="admin-empty-text">Form submissions will appear here.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Form</th>
                        <th>Preview</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr class="<?= !$submission['processed'] ? 'row-unread' : ''; ?>">
                            <td>
                                <strong><?= htmlspecialchars($submission['form_type']); ?></strong>
                            </td>
                            <td>
                                <span class="admin-text-muted"><?= $preview; ?></span>
                            </td>
                            <td><?= date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></td>
                            <td>
                                <?php if (!$submission['processed']): ?>
                                    <span class="admin-badge admin-badge-danger">New</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-success">Read</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="/adminnew/forms?view=<?= $submission['id']; ?>&filter=<?= $filter; ?>" class="admin-btn admin-btn-sm admin-btn-primary">View</a>
                                    <a href="/adminnew/forms?delete=<?= $submission['id']; ?>&filter=<?= $filter; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete?')">×</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
.admin-filter-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--admin-bg);
    padding: 0.25rem;
    border-radius: var(--admin-radius);
}
.admin-filter-tab {
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: var(--admin-text-muted);
    border-radius: var(--admin-radius-sm);
    font-weight: 500;
    font-size: 0.875rem;
    transition: all var(--admin-transition);
}
.admin-filter-tab:hover {
    color: var(--admin-text);
}
.admin-filter-tab.active {
    background: var(--admin-card-bg);
    color: var(--admin-text);
    box-shadow: var(--admin-shadow-sm);
}

.row-unread {
    background: color-mix(in srgb, var(--admin-warning) 5%, transparent);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.detail-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
}
.detail-value {
    font-weight: 500;
}

.data-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--admin-border);
}
.data-row:last-child {
    border-bottom: none;
}
.data-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--admin-text-muted);
}
.data-value {
    white-space: pre-wrap;
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
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
