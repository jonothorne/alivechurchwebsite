<?php
/**
 * Bible Study - Search Page
 * Search studies by keyword or verse reference
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

$query = trim($_GET['q'] ?? '');
$results = [];
$verseMatch = null;

if (!empty($query)) {
    // Check if query is a verse reference (e.g., "John 3:16", "Genesis 1:1-5", "Rom 8")
    $versePattern = '/^(\d?\s*[a-zA-Z]+)\s*(\d+)(?::(\d+)(?:-(\d+))?)?$/';

    if (preg_match($versePattern, $query, $matches)) {
        $bookQuery = trim($matches[1]);
        $chapterNum = intval($matches[2]);
        $verseStart = isset($matches[3]) ? intval($matches[3]) : null;
        $verseEnd = isset($matches[4]) ? intval($matches[4]) : $verseStart;

        // Find the book
        $bookStmt = $pdo->prepare("
            SELECT * FROM bible_books
            WHERE name LIKE ? OR abbreviation LIKE ? OR slug LIKE ?
            LIMIT 1
        ");
        $searchBook = "%$bookQuery%";
        $bookStmt->execute([$searchBook, $searchBook, $searchBook]);
        $book = $bookStmt->fetch();

        if ($book) {
            // Check if we have a study for this chapter
            $studyStmt = $pdo->prepare("
                SELECT s.*, b.name as book_name, b.slug as book_slug
                FROM bible_studies s
                JOIN bible_books b ON s.book_id = b.id
                WHERE s.book_id = ? AND s.chapter = ? AND s.status = 'published'
            ");
            $studyStmt->execute([$book['id'], $chapterNum]);
            $study = $studyStmt->fetch();

            if ($study) {
                $verseMatch = [
                    'book' => $book,
                    'chapter' => $chapterNum,
                    'verse_start' => $verseStart,
                    'verse_end' => $verseEnd,
                    'study' => $study
                ];
            }
        }
    }

    // Search in study content using LIKE (reliable fallback)
    $likeQuery = "%$query%";

    // Try full-text search first, fall back to LIKE if not available
    $useFullText = false;
    try {
        // Check if fulltext index exists
        $indexCheck = $pdo->query("SHOW INDEX FROM bible_studies WHERE Index_type = 'FULLTEXT'");
        $useFullText = $indexCheck->rowCount() > 0;
    } catch (Exception $e) {
        $useFullText = false;
    }

    if ($useFullText) {
        try {
            $searchStmt = $pdo->prepare("
                SELECT s.*, b.name as book_name, b.slug as book_slug,
                       MATCH(s.content, s.title, s.summary) AGAINST (? IN NATURAL LANGUAGE MODE) as relevance
                FROM bible_studies s
                JOIN bible_books b ON s.book_id = b.id
                WHERE s.status = 'published'
                  AND (
                      MATCH(s.content, s.title, s.summary) AGAINST (? IN NATURAL LANGUAGE MODE)
                      OR s.content LIKE ?
                      OR s.title LIKE ?
                  )
                ORDER BY relevance DESC
                LIMIT 20
            ");
            $searchStmt->execute([$query, $query, $likeQuery, $likeQuery]);
            $results = $searchStmt->fetchAll();
        } catch (Exception $e) {
            $useFullText = false; // Fall through to LIKE search
        }
    }

    if (!$useFullText) {
        // Use LIKE search (works without fulltext index)
        $searchStmt = $pdo->prepare("
            SELECT s.*, b.name as book_name, b.slug as book_slug, 0 as relevance
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            WHERE s.status = 'published'
              AND (s.content LIKE ? OR s.title LIKE ? OR s.summary LIKE ?)
            ORDER BY b.book_order, s.chapter
            LIMIT 20
        ");
        $searchStmt->execute([$likeQuery, $likeQuery, $likeQuery]);
        $results = $searchStmt->fetchAll();
    }
}

// Function to extract snippet around search term
function getSnippet($content, $query, $length = 200) {
    $content = strip_tags($content);
    $pos = stripos($content, $query);
    if ($pos === false) {
        return substr($content, 0, $length) . '...';
    }
    $start = max(0, $pos - $length / 2);
    $snippet = substr($content, $start, $length);
    if ($start > 0) $snippet = '...' . $snippet;
    if ($start + $length < strlen($content)) $snippet .= '...';
    // Highlight the search term
    $snippet = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $snippet);
    return $snippet;
}

$page_title = 'Search Bible Studies | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Bible Study Library</p>
        <h1>Search Studies</h1>
    </div>
</section>

<section class="bible-study-search-page">
    <div class="container">
        <!-- Search Form -->
        <form action="/bible-study/search" method="GET" class="study-search-form large">
            <input type="text" name="q" value="<?= htmlspecialchars($query); ?>" placeholder="Search by keyword or verse (e.g., &quot;grace&quot; or &quot;John 3:16&quot;)..." aria-label="Search Bible studies">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <?php if (!empty($query)): ?>
            <!-- Verse Match -->
            <?php if ($verseMatch): ?>
                <div class="verse-match-result">
                    <h2>Verse Reference Found</h2>
                    <a href="/bible-study/<?= htmlspecialchars($verseMatch['study']['book_slug']); ?>/<?= $verseMatch['chapter']; ?><?= $verseMatch['verse_start'] ? '#v' . $verseMatch['verse_start'] : ''; ?>" class="verse-match-card">
                        <span class="verse-ref">
                            <?= htmlspecialchars($verseMatch['book']['name']); ?> <?= $verseMatch['chapter']; ?><?php if ($verseMatch['verse_start']): ?>:<?= $verseMatch['verse_start']; ?><?php if ($verseMatch['verse_end'] && $verseMatch['verse_end'] !== $verseMatch['verse_start']): ?>-<?= $verseMatch['verse_end']; ?><?php endif; ?><?php endif; ?>
                        </span>
                        <span class="verse-action">Go to study &rarr;</span>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Search Results -->
            <div class="search-results">
                <h2><?= count($results); ?> result<?= count($results) !== 1 ? 's' : ''; ?> for "<?= htmlspecialchars($query); ?>"</h2>

                <?php if (empty($results) && !$verseMatch): ?>
                    <div class="no-results">
                        <p>No studies found matching your search.</p>
                        <p>Try:</p>
                        <ul>
                            <li>Using different keywords</li>
                            <li>Searching for a specific verse (e.g., "John 3:16")</li>
                            <li>Browsing by book instead</li>
                        </ul>
                        <a href="/bible-study" class="btn btn-outline">Browse All Books</a>
                    </div>
                <?php else: ?>
                    <div class="results-list">
                        <?php foreach ($results as $result): ?>
                            <a href="/bible-study/<?= htmlspecialchars($result['book_slug']); ?>/<?= $result['chapter']; ?>" class="search-result-card">
                                <div class="result-header">
                                    <span class="result-book"><?= htmlspecialchars($result['book_name']); ?> <?= $result['chapter']; ?></span>
                                    <?php if ($result['title']): ?>
                                        <span class="result-title"><?= htmlspecialchars($result['title']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="result-snippet"><?= getSnippet($result['content'], $query); ?></p>
                                <?php if ($result['reading_time']): ?>
                                    <span class="result-meta"><?= $result['reading_time']; ?> min read</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Search Tips -->
            <div class="search-tips">
                <h2>Search Tips</h2>
                <div class="tips-grid">
                    <div class="tip">
                        <h3>Search by Topic</h3>
                        <p>Enter keywords like "salvation", "prayer", "faith" to find relevant studies.</p>
                    </div>
                    <div class="tip">
                        <h3>Search by Verse</h3>
                        <p>Enter a reference like "John 3:16" or "Romans 8:28" to jump directly to that passage.</p>
                    </div>
                    <div class="tip">
                        <h3>Search by Book</h3>
                        <p>Enter a book name like "Genesis" or "Philippians" to see all available studies.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/bible-study" class="btn btn-outline">&larr; Back to Library</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
