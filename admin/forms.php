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
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.5rem;">
        <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Submissions</div>
        <div style="font-size: 2rem; font-weight: 700; color: #1e293b;"><?= $stats['total']; ?></div>
    </div>

    <div class="card" style="padding: 1.5rem;">
        <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem;">Unread</div>
        <div style="font-size: 2rem; font-weight: 700; color: #ff1493;">
            <?= $stats['unread']; ?>
            <?php if ($stats['unread'] > 0): ?>
                <span style="font-size: 1rem; color: #94a3b8;"> new</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="padding: 1.5rem;">
        <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem;">Read</div>
        <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?= $stats['total'] - $stats['unread']; ?></div>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
    <a href="?filter=all" class="<?= $filter === 'all' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'all' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">All (<?= $stats['total']; ?>)</a>
    <a href="?filter=unread" class="<?= $filter === 'unread' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'unread' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">Unread<?= $stats['unread'] > 0 ? ' (' . $stats['unread'] . ')' : ''; ?></a>
    <a href="?filter=read" class="<?= $filter === 'read' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'read' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">Read</a>
</div>

<!-- Detail View Modal (if viewing a submission) -->
<?php if ($detail_submission): ?>
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Submission Details</h2>
        <a href="/admin/forms.php?filter=<?= $filter; ?>" class="btn btn-outline">← Back to List</a>
    </div>

    <div style="padding: 1.5rem;">
        <!-- Header Info -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; padding: 1rem; background: #f8fafc; border-radius: 0.5rem;">
            <div>
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Form Name</div>
                <div style="font-weight: 600;"><?= htmlspecialchars($detail_submission['form_type']); ?></div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Submitted</div>
                <div style="font-weight: 600;"><?= date('F j, Y g:i A', strtotime($detail_submission['submitted_at'])); ?></div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Status</div>
                <div>
                    <?php if (!$detail_submission['processed']): ?>
                        <span class="badge badge-danger">Unread</span>
                    <?php else: ?>
                        <span class="badge badge-success">Read</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">IP Address</div>
                <div style="font-family: monospace; font-size: 0.875rem;"><?= htmlspecialchars($detail_submission['ip_address'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Form Data -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem;">
            <h3 style="margin-bottom: 1rem; font-size: 1.125rem;">Submitted Data</h3>
            <?php
            $form_data = json_decode($detail_submission['form_data'], true);
            if ($form_data):
                foreach ($form_data as $key => $value):
                    if ($key === 'csrf_token') continue; // Skip CSRF token
            ?>
                    <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem; text-transform: capitalize;">
                            <?= htmlspecialchars(str_replace('_', ' ', $key)); ?>
                        </div>
                        <div style="font-size: 1rem; color: #1e293b;">
                            <?= nl2br(htmlspecialchars($value)); ?>
                        </div>
                    </div>
            <?php
                endforeach;
            else:
            ?>
                <p style="color: #94a3b8;">No data available</p>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
            <?php if (!$detail_submission['processed']): ?>
                <a href="?mark=<?= $detail_submission['id']; ?>&status=read&filter=<?= $filter; ?>" class="btn btn-outline">Mark as Read</a>
            <?php else: ?>
                <a href="?mark=<?= $detail_submission['id']; ?>&status=unread&filter=<?= $filter; ?>" class="btn btn-outline">Mark as Unread</a>
            <?php endif; ?>
            <a href="?delete=<?= $detail_submission['id']; ?>&filter=<?= $filter; ?>" class="btn btn-danger" data-confirm-delete>Delete Submission</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Submissions List -->
<div class="card">
    <div class="card-header">
        <h2>Form Submissions</h2>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <h3>No submissions yet</h3>
            <p>Form submissions will appear here</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
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
                                $preview .= ($preview ? ' • ' : '') . htmlspecialchars(substr($data[$field], 0, 40));
                                break;
                            }
                        }

                        if (!$preview) {
                            $preview = 'No preview available';
                        }
                        ?>
                        <tr style="<?= !$submission['processed'] ? 'background: #fef2f2;' : ''; ?>">
                            <td>
                                <strong><?= htmlspecialchars($submission['form_type']); ?></strong>
                                <?php if (!$submission['processed']): ?>
                                    <span class="badge badge-danger" style="margin-left: 0.5rem;">New</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= $preview; ?>
                            </td>
                            <td><?= date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></td>
                            <td>
                                <?php if (!$submission['processed']): ?>
                                    <span class="badge badge-danger">Unread</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Read</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?view=<?= $submission['id']; ?>&filter=<?= $filter; ?>" class="btn btn-sm btn-primary">View</a>
                                <a href="?delete=<?= $submission['id']; ?>&filter=<?= $filter; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
