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
            $stmt = $pdo->prepare("UPDATE serve_opportunities SET title = ?, description = ?, team_leader = ?, commitment = ?, schedule = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $description, $team_leader, $commitment, $schedule, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'serve_opportunity', $id, 'Updated serve opportunity: ' . $title);
            $success = 'Serve opportunity updated successfully';
        } else {
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

// Count stats
$total = count($opportunities);
$visible_count = count(array_filter($opportunities, fn($o) => $o['visible']));
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
        <span class="admin-greeting-text">Serve Opportunities</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $total; ?></strong> Total</span>
        <span class="admin-inline-stat"><strong><?= $visible_count; ?></strong> Visible</span>
    </div>
</div>

<!-- Form Card -->
<div class="admin-card">
    <details <?= $edit_opportunity ? 'open' : ''; ?>>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3><?= $edit_opportunity ? 'Edit Opportunity' : '+ Add Opportunity'; ?></h3>
            <?php if ($edit_opportunity): ?>
                <a href="/admin/serve.php" class="btn btn-xs btn-outline">Cancel</a>
            <?php endif; ?>
        </summary>
        <form method="post" class="admin-compact-form">
            <?= csrf_field(); ?>
            <?php if ($edit_opportunity): ?>
                <input type="hidden" name="id" value="<?= $edit_opportunity['id']; ?>">
            <?php endif; ?>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Team/Role Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($edit_opportunity['title'] ?? ''); ?>" required placeholder="e.g., Worship Team">
                </div>
                <div class="admin-form-group">
                    <label>Team Leader</label>
                    <input type="text" name="team_leader" value="<?= htmlspecialchars($edit_opportunity['team_leader'] ?? ''); ?>" placeholder="Contact person">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="description" rows="2"><?= htmlspecialchars($edit_opportunity['description'] ?? ''); ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Time Commitment</label>
                    <input type="text" name="commitment" value="<?= htmlspecialchars($edit_opportunity['commitment'] ?? ''); ?>" placeholder="e.g., 2 hours/week">
                </div>
                <div class="admin-form-group">
                    <label>Schedule</label>
                    <input type="text" name="schedule" value="<?= htmlspecialchars($edit_opportunity['schedule'] ?? ''); ?>" placeholder="e.g., Sundays 9:00 AM">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group" style="flex: 0 0 auto;">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="visible" value="1" <?= ($edit_opportunity['visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Visible</span>
                    </label>
                </div>
                <div class="admin-form-group" style="flex: 1; text-align: right;">
                    <button type="submit" class="btn btn-sm btn-primary">Save Opportunity</button>
                </div>
            </div>
        </form>
    </details>
</div>

<!-- Opportunities List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Opportunities</h3>
        <span class="admin-muted-text"><?= $total; ?> opportunities</span>
    </div>

    <?php if (empty($opportunities)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">🤝</span>
            <p>No serve opportunities yet. Add one above.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($opportunities as $opp): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($opp['title']); ?>
                            <?php if ($opp['visible']): ?>
                                <span class="admin-badge admin-badge-success">Visible</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Hidden</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($opp['team_leader']): ?>
                                <?= htmlspecialchars($opp['team_leader']); ?> ·
                            <?php endif; ?>
                            <?php if ($opp['schedule']): ?>
                                <?= htmlspecialchars($opp['schedule']); ?> ·
                            <?php endif; ?>
                            <?php if ($opp['commitment']): ?>
                                <?= htmlspecialchars($opp['commitment']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?edit=<?= $opp['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <a href="?delete=<?= $opp['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
