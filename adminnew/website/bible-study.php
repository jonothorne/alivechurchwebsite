<?php
/**
 * Bible Studies - New Admin
 */
$page_title = 'Bible Studies';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

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

$booksWithStudies = $pdo->query("SELECT COUNT(DISTINCT book_id) FROM bible_studies WHERE status = 'published'")->fetchColumn();
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Bible Studies</h1>
        <p class="admin-page-subtitle"><?= $counts['published']; ?> published · <?= $booksWithStudies; ?> books</p>
    </div>
    <a href="/adminnew/bible-study/edit" class="admin-btn admin-btn-primary">+ New Study</a>
</div>

<div class="admin-card">
    <div class="admin-card-header" style="flex-wrap: wrap; gap: 1rem;">
        <div class="admin-filter-tabs">
            <a href="/adminnew/bible-study" class="admin-filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All (<?= $counts['all']; ?>)</a>
            <a href="/adminnew/bible-study?status=published" class="admin-filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published (<?= $counts['published']; ?>)</a>
            <a href="/adminnew/bible-study?status=draft" class="admin-filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts (<?= $counts['draft']; ?>)</a>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <select onchange="window.location.href=this.value" class="admin-form-select" style="width: auto;">
                <option value="/adminnew/bible-study">All Testaments</option>
                <option value="/adminnew/bible-study?testament=old" <?= $testamentFilter === 'old' ? 'selected' : ''; ?>>Old Testament</option>
                <option value="/adminnew/bible-study?testament=new" <?= $testamentFilter === 'new' ? 'selected' : ''; ?>>New Testament</option>
            </select>
            <select onchange="window.location.href=this.value" class="admin-form-select" style="width: auto;">
                <option value="/adminnew/bible-study">All Books</option>
                <?php foreach ($books as $book): ?>
                    <option value="/adminnew/bible-study?book=<?= $book['id']; ?>" <?= $bookFilter == $book['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($book['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($studies)): ?>
        <div class="admin-empty-state">
            <h3 class="admin-empty-title">No Bible studies yet</h3>
            <p class="admin-empty-text">Create your first study to get started.</p>
            <a href="/adminnew/bible-study/edit" class="admin-btn admin-btn-primary">+ New Study</a>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Chapter</th>
                        <th>Testament</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studies as $study): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></strong>
                                <?php if ($study['title']): ?>
                                    <br><span class="admin-text-muted"><?= htmlspecialchars($study['title']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-secondary"><?= $study['testament'] === 'old' ? 'OT' : 'NT'; ?></span>
                            </td>
                            <td><?= htmlspecialchars($study['author_name'] ?? '—'); ?></td>
                            <td>
                                <?php if ($study['status'] === 'published'): ?>
                                    <span class="admin-badge admin-badge-success">Published</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <?php if ($study['status'] === 'published'): ?>
                                        <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                                    <?php endif; ?>
                                    <a href="/adminnew/bible-study/edit/<?= $study['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                    <?php if ($study['status'] === 'draft'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                            <button type="submit" name="publish" class="admin-btn admin-btn-sm admin-btn-success">Publish</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                            <button type="submit" name="unpublish" class="admin-btn admin-btn-sm admin-btn-secondary">Unpub</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this study?')">
                                        <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                        <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger">×</button>
                                    </form>
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
.admin-filter-tabs { display: flex; gap: 0.25rem; background: var(--admin-bg); padding: 0.25rem; border-radius: var(--admin-radius); }
.admin-filter-tab { padding: 0.5rem 1rem; text-decoration: none; color: var(--admin-text-muted); border-radius: var(--admin-radius-sm); font-weight: 500; font-size: 0.875rem; }
.admin-filter-tab:hover { color: var(--admin-text); }
.admin-filter-tab.active { background: var(--admin-card-bg); color: var(--admin-text); box-shadow: var(--admin-shadow-sm); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
