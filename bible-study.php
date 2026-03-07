<?php
/**
 * Bible Study Library - Main Page
 * Browse studies by testament and book
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

// Get all books with study counts
$books = $pdo->query("
    SELECT b.*,
           COUNT(DISTINCT s.chapter) as chapters_available,
           SUM(CASE WHEN s.status = 'published' THEN 1 ELSE 0 END) as published_chapters
    FROM bible_books b
    LEFT JOIN bible_studies s ON b.id = s.book_id AND s.status = 'published'
    GROUP BY b.id
    ORDER BY b.book_order
")->fetchAll();

// Separate by testament
$oldTestament = array_filter($books, fn($b) => $b['testament'] === 'old');
$newTestament = array_filter($books, fn($b) => $b['testament'] === 'new');

// Get recent studies
$recentStudies = $pdo->query("
    SELECT s.*, b.name as book_name, b.slug as book_slug
    FROM bible_studies s
    JOIN bible_books b ON s.book_id = b.id
    WHERE s.status = 'published'
    ORDER BY s.updated_at DESC
    LIMIT 5
")->fetchAll();

// Get total stats
$totalChapters = $pdo->query("SELECT COUNT(*) FROM bible_studies WHERE status = 'published'")->fetchColumn();
$totalBooks = $pdo->query("SELECT COUNT(DISTINCT book_id) FROM bible_studies WHERE status = 'published'")->fetchColumn();

$page_title = 'Bible Study Library | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('bible-study');
}
?>

<section class="page-hero bible-study-hero">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="bible-study" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Bible Study Library'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="bible-study" data-cms-type="text"><?= $cms->text('hero_headline', 'Deep Dive into Scripture'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="bible-study" data-cms-type="text"><?= $cms->text('hero_subtext', 'In-depth, verse-by-verse studies through every book of the Bible.'); ?></p>

        <!-- Search Bar -->
        <form action="/bible-study/search" method="GET" class="study-search-form hero-search">
            <input type="text" name="q" placeholder="Search studies or enter a verse (e.g., John 3:16)..." aria-label="Search Bible studies" style="background: rgba(255,255,255,0.15) !important; border-color: rgba(255,255,255,0.3) !important; color: #fff !important; -webkit-appearance: none !important;">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <div class="hero-topics-link">
            <a href="/bible-study/topics" class="btn btn-outline btn-sm">Browse by Life Topic</a>
        </div>

        <!-- Stats -->
        <?php if ($totalChapters > 0): ?>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat-number"><?= $totalBooks; ?></span>
                <span class="hero-stat-label">Books Covered</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number"><?= $totalChapters; ?></span>
                <span class="hero-stat-label">Chapter Studies</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number">66</span>
                <span class="hero-stat-label">Books of the Bible</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Recent Studies -->
<?php if (!empty($recentStudies)): ?>
<section class="bible-study-recent">
    <div class="container">
        <h2>Recently Added</h2>
        <div class="recent-studies-grid">
            <?php foreach ($recentStudies as $study): ?>
                <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" class="recent-study-card">
                    <span class="study-book"><?= htmlspecialchars($study['book_name']); ?></span>
                    <span class="study-chapter">Chapter <?= $study['chapter']; ?></span>
                    <?php if ($study['title']): ?>
                        <span class="study-title"><?= htmlspecialchars($study['title']); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Old Testament -->
<section class="bible-study-testament">
    <div class="container">
        <h2>Old Testament</h2>
        <p class="testament-description">39 books from Genesis to Malachi</p>
        <div class="books-grid">
            <?php foreach ($oldTestament as $book): ?>
                <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="book-card <?= $book['published_chapters'] > 0 ? 'has-content' : 'no-content'; ?>">
                    <span class="book-name"><?= htmlspecialchars($book['name']); ?></span>
                    <span class="book-chapters"><?= $book['chapters']; ?> chapters</span>
                    <?php if ($book['published_chapters'] > 0): ?>
                        <span class="book-progress"><?= $book['published_chapters']; ?> study</span>
                    <?php else: ?>
                        <span class="book-coming-soon">Coming soon</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- New Testament -->
<section class="bible-study-testament">
    <div class="container">
        <h2>New Testament</h2>
        <p class="testament-description">27 books from Matthew to Revelation</p>
        <div class="books-grid">
            <?php foreach ($newTestament as $book): ?>
                <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="book-card <?= $book['published_chapters'] > 0 ? 'has-content' : 'no-content'; ?>">
                    <span class="book-name"><?= htmlspecialchars($book['name']); ?></span>
                    <span class="book-chapters"><?= $book['chapters']; ?> chapters</span>
                    <?php if ($book['published_chapters'] > 0): ?>
                        <span class="book-progress"><?= $book['published_chapters']; ?> study</span>
                    <?php else: ?>
                        <span class="book-coming-soon">Coming soon</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- About This Resource -->
<section class="bible-study-about">
    <div class="container narrow">
        <div class="about-card" data-cms-editable="about_content" data-cms-page="bible-study" data-cms-type="html">
            <?= $cms->html('about_content', '<h2>About These Studies</h2>
<p>These in-depth Bible studies are written to help you understand Scripture more deeply. Each study examines the text verse-by-verse, exploring the historical context, original language, and practical application for today.</p>
<p>Whether you\'re new to Bible study or have been reading Scripture for years, we pray these resources will enrich your understanding and deepen your faith.</p>'); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
