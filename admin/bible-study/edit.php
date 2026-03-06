<?php
$page_title = 'Edit Bible Study';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/BibleStudyTagger.php';
require_once __DIR__ . '/../../includes/CrossReferenceManager.php';

$pdo = getDbConnection();
$tagger = new BibleStudyTagger($pdo);
$crossRefManager = new CrossReferenceManager($pdo);

$studyId = $_GET['id'] ?? null;
$isNew = !$studyId;

$study = [
    'id' => null,
    'book_id' => $_GET['book'] ?? '',
    'chapter' => $_GET['chapter'] ?? '',
    'title' => '',
    'summary' => '',
    'content' => '',
    'status' => 'draft',
    'reading_time' => 0,
];

// Load existing study
if ($studyId) {
    $stmt = $pdo->prepare("SELECT * FROM bible_studies WHERE id = ?");
    $stmt->execute([$studyId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $study = $existing;
        $isNew = false;
    }
}

// Get all books
$books = $pdo->query("SELECT * FROM bible_books ORDER BY book_order")->fetchAll();

// Get all users who can be authors (editors and admins)
$authors = $pdo->query("SELECT id, full_name, username, role FROM users WHERE role IN ('admin', 'editor') AND active = 1 ORDER BY full_name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'book_id' => intval($_POST['book_id']),
        'chapter' => intval($_POST['chapter']),
        'title' => trim($_POST['title']),
        'summary' => trim($_POST['summary']),
        'content' => $_POST['content'],
        'status' => $_POST['status'],
        'reading_time' => intval($_POST['reading_time']) ?: calculateReadingTime($_POST['content']),
        'author_id' => intval($_POST['author_id']) ?: $current_user['id'],
    ];

    // Validate
    if (empty($data['book_id'])) {
        $error_message = 'Please select a book.';
    } elseif ($data['chapter'] < 1) {
        $error_message = 'Please enter a valid chapter number.';
    } else {
        try {
            // Check for duplicate book/chapter
            $checkStmt = $pdo->prepare("SELECT id FROM bible_studies WHERE book_id = ? AND chapter = ? AND id != ?");
            $checkStmt->execute([$data['book_id'], $data['chapter'], $studyId ?? 0]);
            if ($checkStmt->fetch()) {
                $error_message = 'A study for this book and chapter already exists.';
            } else {
                if ($isNew) {
                    $stmt = $pdo->prepare("INSERT INTO bible_studies (book_id, chapter, title, summary, content, status, reading_time, author_id)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $data['book_id'], $data['chapter'], $data['title'], $data['summary'],
                        $data['content'], $data['status'], $data['reading_time'], $current_user['id']
                    ]);
                    $studyId = $pdo->lastInsertId();
                    $success_message = 'Study created successfully.';
                } else {
                    $stmt = $pdo->prepare("UPDATE bible_studies SET book_id = ?, chapter = ?, title = ?, summary = ?,
                                           content = ?, status = ?, reading_time = ?, author_id = ?, updated_at = NOW()
                                           WHERE id = ?");
                    $stmt->execute([
                        $data['book_id'], $data['chapter'], $data['title'], $data['summary'],
                        $data['content'], $data['status'], $data['reading_time'], $data['author_id'], $studyId
                    ]);
                    $success_message = 'Study updated successfully.';
                }

                // Auto-tag the study based on content
                $tagResults = $tagger->tagStudy($studyId);
                if (!empty($tagResults)) {
                    $success_message .= ' Auto-tagged with ' . count($tagResults) . ' topics.';
                }

                // Detect and save cross-references
                $crossRefs = $crossRefManager->saveReferences($studyId);
                if (!empty($crossRefs)) {
                    $success_message .= ' Found ' . count($crossRefs) . ' cross-references.';
                }

                // Update study data for form
                $study = array_merge($study, $data);
                $study['id'] = $studyId;
                $isNew = false;
            }
        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get existing tags for this study
$studyTopics = [];
$allTopics = [];
$studyCrossRefs = [];
if (!$isNew && $studyId) {
    $studyTopics = $tagger->getStudyTopics($studyId);
    $studyCrossRefs = $crossRefManager->getReferencesForStudy($studyId);
}
// Get all topics for manual tagging
$allTopics = $tagger->getTopicsByCategory();

// Function to estimate reading time
function calculateReadingTime($content) {
    $wordCount = str_word_count(strip_tags($content));
    return max(1, round($wordCount / 200)); // 200 words per minute
}

// Get book info if editing
$currentBook = null;
if ($study['book_id']) {
    foreach ($books as $book) {
        if ($book['id'] == $study['book_id']) {
            $currentBook = $book;
            break;
        }
    }
}
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/admin/bible-study" style="color: #667eea; text-decoration: none;">&larr; Back to Bible Studies</a>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="POST">
    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
        <!-- Main Content -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h2><?= $isNew ? 'Create New Study' : 'Edit Study'; ?></h2>
                    <?php if (!$isNew && $study['status'] === 'published' && $currentBook): ?>
                        <a href="/bible-study/<?= htmlspecialchars($currentBook['slug']); ?>/<?= $study['chapter']; ?>" target="_blank" class="btn btn-outline">Preview</a>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="book_id">Book *</label>
                        <select id="book_id" name="book_id" required>
                            <option value="">— Select Book —</option>
                            <optgroup label="Old Testament">
                                <?php foreach ($books as $book): ?>
                                    <?php if ($book['testament'] === 'old'): ?>
                                        <option value="<?= $book['id']; ?>" data-chapters="<?= $book['chapters']; ?>" <?= $study['book_id'] == $book['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($book['name']); ?> (<?= $book['chapters']; ?> chapters)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="New Testament">
                                <?php foreach ($books as $book): ?>
                                    <?php if ($book['testament'] === 'new'): ?>
                                        <option value="<?= $book['id']; ?>" data-chapters="<?= $book['chapters']; ?>" <?= $study['book_id'] == $book['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($book['name']); ?> (<?= $book['chapters']; ?> chapters)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="chapter">Chapter *</label>
                        <input type="number" id="chapter" name="chapter" value="<?= htmlspecialchars($study['chapter']); ?>" min="1" required placeholder="1">
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">Study Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($study['title'] ?? ''); ?>" placeholder="e.g., The Beginning of Creation">
                    <div class="form-help">Optional title for this chapter study.</div>
                </div>

                <div class="form-group">
                    <label for="summary">Summary</label>
                    <textarea id="summary" name="summary" rows="3" placeholder="Brief summary of what this chapter covers..."><?= htmlspecialchars($study['summary'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="content">Study Content</label>
                    <textarea id="content" name="content" rows="30" placeholder="Write your verse-by-verse study here...

Use [1] or [1-5] to mark verse references. These will become clickable anchors.

Example:
[1] In the beginning God created the heavens and the earth.

This verse establishes...

[2-3] And the earth was without form...

HTML is also supported for formatting."><?= htmlspecialchars($study['content'] ?? ''); ?></textarea>
                    <div class="form-help">
                        Use <code>[1]</code> or <code>[1-5]</code> to mark verse references. These become anchors for navigation.
                        HTML tags like &lt;p&gt;, &lt;h2&gt;, &lt;blockquote&gt; are supported.
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Publish Box -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Publish</h3>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= ($study['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?= ($study['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="author_id">Author</label>
                    <select id="author_id" name="author_id">
                        <?php foreach ($authors as $author): ?>
                            <option value="<?= $author['id']; ?>" <?= ($study['author_id'] ?? $current_user['id']) == $author['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($author['full_name'] ?? $author['username'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reading_time">Reading Time (minutes)</label>
                    <input type="number" id="reading_time" name="reading_time" value="<?= intval($study['reading_time'] ?? 0); ?>" min="0" placeholder="Auto-calculated">
                    <div class="form-help">Leave as 0 to auto-calculate.</div>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><?= $isNew ? 'Create Study' : 'Update Study'; ?></button>
                </div>
            </div>

            <!-- Quick Info -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Verse Markers</h3>
                </div>
                <div style="font-size: 0.875rem; color: #64748b;">
                    <p>Use these formats in your content:</p>
                    <ul style="padding-left: 1.5rem; margin: 0.5rem 0;">
                        <li><code>[1]</code> - Single verse</li>
                        <li><code>[1-5]</code> - Verse range</li>
                    </ul>
                    <p style="margin-top: 0.75rem;">These markers become clickable anchors that readers can jump to.</p>
                </div>
            </div>

            <!-- Word Count -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Statistics</h3>
                </div>
                <div style="font-size: 0.875rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                        <span style="color: #64748b;">Word Count</span>
                        <span id="word-count" style="font-weight: 600;">0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                        <span style="color: #64748b;">Verse Markers</span>
                        <span id="verse-count" style="font-weight: 600;">0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                        <span style="color: #64748b;">Est. Reading Time</span>
                        <span id="est-time" style="font-weight: 600;">0 min</span>
                    </div>
                </div>
            </div>

            <?php if (!$isNew && !empty($studyTopics)): ?>
            <!-- Auto-Tagged Topics -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Auto-Tagged Topics</h3>
                </div>
                <div style="font-size: 0.875rem;">
                    <p style="color: #64748b; margin: 0 0 0.75rem;">These topics were automatically detected from the content:</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($studyTopics as $topic): ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: <?= $topic['auto_tagged'] ? '#e0e7ff' : '#d1fae5'; ?>; border-radius: 0.25rem; font-size: 0.75rem;">
                                <?= $topic['icon']; ?> <?= htmlspecialchars($topic['name']); ?>
                                <span style="color: #64748b; font-size: 0.65rem;">(<?= round($topic['relevance_score']); ?>%)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p style="color: #94a3b8; font-size: 0.75rem; margin: 0.75rem 0 0;">Topics update automatically when you save.</p>
                </div>
            </div>
            <?php elseif (!$isNew): ?>
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Topics</h3>
                </div>
                <div style="font-size: 0.875rem; color: #64748b;">
                    <p style="margin: 0;">No topics detected yet. Topics will be auto-tagged when you save the study with content.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$isNew && !empty($studyCrossRefs)): ?>
            <!-- Cross References -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Cross-References</h3>
                </div>
                <div style="font-size: 0.875rem;">
                    <p style="color: #64748b; margin: 0 0 0.75rem;">Scripture references detected in content:</p>
                    <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                        <?php foreach ($studyCrossRefs as $ref): ?>
                            <?php
                            $refText = $ref['book_name'] . ' ' . $ref['target_chapter'];
                            if ($ref['target_verse_start']) {
                                $refText .= ':' . $ref['target_verse_start'];
                                if ($ref['target_verse_end'] && $ref['target_verse_end'] != $ref['target_verse_start']) {
                                    $refText .= '-' . $ref['target_verse_end'];
                                }
                            }
                            ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.375rem 0.5rem; background: #f8fafc; border-radius: 0.25rem;">
                                <span><?= htmlspecialchars($refText); ?></span>
                                <?php if ($ref['linked_study_id']): ?>
                                    <span style="font-size: 0.7rem; color: #10b981;">Has study</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="color: #94a3b8; font-size: 0.75rem; margin: 0.75rem 0 0;">Cross-references update automatically when you save.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// Update statistics as user types
const contentField = document.getElementById('content');
const wordCountEl = document.getElementById('word-count');
const verseCountEl = document.getElementById('verse-count');
const estTimeEl = document.getElementById('est-time');

function updateStats() {
    const content = contentField.value;

    // Word count (strip HTML and verse markers)
    const cleanText = content.replace(/<[^>]*>/g, '').replace(/\[\d+(-\d+)?\]/g, '');
    const words = cleanText.trim().split(/\s+/).filter(w => w.length > 0).length;
    wordCountEl.textContent = words.toLocaleString();

    // Verse markers count
    const verseMatches = content.match(/\[\d+(-\d+)?\]/g);
    const verseCount = verseMatches ? verseMatches.length : 0;
    verseCountEl.textContent = verseCount;

    // Estimated reading time
    const readingTime = Math.max(1, Math.round(words / 200));
    estTimeEl.textContent = readingTime + ' min';
}

contentField.addEventListener('input', updateStats);
updateStats(); // Initial count

// Update chapter max based on book selection
const bookSelect = document.getElementById('book_id');
const chapterInput = document.getElementById('chapter');

bookSelect.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const maxChapters = selected.dataset.chapters;
    if (maxChapters) {
        chapterInput.max = maxChapters;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
