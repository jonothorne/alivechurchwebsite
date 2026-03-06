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
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Manage Bible Studies</h2>
        <a href="/admin/bible-study/edit" class="btn btn-primary">+ New Study</a>
    </div>

    <!-- Stats -->
    <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 0.5rem;">
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;"><?= $counts['published']; ?></div>
            <div style="font-size: 0.875rem; color: #64748b;">Published Studies</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #64748b;"><?= $counts['draft']; ?></div>
            <div style="font-size: 0.875rem; color: #64748b;">Drafts</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?= $booksWithStudies; ?></div>
            <div style="font-size: 0.875rem; color: #64748b;">Books Covered</div>
        </div>
    </div>

    <!-- Filters -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div class="filter-tabs">
            <a href="/admin/bible-study" class="filter-tab <?= !$statusFilter ? 'active' : ''; ?>">All (<?= $counts['all']; ?>)</a>
            <a href="/admin/bible-study?status=published" class="filter-tab <?= $statusFilter === 'published' ? 'active' : ''; ?>">Published (<?= $counts['published']; ?>)</a>
            <a href="/admin/bible-study?status=draft" class="filter-tab <?= $statusFilter === 'draft' ? 'active' : ''; ?>">Drafts (<?= $counts['draft']; ?>)</a>
        </div>
        <select onchange="window.location.href='/admin/bible-study?testament=' + this.value" style="padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #cbd5e1;">
            <option value="">All Testaments</option>
            <option value="old" <?= $testamentFilter === 'old' ? 'selected' : ''; ?>>Old Testament</option>
            <option value="new" <?= $testamentFilter === 'new' ? 'selected' : ''; ?>>New Testament</option>
        </select>
        <select onchange="window.location.href='/admin/bible-study?book=' + this.value" style="padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #cbd5e1;">
            <option value="">All Books</option>
            <?php foreach ($books as $book): ?>
                <option value="<?= $book['id']; ?>" <?= $bookFilter == $book['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($book['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($studies)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📖</div>
            <h3>No Bible Studies Yet</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Start adding verse-by-verse Bible studies.</p>
            <a href="/admin/bible-study/edit" class="btn btn-primary">Create First Study</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Book & Chapter</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Reading Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studies as $study): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></strong>
                                <div style="font-size: 0.75rem; color: #94a3b8;">
                                    <?= $study['testament'] === 'old' ? 'Old Testament' : 'New Testament'; ?>
                                </div>
                            </td>
                            <td><?= $study['title'] ? htmlspecialchars($study['title']) : '<span style="color: #94a3b8;">—</span>'; ?></td>
                            <td><?= $study['author_name'] ? htmlspecialchars($study['author_name']) : '<span style="color: #94a3b8;">—</span>'; ?></td>
                            <td>
                                <?php if ($study['status'] === 'published'): ?>
                                    <span class="badge badge-success">Published</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($study['reading_time']): ?>
                                    <?= $study['reading_time']; ?> min
                                <?php else: ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($study['status'] === 'published'): ?>
                                        <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                    <?php endif; ?>
                                    <a href="/admin/bible-study/edit?id=<?= $study['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <?php if ($study['status'] === 'draft'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                            <button type="submit" name="publish" class="btn btn-sm btn-success">Publish</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                            <button type="submit" name="unpublish" class="btn btn-sm btn-outline">Unpublish</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this study?');">
                                        <input type="hidden" name="id" value="<?= $study['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
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

<style>
.filter-tabs {
    display: flex;
    gap: 0.25rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 0.5rem;
}
.filter-tab {
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: #64748b;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
}
.filter-tab:hover {
    color: #1e293b;
}
.filter-tab.active {
    background: #fff;
    color: #1e293b;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
