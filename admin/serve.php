<?php
$page_title = 'Serve Opportunities';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM serve_opportunities WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'serve_opportunity', $id, 'Deleted serve opportunity');
        $success = 'Serve opportunity deleted successfully';
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
        $team_leader = $_POST['team_leader'];
        $commitment = $_POST['commitment'];
        $schedule = $_POST['schedule'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE serve_opportunities SET title = ?, description = ?, team_leader = ?, commitment = ?, schedule = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $description, $team_leader, $commitment, $schedule, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'serve_opportunity', $id, 'Updated serve opportunity: ' . $title);
            $success = 'Serve opportunity updated successfully';
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO serve_opportunities (title, description, team_leader, commitment, schedule, visible) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $team_leader, $commitment, $schedule, $visible]);
            $new_id = $pdo->lastInsertId();
            log_activity($_SESSION['admin_user_id'], 'create', 'serve_opportunity', $new_id, 'Created serve opportunity: ' . $title);
            $success = 'Serve opportunity created successfully';
        }
    }
}

// Fetch all serve opportunities
$opportunities = $pdo->query("SELECT * FROM serve_opportunities ORDER BY created_at DESC")->fetchAll();

// Get opportunity for editing
$edit_opportunity = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM serve_opportunities WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_opportunity = $stmt->fetch();
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
        <h2><?= $edit_opportunity ? 'Edit' : 'Add New'; ?> Serve Opportunity</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_opportunity): ?>
            <input type="hidden" name="id" value="<?= $edit_opportunity['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Team/Role Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($edit_opportunity['title'] ?? ''); ?>" required>
            <div class="form-help">The name of the team or role (e.g., "Worship Team", "Kids Ministry")</div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="5" class="wysiwyg"><?= htmlspecialchars($edit_opportunity['description'] ?? ''); ?></textarea>
            <div class="form-help">What the role involves, what skills are needed, what to expect</div>
        </div>

        <div class="form-group">
            <label>Team Leader</label>
            <input type="text" name="team_leader" value="<?= htmlspecialchars($edit_opportunity['team_leader'] ?? ''); ?>">
            <div class="form-help">Name of the team leader or contact person</div>
        </div>

        <div class="form-group">
            <label>Time Commitment</label>
            <input type="text" name="commitment" value="<?= htmlspecialchars($edit_opportunity['commitment'] ?? ''); ?>" placeholder="e.g., 2 hours/week, Once a month">
            <div class="form-help">How often volunteers are needed (e.g., "Weekly", "Monthly", "2 hours/week")</div>
        </div>

        <div class="form-group">
            <label>Schedule</label>
            <input type="text" name="schedule" value="<?= htmlspecialchars($edit_opportunity['schedule'] ?? ''); ?>" placeholder="e.g., Sundays 9:00 AM">
            <div class="form-help">When the team serves (e.g., "Sundays 9:00 AM", "Wednesdays 7:00 PM")</div>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="visible" value="1" <?= ($edit_opportunity['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                <span>Visible on website</span>
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Opportunity</button>
            <?php if ($edit_opportunity): ?>
                <a href="/admin/serve.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Serve Opportunities</h2>
    </div>

    <?php if (empty($opportunities)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🤝</div>
            <h3>No serve opportunities yet</h3>
            <p>Create your first opportunity above</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Team Leader</th>
                        <th>Commitment</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($opportunities as $opp): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($opp['title']); ?></strong></td>
                            <td><?= htmlspecialchars($opp['team_leader'] ?? '—'); ?></td>
                            <td><?= htmlspecialchars($opp['commitment'] ?? '—'); ?></td>
                            <td><?= htmlspecialchars($opp['schedule'] ?? '—'); ?></td>
                            <td>
                                <?php if ($opp['visible']): ?>
                                    <span class="badge badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $opp['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="?delete=<?= $opp['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
