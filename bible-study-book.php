<?php
/**
 * Bible Study - Book Overview Page
 * Shows all available chapters for a specific book
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

// Get book slug from URL
$bookSlug = $_GET['book'] ?? '';
if (empty($bookSlug)) {
    header('Location: /bible-study');
    exit;
}

// Get book info
$stmt = $pdo->prepare("SELECT * FROM bible_books WHERE slug = ?");
$stmt->execute([$bookSlug]);
$book = $stmt->fetch();

if (!$book) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Book Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero <?= $hero_texture_class; ?>">
        <div class="container narrow">
            <h1>Book Not Found</h1>
            <p>Sorry, we couldn't find that book of the Bible.</p>
            <a href="/bible-study" class="btn btn-primary">Back to Library</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get all studies for this book
$studiesStmt = $pdo->prepare("
    SELECT s.*, u.full_name as author_name
    FROM bible_studies s
    LEFT JOIN users u ON s.author_id = u.id
    WHERE s.book_id = ? AND s.status = 'published'
    ORDER BY s.chapter
");
$studiesStmt->execute([$book['id']]);
$studies = $studiesStmt->fetchAll();

// Create a map of available chapters
$studyMap = [];
foreach ($studies as $study) {
    $studyMap[$study['chapter']] = $study;
}

// Get adjacent books for navigation
$prevBook = $pdo->prepare("SELECT * FROM bible_books WHERE book_order = ?");
$prevBook->execute([$book['book_order'] - 1]);
$prevBook = $prevBook->fetch();

$nextBook = $pdo->prepare("SELECT * FROM bible_books WHERE book_order = ?");
$nextBook->execute([$book['book_order'] + 1]);
$nextBook = $nextBook->fetch();

$page_title = $book['name'] . ' Bible Study | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="study-book-hero">
    <div class="container">
        <div class="book-hero-content">
            <a href="/bible-study" class="back-link">&larr; Bible Study Library</a>
            <span class="testament-badge"><?= $book['testament'] === 'old' ? 'Old Testament' : 'New Testament'; ?></span>
            <h1><?= htmlspecialchars($book['name']); ?></h1>
            <p class="book-meta"><?= $book['chapters']; ?> chapters · <?= count($studies); ?> studies available</p>
            <?php if ($book['description']): ?>
                <p class="book-description"><?= htmlspecialchars($book['description']); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="study-chapters-section">
    <div class="container">
        <?php if (empty($studies)): ?>
            <div class="no-studies-message">
                <h2>Studies Coming Soon</h2>
                <p>We're currently working on studies for <?= htmlspecialchars($book['name']); ?>. Check back soon!</p>
                <a href="/bible-study" class="btn btn-outline">Browse Other Books</a>
            </div>
        <?php else: ?>
            <h2>Available Chapters</h2>
            <div class="chapters-grid">
                <?php for ($i = 1; $i <= $book['chapters']; $i++): ?>
                    <?php $hasStudy = isset($studyMap[$i]); ?>
                    <?php if ($hasStudy): ?>
                        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $i; ?>" class="chapter-card available">
                            <span class="chapter-number"><?= $i; ?></span>
                            <?php if ($studyMap[$i]['title']): ?>
                                <span class="chapter-title"><?= htmlspecialchars($studyMap[$i]['title']); ?></span>
                            <?php endif; ?>
                            <?php if ($studyMap[$i]['reading_time']): ?>
                                <span class="chapter-time"><?= $studyMap[$i]['reading_time']; ?> min read</span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <div class="chapter-card unavailable">
                            <span class="chapter-number"><?= $i; ?></span>
                            <span class="chapter-status">Coming soon</span>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Book Navigation -->
<section class="study-book-nav">
    <div class="container">
        <div class="book-nav-inner">
            <?php if ($prevBook): ?>
                <a href="/bible-study/<?= htmlspecialchars($prevBook['slug']); ?>" class="book-nav-link prev">
                    <span class="nav-direction">&larr; Previous Book</span>
                    <span class="nav-book-name"><?= htmlspecialchars($prevBook['name']); ?></span>
                </a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <a href="/bible-study" class="btn btn-outline">All Books</a>

            <?php if ($nextBook): ?>
                <a href="/bible-study/<?= htmlspecialchars($nextBook['slug']); ?>" class="book-nav-link next">
                    <span class="nav-direction">Next Book &rarr;</span>
                    <span class="nav-book-name"><?= htmlspecialchars($nextBook['name']); ?></span>
                </a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
