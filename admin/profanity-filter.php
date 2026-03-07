<?php
$page_title = 'Profanity Filter';
$current_page = 'profanity-filter';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/profanity-filter.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (removeProfanityWord($id, $pdo)) {
        log_activity($_SESSION['admin_user_id'] ?? null, 'delete', 'profanity_word', $id, 'Deleted profanity word');
        $success = 'Word removed from filter.';
    }
}

// Handle Toggle
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if (toggleProfanityWord($id, $pdo)) {
        log_activity($_SESSION['admin_user_id'] ?? null, 'update', 'profanity_word', $id, 'Toggled profanity word status');
        $success = 'Word status updated.';
    }
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_word'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $word = trim($_POST['word'] ?? '');
        $category = $_POST['category'] ?? 'profanity';

        if (empty($word)) {
            $error = 'Please enter a word.';
        } elseif (addProfanityWord($word, $category, $pdo)) {
            log_activity($_SESSION['admin_user_id'] ?? null, 'create', 'profanity_word', null, 'Added profanity word: ' . $word);
            $success = 'Word added to filter.';
        } else {
            $error = 'Failed to add word. It may already exist.';
        }
    }
}

// Handle Bulk Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $words = trim($_POST['words'] ?? '');
        $category = $_POST['bulk_category'] ?? 'profanity';

        if (empty($words)) {
            $error = 'Please enter words to add.';
        } else {
            $wordList = preg_split('/[\n,]+/', $words);
            $added = 0;
            foreach ($wordList as $word) {
                $word = trim($word);
                if (!empty($word) && addProfanityWord($word, $category, $pdo)) {
                    $added++;
                }
            }
            if ($added > 0) {
                log_activity($_SESSION['admin_user_id'] ?? null, 'create', 'profanity_word', null, "Bulk added $added profanity words");
                $success = "$added word(s) added to filter.";
            } else {
                $error = 'No new words were added. They may already exist.';
            }
        }
    }
}

// Get filter
$categoryFilter = $_GET['category'] ?? '';

// Get words
if ($categoryFilter) {
    $stmt = $pdo->prepare("SELECT * FROM profanity_words WHERE category = ? ORDER BY word ASC");
    $stmt->execute([$categoryFilter]);
} else {
    $stmt = $pdo->query("SELECT * FROM profanity_words ORDER BY category ASC, word ASC");
}
$words = $stmt->fetchAll();

// Get counts by category
$categoryCounts = $pdo->query("SELECT category, COUNT(*) as count FROM profanity_words GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($categoryCounts);

// Categories
$categories = [
    'profanity' => 'Profanity',
    'slur' => 'Slurs',
    'sexual' => 'Sexual Content',
    'threat' => 'Threats/Violence',
    'other' => 'Other'
];
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    <!-- Add Word Form -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2>Add Word</h2>
            </div>
            <form method="POST">
                <?= csrf_field(); ?>
                <div class="form-group">
                    <label>Word/Phrase</label>
                    <input type="text" name="word" placeholder="Enter word or phrase" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?= $value; ?>"><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_word" class="btn btn-primary">Add Word</button>
            </form>
        </div>

        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h2>Bulk Add</h2>
            </div>
            <form method="POST">
                <?= csrf_field(); ?>
                <div class="form-group">
                    <label>Words (one per line or comma-separated)</label>
                    <textarea name="words" rows="6" placeholder="word1&#10;word2&#10;word3"></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="bulk_category">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?= $value; ?>"><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="bulk_add" class="btn btn-primary">Add All</button>
            </form>
        </div>
    </div>

    <!-- Word List -->
    <div class="card">
        <div class="card-header">
            <h2>Filtered Words (<?= $totalCount; ?>)</h2>
        </div>

        <!-- Category Filter -->
        <div class="filter-tabs" style="margin-bottom: 1.5rem;">
            <a href="/admin/profanity-filter" class="filter-tab <?= !$categoryFilter ? 'active' : ''; ?>">
                All (<?= $totalCount; ?>)
            </a>
            <?php foreach ($categories as $value => $label): ?>
                <a href="/admin/profanity-filter?category=<?= $value; ?>" class="filter-tab <?= $categoryFilter === $value ? 'active' : ''; ?>">
                    <?= $label; ?> (<?= $categoryCounts[$value] ?? 0; ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($words)): ?>
            <div class="empty-state" style="padding: 2rem;">
                <p style="color: #64748b;">No words in filter.</p>
            </div>
        <?php else: ?>
            <div class="word-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 0 1.5rem 1.5rem;">
                <?php foreach ($words as $word): ?>
                    <div class="word-tag <?= !$word['active'] ? 'inactive' : ''; ?>" data-category="<?= htmlspecialchars($word['category']); ?>">
                        <span class="word-text"><?= htmlspecialchars($word['word']); ?></span>
                        <span class="word-category"><?= htmlspecialchars($categories[$word['category']] ?? $word['category']); ?></span>
                        <div class="word-actions">
                            <a href="?toggle=<?= $word['id']; ?><?= $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?>"
                               class="word-action" title="<?= $word['active'] ? 'Disable' : 'Enable'; ?>">
                                <?= $word['active'] ? '👁️' : '👁️‍🗨️'; ?>
                            </a>
                            <a href="?delete=<?= $word['id']; ?><?= $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?>"
                               class="word-action" title="Delete" onclick="return confirm('Remove this word from the filter?');">
                                ✕
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 0.5rem;
}
.filter-tab {
    padding: 0.5rem 0.75rem;
    text-decoration: none;
    color: #64748b;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.8rem;
    white-space: nowrap;
}
.filter-tab:hover {
    color: #1e293b;
}
.filter-tab.active {
    background: #fff;
    color: #1e293b;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.word-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    padding: 0.375rem 0.5rem;
    font-size: 0.875rem;
}
.word-tag.inactive {
    opacity: 0.5;
    background: #fef2f2;
    border-color: #fecaca;
}
.word-tag[data-category="slur"] {
    border-left: 3px solid #dc2626;
}
.word-tag[data-category="profanity"] {
    border-left: 3px solid #f59e0b;
}
.word-tag[data-category="sexual"] {
    border-left: 3px solid #ec4899;
}
.word-tag[data-category="threat"] {
    border-left: 3px solid #7c3aed;
}
.word-tag[data-category="other"] {
    border-left: 3px solid #6b7280;
}

.word-text {
    font-family: monospace;
    color: #1e293b;
}
.word-category {
    font-size: 0.7rem;
    color: #94a3b8;
    text-transform: uppercase;
}
.word-actions {
    display: flex;
    gap: 0.25rem;
    margin-left: 0.25rem;
}
.word-action {
    text-decoration: none;
    opacity: 0.5;
    font-size: 0.75rem;
    padding: 0.125rem;
}
.word-action:hover {
    opacity: 1;
}

@media (max-width: 768px) {
    .admin-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
