<?php
$page_title = 'Bible Studies';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM bible_studies WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Study deleted successfully.';
}

// Handle status change
if (isset($_POST['publish']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE bible_studies SET status = 'published' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Study published successfully.';
}

if (isset($_POST['unpublish']) && isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE bible_studies SET status = 'draft' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success_message = 'Study unpublished.';
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$testamentFilter = $_GET['testament'] ?? '';
$bookFilter = $_GET['book'] ?? '';

$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
}
if ($testamentFilter) {
    $where[] = 'b.testament = ?';
    $params[] = $testamentFilter;
}
if ($bookFilter) {
    $where[] = 's.book_id = ?';
    $params[] = $bookFilter;
}

$whereClause = implode(' AND ', $where);

// Get studies
$sql = "SELECT s.*, b.name as book_name, b.slug as book_slug, b.testament, u.full_name as author_name
        FROM bible_studies s
        JOIN bible_books b ON s.book_id = b.id
        LEFT JOIN users u ON s.author_id = u.id
        WHERE $whereClause
        ORDER BY b.book_order, s.chapter";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$studies = $stmt->fetchAll();

// Get books for filter
$books = $pdo->query("SELECT * FROM bible_books ORDER BY book_order")->fetchAll();

// Get counts
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM bible_studies")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM bible_studies WHERE status = 'published'")->fetchColumn(),
    'draft' => $pdo->query("SELECT COUNT(*) FROM bible_studies WHERE status = 'draft'")->fetchColumn(),
];

// Get stats
$booksWithStudies = $pdo->query("SELECT COUNT(DISTINCT book_id) FROM bible_studies WHERE status = 'published'")->fetchColumn();
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Bible Studies</span>
        <a href="/admin/bible-study/edit" class="btn btn-sm btn-primary">+ New Study</a>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $counts['published']; ?></strong> Published</span>
        <span class="admin-inline-stat"><strong><?= $counts['draft']; ?></strong> Drafts</span>
        <span class="admin-inline-stat"><strong><?= $booksWithStudies; ?></strong> Books</span>
    </div>
</div>

<!-- Filters -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="/admin/bible-study" class="admin-filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All</a>
            <a href="/admin/bible-study?status=published" class="admin-filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published</a>
            <a href="/admin/bible-study?status=draft" class="admin-filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts</a>
        </div>
        <div class="admin-filter-selects">
            <select onchange="window.location.href='/admin/bible-study?testament=' + this.value" class="admin-select-sm">
                <option value="">All Testaments</option>
                <option value="old" <?= $testamentFilter === 'old' ? 'selected' : ''; ?>>Old Testament</option>
                <option value="new" <?= $testamentFilter === 'new' ? 'selected' : ''; ?>>New Testament</option>
            </select>
            <select onchange="window.location.href='/admin/bible-study?book=' + this.value" class="admin-select-sm">
                <option value="">All Books</option>
                <?php foreach ($books as $book): ?>
                    <option value="<?= $book['id']; ?>" <?= $bookFilter == $book['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($book['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($studies)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">📖</span>
            <p>No Bible studies yet. <a href="/admin/bible-study/edit">Create one</a></p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($studies as $study): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?>
                            <?php if ($study['title']): ?>
                                <span class="admin-muted">— <?= htmlspecialchars($study['title']); ?></span>
                            <?php endif; ?>
                            <?php if ($study['status'] === 'published'): ?>
                                <span class="admin-badge admin-badge-success">Published</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Draft</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <span class="admin-badge admin-badge-outline"><?= $study['testament'] === 'old' ? 'OT' : 'NT'; ?></span>
                            <?php if ($study['author_name']): ?>
                                · <?= htmlspecialchars($study['author_name']); ?>
                            <?php endif; ?>
                            <?php if ($study['reading_time']): ?>
                                · <?= $study['reading_time']; ?> min read
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <?php if ($study['status'] === 'published'): ?>
                            <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" target="_blank" class="btn btn-xs btn-outline">View</a>
                        <?php endif; ?>
                        <a href="/admin/bible-study/edit?id=<?= $study['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <?php if ($study['status'] === 'draft'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                <button type="submit" name="publish" class="btn btn-xs btn-primary">Publish</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                <button type="submit" name="unpublish" class="btn btn-xs btn-outline">Unpublish</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this study?');">
                            <input type="hidden" name="id" value="<?= $study['id']; ?>">
                            <button type="submit" name="delete" class="btn btn-xs btn-danger">×</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
