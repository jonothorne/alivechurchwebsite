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
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Reading Plans</span>
        <a href="/admin/reading-plans/edit" class="btn btn-sm btn-primary">+ New Plan</a>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $counts['published']; ?></strong> Published</span>
        <span class="admin-inline-stat"><strong><?= $counts['draft']; ?></strong> Drafts</span>
        <span class="admin-inline-stat"><strong><?= $counts['featured']; ?></strong> Featured</span>
    </div>
</div>

<!-- Filters -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="/admin/reading-plans" class="admin-filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All</a>
            <a href="/admin/reading-plans?status=published" class="admin-filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published</a>
            <a href="/admin/reading-plans?status=draft" class="admin-filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts</a>
        </div>
        <?php if (!empty($categories)): ?>
        <div class="admin-filter-selects">
            <select onchange="window.location.href='/admin/reading-plans?category='+this.value" class="admin-select-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat); ?>" <?= $categoryFilter === $cat ? 'selected' : ''; ?>>
                        <?= htmlspecialchars(ucfirst($cat)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($plans)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">📖</span>
            <p>No reading plans yet. <a href="/admin/reading-plans/edit">Create one</a></p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($plans as $plan): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?php if ($plan['icon']): ?>
                                <span class="admin-plan-icon"><?= $plan['icon']; ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($plan['title']); ?>
                            <?php if ($plan['is_featured']): ?>
                                <span class="admin-badge admin-badge-warning">Featured</span>
                            <?php endif; ?>
                            <?php if ($plan['published']): ?>
                                <span class="admin-badge admin-badge-success">Published</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Draft</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($plan['category']): ?>
                                <span class="admin-badge admin-badge-info"><?= htmlspecialchars(ucfirst($plan['category'])); ?></span>
                            <?php endif; ?>
                            · <strong><?= $plan['day_count']; ?></strong>/<?= $plan['duration_days']; ?> days
                            · <strong><?= $plan['user_count']; ?></strong> users
                            <?php if ($plan['description']): ?>
                                · <?= htmlspecialchars(substr($plan['description'], 0, 50)); ?><?= strlen($plan['description']) > 50 ? '...' : ''; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="/admin/reading-plans/edit?id=<?= $plan['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <form method="post" style="display: inline;">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $plan['id']; ?>">
                            <button type="submit" name="toggle_publish" class="btn btn-xs btn-outline">
                                <?= $plan['published'] ? 'Unpublish' : 'Publish'; ?>
                            </button>
                        </form>
                        <form method="post" style="display: inline;">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $plan['id']; ?>">
                            <button type="submit" name="toggle_featured" class="btn btn-xs btn-outline" title="<?= $plan['is_featured'] ? 'Unfeature' : 'Feature'; ?>">
                                <?= $plan['is_featured'] ? '★' : '☆'; ?>
                            </button>
                        </form>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this reading plan?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="id" value="<?= $plan['id']; ?>">
                            <button type="submit" name="delete" class="btn btn-xs btn-danger">×</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
