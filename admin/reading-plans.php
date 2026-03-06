<?php
$page_title = 'Reading Plans';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success_message = '';
$error_message = '';

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token';
    } else {
        $stmt = $pdo->prepare("DELETE FROM reading_plans WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        log_activity($_SESSION['admin_user_id'], 'delete', 'reading_plan', $_POST['id'], 'Deleted reading plan');
        $success_message = 'Reading plan deleted successfully.';
    }
}

// Handle publish/unpublish
if (isset($_POST['toggle_publish']) && isset($_POST['id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token';
    } else {
        $stmt = $pdo->prepare("UPDATE reading_plans SET published = NOT published WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        log_activity($_SESSION['admin_user_id'], 'update', 'reading_plan', $_POST['id'], 'Toggled publish status');
        $success_message = 'Reading plan updated.';
    }
}

// Handle feature toggle
if (isset($_POST['toggle_featured']) && isset($_POST['id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token';
    } else {
        $stmt = $pdo->prepare("UPDATE reading_plans SET is_featured = NOT is_featured WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        log_activity($_SESSION['admin_user_id'], 'update', 'reading_plan', $_POST['id'], 'Toggled featured status');
        $success_message = 'Reading plan updated.';
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$where = ['1=1'];
$params = [];

if ($statusFilter === 'published') {
    $where[] = 'p.published = 1';
} elseif ($statusFilter === 'draft') {
    $where[] = 'p.published = 0';
}

if ($categoryFilter) {
    $where[] = 'p.category = ?';
    $params[] = $categoryFilter;
}

$whereClause = implode(' AND ', $where);

// Get reading plans with stats
$sql = "SELECT p.*,
        u.full_name as author_name,
        (SELECT COUNT(*) FROM reading_plan_days d WHERE d.plan_id = p.id) as day_count,
        (SELECT COUNT(*) FROM user_reading_plan_progress up WHERE up.plan_id = p.id) as user_count
        FROM reading_plans p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE $whereClause
        ORDER BY p.is_featured DESC, p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plans = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM reading_plans WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get counts
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM reading_plans")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM reading_plans WHERE published = 1")->fetchColumn(),
    'draft' => $pdo->query("SELECT COUNT(*) FROM reading_plans WHERE published = 0")->fetchColumn(),
    'featured' => $pdo->query("SELECT COUNT(*) FROM reading_plans WHERE is_featured = 1")->fetchColumn(),
];
?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Reading Plans</h2>
        <a href="/admin/reading-plans/edit" class="btn btn-primary">+ New Reading Plan</a>
    </div>

    <!-- Stats -->
    <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 0.5rem;">
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;"><?= $counts['published']; ?></div>
            <div style="font-size: 0.875rem; color: #64748b;">Published</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #64748b;"><?= $counts['draft']; ?></div>
            <div style="font-size: 0.875rem; color: #64748b;">Drafts</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?= $counts['featured']; ?></div>
            <div style="font-size: 0.875rem; color: #64748b;">Featured</div>
        </div>
    </div>

    <!-- Filters -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="/admin/reading-plans" class="btn <?= !$statusFilter ? 'btn-primary' : 'btn-outline'; ?> btn-sm">
            All (<?= $counts['all']; ?>)
        </a>
        <a href="/admin/reading-plans?status=published" class="btn <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline'; ?> btn-sm">
            Published (<?= $counts['published']; ?>)
        </a>
        <a href="/admin/reading-plans?status=draft" class="btn <?= $statusFilter === 'draft' ? 'btn-primary' : 'btn-outline'; ?> btn-sm">
            Drafts (<?= $counts['draft']; ?>)
        </a>

        <?php if (!empty($categories)): ?>
        <select onchange="window.location.href='/admin/reading-plans?category='+this.value" style="padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #e2e8f0;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat); ?>" <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                    <?= htmlspecialchars(ucfirst($cat)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <?php if (empty($plans)): ?>
        <p style="color: #64748b; padding: 2rem; text-align: center;">
            No reading plans found. <a href="/admin/reading-plans/edit">Create your first reading plan</a>
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Days</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <?php if ($plan['icon']): ?>
                                    <span style="font-size: 1.5rem;"><?= $plan['icon']; ?></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= htmlspecialchars($plan['title']); ?></strong>
                                    <?php if ($plan['is_featured']): ?>
                                        <span style="background: #fef3c7; color: #92400e; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; margin-left: 0.5rem;">Featured</span>
                                    <?php endif; ?>
                                    <?php if ($plan['description']): ?>
                                        <div style="color: #64748b; font-size: 0.875rem; margin-top: 0.25rem;">
                                            <?= htmlspecialchars(substr($plan['description'], 0, 80)); ?><?= strlen($plan['description']) > 80 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($plan['category']): ?>
                                <span style="background: #e0e7ff; color: #3730a3; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem;">
                                    <?= htmlspecialchars(ucfirst($plan['category'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 600;"><?= $plan['day_count']; ?></span>
                            <span style="color: #64748b;">/ <?= $plan['duration_days']; ?></span>
                        </td>
                        <td>
                            <?= $plan['user_count']; ?>
                        </td>
                        <td>
                            <?php if ($plan['published']): ?>
                                <span class="badge badge-success">Published</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="/admin/reading-plans/edit?id=<?= $plan['id']; ?>" class="btn btn-sm btn-outline">Edit</a>

                                <form method="post" style="display: inline;">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= $plan['id']; ?>">
                                    <button type="submit" name="toggle_publish" class="btn btn-sm btn-outline">
                                        <?= $plan['published'] ? 'Unpublish' : 'Publish'; ?>
                                    </button>
                                </form>

                                <form method="post" style="display: inline;">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= $plan['id']; ?>">
                                    <button type="submit" name="toggle_featured" class="btn btn-sm btn-outline" title="<?= $plan['is_featured'] ? 'Remove from featured' : 'Add to featured'; ?>">
                                        <?= $plan['is_featured'] ? '★' : '☆'; ?>
                                    </button>
                                </form>

                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this reading plan? This cannot be undone.');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= $plan['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
