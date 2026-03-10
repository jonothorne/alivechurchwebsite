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
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Profanity Filter</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $totalCount; ?></strong> Words</span>
        <?php foreach ($categoryCounts as $cat => $count): ?>
            <span class="admin-inline-stat"><strong><?= $count; ?></strong> <?= ucfirst($cat); ?></span>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-profanity-grid">
    <!-- Add Forms -->
    <div class="admin-profanity-sidebar">
        <div class="admin-card">
            <details open>
                <summary class="admin-card-header" style="cursor: pointer;">
                    <h3>+ Add Word</h3>
                </summary>
                <form method="POST" class="admin-compact-form">
                    <?= csrf_field(); ?>
                    <div class="admin-form-group">
                        <label>Word/Phrase</label>
                        <input type="text" name="word" placeholder="Enter word" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Category</label>
                        <select name="category">
                            <?php foreach ($categories as $value => $label): ?>
                                <option value="<?= $value; ?>"><?= $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_word" class="btn btn-sm btn-primary" style="width: 100%;">Add</button>
                </form>
            </details>
        </div>

        <div class="admin-card">
            <details>
                <summary class="admin-card-header" style="cursor: pointer;">
                    <h3>+ Bulk Add</h3>
                </summary>
                <form method="POST" class="admin-compact-form">
                    <?= csrf_field(); ?>
                    <div class="admin-form-group">
                        <label>Words (one per line)</label>
                        <textarea name="words" rows="4" placeholder="word1&#10;word2&#10;word3"></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label>Category</label>
                        <select name="bulk_category">
                            <?php foreach ($categories as $value => $label): ?>
                                <option value="<?= $value; ?>"><?= $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="bulk_add" class="btn btn-sm btn-primary" style="width: 100%;">Add All</button>
                </form>
            </details>
        </div>
    </div>

    <!-- Word List -->
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="admin-filter-tabs" style="margin: 0; flex-wrap: wrap;">
                <a href="/admin/profanity-filter" class="admin-filter-tab <?= !$categoryFilter ? 'active' : ''; ?>">All</a>
                <?php foreach ($categories as $value => $label): ?>
                    <a href="/admin/profanity-filter?category=<?= $value; ?>" class="admin-filter-tab <?= $categoryFilter === $value ? 'active' : ''; ?>">
                        <?= $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($words)): ?>
            <div class="admin-empty-state">
                <span class="admin-empty-icon">🔇</span>
                <p>No words in filter.</p>
            </div>
        <?php else: ?>
            <div class="admin-word-cloud">
                <?php foreach ($words as $word): ?>
                    <div class="admin-word-tag <?= !$word['active'] ? 'inactive' : ''; ?>" data-category="<?= htmlspecialchars($word['category']); ?>">
                        <span class="admin-word-text"><?= htmlspecialchars($word['word']); ?></span>
                        <span class="admin-word-cat"><?= htmlspecialchars($categories[$word['category']] ?? $word['category']); ?></span>
                        <a href="?toggle=<?= $word['id']; ?><?= $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?>"
                           class="admin-word-action" title="<?= $word['active'] ? 'Disable' : 'Enable'; ?>">
                            <?= $word['active'] ? '👁️' : '👁️‍🗨️'; ?>
                        </a>
                        <a href="?delete=<?= $word['id']; ?><?= $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?>"
                           class="admin-word-action" title="Delete" onclick="return confirm('Remove this word?');">×</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
