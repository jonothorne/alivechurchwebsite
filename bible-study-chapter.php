<?php
/**
 * Bible Study - Chapter Study Page
 * Shows the full study content for a specific chapter
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/BibleStudyTagger.php';
require_once __DIR__ . '/includes/CrossReferenceManager.php';
require_once __DIR__ . '/includes/UserStudies.php';
require_once __DIR__ . '/includes/SermonManager.php';

$tagger = new BibleStudyTagger($pdo);
$crossRefManager = new CrossReferenceManager($pdo);
$sermonManager = new SermonManager($pdo);

// Check if user is logged in for save/highlight features
$userStudies = $current_user ? new UserStudies($pdo, $current_user['id']) : null;

// Check if user can edit (editor or admin)
$canEdit = $auth->isEditor();
$currentUser = $current_user; // Keep compatibility with rest of file

// Get book and chapter from URL
$bookSlug = $_GET['book'] ?? '';
$chapter = intval($_GET['chapter'] ?? 0);

if (empty($bookSlug) || $chapter < 1) {
    header('Location: /bible-study');
    exit;
}

// Get book info
$bookStmt = $pdo->prepare("SELECT * FROM bible_books WHERE slug = ?");
$bookStmt->execute([$bookSlug]);
$book = $bookStmt->fetch();

if (!$book) {
    header('Location: /bible-study');
    exit;
}

// Get study content
$studyStmt = $pdo->prepare("
    SELECT s.*, u.full_name as author_name, u.username as author_username
    FROM bible_studies s
    LEFT JOIN users u ON s.author_id = u.id
    WHERE s.book_id = ? AND s.chapter = ? AND s.status = 'published'
");
$studyStmt->execute([$book['id'], $chapter]);
$study = $studyStmt->fetch();

if (!$study) {
    // Get adjacent chapters for navigation even when study is not available
    $prevChapter = $chapter > 1 ? $chapter - 1 : null;
    $nextChapter = $chapter < $book['chapters'] ? $chapter + 1 : null;

    // Get all available chapters with studies for sidebar navigation
    $availableChaptersStmt = $pdo->prepare("SELECT chapter, title FROM bible_studies WHERE book_id = ? AND status = 'published' ORDER BY chapter");
    $availableChaptersStmt->execute([$book['id']]);
    $availableChapters = $availableChaptersStmt->fetchAll();

    // Create lookup of chapters with studies
    $chaptersWithStudies = [];
    foreach ($availableChapters as $ch) {
        $chaptersWithStudies[$ch['chapter']] = $ch['title'];
    }

    header('HTTP/1.0 404 Not Found');
    $page_title = $book['name'] . ' ' . $chapter . ' - Study Not Available | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <?php $random_texture = get_random_texture(); ?>
    <article class="bible-study-article">
        <!-- Study Header -->
        <header class="study-header <?= $random_texture; ?>">
            <div class="container">
                <div class="study-header-content">
                    <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="back-link">&larr; <?= htmlspecialchars($book['name']); ?></a>
                    <span class="testament-badge"><?= $book['testament'] === 'old' ? 'Old Testament' : 'New Testament'; ?></span>
                    <h1><?= htmlspecialchars($book['name']); ?> <?= $chapter; ?></h1>
                    <p class="study-title">Study Not Yet Available</p>
                </div>
            </div>
        </header>

        <div class="study-layout">
            <!-- Sidebar Navigation -->
            <aside class="study-sidebar">
                <div class="sidebar-sticky">
                    <!-- Chapter Navigator -->
                    <div class="sidebar-section">
                        <h3>Chapters</h3>
                        <div class="chapter-grid">
                            <?php for ($i = 1; $i <= $book['chapters']; $i++): ?>
                                <?php $hasStudy = isset($chaptersWithStudies[$i]); ?>
                                <?php if ($hasStudy): ?>
                                    <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $i; ?>"
                                       class="chapter-grid-item"
                                       <?php if (isset($chaptersWithStudies[$i]) && $chaptersWithStudies[$i]): ?>
                                       title="<?= htmlspecialchars($chaptersWithStudies[$i]); ?>"
                                       <?php endif; ?>>
                                        <?= $i; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="chapter-grid-item unavailable <?= $i == $chapter ? 'active' : ''; ?>"><?= $i; ?></span>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <button class="browse-all-books-btn" id="study-nav-toggle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            Browse All Books
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="study-content">
                <div class="container narrow">
                    <div class="study-not-available">
                        <div class="not-available-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                <line x1="9" y1="10" x2="15" y2="10"/>
                            </svg>
                        </div>
                        <h2>Study Coming Soon</h2>
                        <p>The study for <?= htmlspecialchars($book['name']); ?> chapter <?= $chapter; ?> is not yet available. We're working on adding more studies regularly.</p>
                        <p>We have a small team, and there's a lot of bible studies to write! If you're interested in volunteering and helping us create bible studies that connect people to God's word, then reach out and <a href="/contact-us">Contact Us</a></p>
                        <?php if (!empty($availableChapters)): ?>
                            <p class="available-hint">In the meantime, explore one of the <?= count($availableChapters); ?> available <?= htmlspecialchars($book['name']); ?> studies using the chapter navigation.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Chapter Navigation -->
                    <nav class="chapter-pagination">
                        <?php if ($prevChapter): ?>
                            <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $prevChapter; ?>" class="chapter-nav prev">
                                <span class="nav-direction">&larr; Previous</span>
                                <span class="nav-chapter">Chapter <?= $prevChapter; ?></span>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="btn btn-outline">All Chapters</a>

                        <?php if ($nextChapter): ?>
                            <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $nextChapter; ?>" class="chapter-nav next">
                                <span class="nav-direction">Next &rarr;</span>
                                <span class="nav-chapter">Chapter <?= $nextChapter; ?></span>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </article>

    <!-- Mobile Floating Action Bar -->
    <div class="mobile-study-bar">
        <button class="mobile-bar-btn mobile-chapters-btn" aria-label="Chapters" data-panel="chapters">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <span>Ch. <?= $chapter; ?></span>
        </button>

        <?php if ($prevChapter): ?>
            <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $prevChapter; ?>" class="mobile-bar-btn" aria-label="Previous chapter">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>
        <?php else: ?>
            <span class="mobile-bar-btn disabled"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></span>
        <?php endif; ?>

        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="mobile-bar-btn" aria-label="All chapters">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
        </a>

        <?php if ($nextChapter): ?>
            <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $nextChapter; ?>" class="mobile-bar-btn" aria-label="Next chapter">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        <?php else: ?>
            <span class="mobile-bar-btn disabled"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
        <?php endif; ?>

        <button class="mobile-bar-btn mobile-browse-books-btn" aria-label="Browse all books">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
            </svg>
        </button>
    </div>

    <!-- Mobile Slide-up Panels -->
    <div class="mobile-panel-overlay"></div>

    <div class="mobile-panel" id="chapters-panel">
        <div class="mobile-panel-header">
            <h3><?= htmlspecialchars($book['name']); ?></h3>
            <button class="mobile-panel-close" aria-label="Close">&times;</button>
        </div>
        <div class="mobile-panel-content">
            <div class="mobile-chapter-grid">
                <?php for ($i = 1; $i <= $book['chapters']; $i++): ?>
                    <?php $hasStudy = isset($chaptersWithStudies[$i]); ?>
                    <?php if ($hasStudy): ?>
                        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $i; ?>"
                           class="mobile-chapter-item">
                            <?= $i; ?>
                        </a>
                    <?php else: ?>
                        <span class="mobile-chapter-item unavailable <?= $i == $chapter ? 'active' : ''; ?>"><?= $i; ?></span>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script>
    // Mobile panel functionality for not-available page
    (function() {
        const overlay = document.querySelector('.mobile-panel-overlay');
        const panels = document.querySelectorAll('.mobile-panel');

        function openPanel(panelId) {
            const panel = document.getElementById(panelId + '-panel');
            if (panel) {
                overlay.classList.add('active');
                panel.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeAllPanels() {
            overlay.classList.remove('active');
            panels.forEach(p => p.classList.remove('active'));
            document.body.style.overflow = '';
        }

        // Panel triggers
        document.querySelectorAll('[data-panel]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                openPanel(btn.dataset.panel);
            });
        });

        // Close buttons
        document.querySelectorAll('.mobile-panel-close').forEach(btn => {
            btn.addEventListener('click', closeAllPanels);
        });

        // Close on overlay click
        if (overlay) {
            overlay.addEventListener('click', closeAllPanels);
        }
    })();
    </script>

    <?php
    include __DIR__ . '/includes/bible-study-navigator.php';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get verse markers from content
$verseMarkers = [];
preg_match_all('/\[(\d+)(?:-(\d+))?\]/', $study['content'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
foreach ($matches as $match) {
    $verseMarkers[] = [
        'start' => intval($match[1][0]),
        'end' => isset($match[2]) ? intval($match[2][0]) : intval($match[1][0]),
        'label' => $match[0][0]
    ];
}

// Process content - convert verse markers to anchors
$processedContent = $study['content'];
$processedContent = preg_replace(
    '/\[(\d+)(?:-(\d+))?\]/',
    '<span class="verse-marker" id="v$1">$0</span>',
    $processedContent
);

// Convert Scripture references to clickable links
$processedContent = $crossRefManager->linkifyReferences($processedContent, $book['id'], $chapter);

// Get adjacent chapters - navigate to any chapter, showing "unavailable" message if no study
$prevChapter = $chapter > 1 ? $chapter - 1 : null;
$nextChapter = $chapter < $book['chapters'] ? $chapter + 1 : null;

// Get all available chapters with studies for sidebar navigation
$availableChaptersStmt = $pdo->prepare("SELECT chapter, title FROM bible_studies WHERE book_id = ? AND status = 'published' ORDER BY chapter");
$availableChaptersStmt->execute([$book['id']]);
$availableChapters = $availableChaptersStmt->fetchAll();

// Create lookup of chapters with studies
$chaptersWithStudies = [];
foreach ($availableChapters as $ch) {
    $chaptersWithStudies[$ch['chapter']] = $ch['title'];
}

// Get topics for this study
$studyTopics = $tagger->getStudyTopics($study['id']);

// Get cross-references
$crossReferences = $crossRefManager->getReferencesForStudy($study['id']);

// Get related studies
$relatedStudies = $crossRefManager->getRelatedStudies($study['id'], 4);

// Get related sermons
$relatedSermons = $sermonManager->getRelatedSermonsForStudy($study['id']);

// Get user's saved status and highlights for this study
$isSaved = false;
$userHighlights = [];
if ($userStudies) {
    $isSaved = $userStudies->isStudySaved($study['id']);
    $userHighlights = $userStudies->getStudyHighlights($study['id']);
}

$page_title = $book['name'] . ' ' . $chapter . ' Study | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<article class="bible-study-article<?= $canEdit ? ' bible-study-editable' : ''; ?>"<?= $canEdit ? ' data-study-id="' . $study['id'] . '"' : ''; ?>>
    <?php if ($canEdit): ?>
    <!-- Editor Status Toggle -->
    <button class="study-status-toggle <?= $study['status'] === 'published' ? 'published' : ''; ?>"
            data-status="<?= htmlspecialchars($study['status']); ?>"
            title="Click to toggle status">
        <span class="status-indicator"></span>
        <span class="status-text"><?= $study['status'] === 'published' ? 'Published' : 'Draft'; ?></span>
    </button>
    <?php endif; ?>

    <!-- Print-only Header (hidden on screen, visible when printing) -->
    <div class="print-header screen-hidden">
        <div class="print-logo">
            <img src="/assets/imgs/logo.png" alt="Alive Church">
        </div>
        <div class="print-title">
            <h1><?= htmlspecialchars($book['name']); ?> <?= $chapter; ?></h1>
            <?php if ($study['title']): ?>
                <p class="print-subtitle"><?= htmlspecialchars($study['title']); ?></p>
            <?php endif; ?>
        </div>
        <div class="print-meta">
            <?php if ($study['author_name']): ?>
                <span>By <?= htmlspecialchars($study['author_name']); ?></span>
            <?php endif; ?>
            <span><?= $book['testament'] === 'old' ? 'Old Testament' : 'New Testament'; ?></span>
            <?php if ($study['reading_time']): ?>
                <span><?= $study['reading_time']; ?> min read</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Study Header -->
    <?php $random_texture = get_random_texture(); ?>
    <header class="study-header <?= $random_texture; ?>">
        <div class="container">
            <div class="study-header-content">
                <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="back-link">&larr; <?= htmlspecialchars($book['name']); ?></a>
                <span class="testament-badge"><?= $book['testament'] === 'old' ? 'Old Testament' : 'New Testament'; ?></span>
                <h1><?= htmlspecialchars($book['name']); ?> <?= $chapter; ?></h1>
                <?php if ($study['title'] || $canEdit): ?>
                    <p class="study-title"<?= $canEdit ? ' data-editable="title"' : ''; ?>><?= htmlspecialchars($study['title'] ?: ($canEdit ? 'Click to add title...' : '')); ?></p>
                <?php endif; ?>
                <div class="study-meta">
                    <?php if ($study['author_name']): ?>
                        <span class="study-author">By <a href="/author/<?= htmlspecialchars($study['author_username'] ?? ''); ?>" class="author-link"><?= htmlspecialchars($study['author_name']); ?></a></span>
                    <?php endif; ?>
                    <?php if ($study['reading_time']): ?>
                        <span class="study-time"><?= $study['reading_time']; ?> min read</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($studyTopics)): ?>
                <div class="study-topics">
                    <?php foreach (array_slice($studyTopics, 0, 5) as $topic): ?>
                        <a href="/bible-study/topics/<?= htmlspecialchars($topic['slug']); ?>" class="study-topic-tag">
                            <?= $topic['icon']; ?> <?= htmlspecialchars($topic['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="study-layout">
        <!-- Sidebar Navigation -->
        <aside class="study-sidebar">
            <div class="sidebar-sticky">
                <?php if ($currentUser): ?>
                <!-- Save/Bookmark Button -->
                <div class="sidebar-section sidebar-actions">
                    <button class="save-study-btn <?= $isSaved ? 'saved' : ''; ?>"
                            data-study-id="<?= $study['id']; ?>"
                            title="<?= $isSaved ? 'Saved to My Studies' : 'Save to My Studies'; ?>">
                        <svg class="bookmark-icon" width="20" height="20" viewBox="0 0 24 24" fill="<?= $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span class="save-text"><?= $isSaved ? 'Saved' : 'Save'; ?></span>
                    </button>
                </div>
                <?php else: ?>
                <div class="sidebar-section sidebar-actions">
                    <button class="save-study-btn login-prompt-trigger" data-feature="save studies to your library" title="Log in to save">
                        <svg class="bookmark-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span class="save-text">Save</span>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Chapter Navigator -->
                <div class="sidebar-section">
                    <h3>Chapters</h3>
                    <div class="chapter-grid">
                        <?php for ($i = 1; $i <= $book['chapters']; $i++): ?>
                            <?php $hasStudy = isset($chaptersWithStudies[$i]); ?>
                            <?php if ($hasStudy): ?>
                                <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $i; ?>"
                                   class="chapter-grid-item <?= $i == $chapter ? 'active' : ''; ?>"
                                   <?php if (isset($chaptersWithStudies[$i]) && $chaptersWithStudies[$i]): ?>
                                   title="<?= htmlspecialchars($chaptersWithStudies[$i]); ?>"
                                   <?php endif; ?>>
                                    <?= $i; ?>
                                </a>
                            <?php else: ?>
                                <span class="chapter-grid-item unavailable"><?= $i; ?></span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <button class="browse-all-books-btn" id="study-nav-toggle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                        </svg>
                        Browse All Books
                    </button>
                </div>

                <?php if (!empty($verseMarkers)): ?>
                <!-- Verse Navigation -->
                <div class="sidebar-section">
                    <h3>Jump to Verse</h3>
                    <div class="verse-nav-list">
                        <?php foreach ($verseMarkers as $marker): ?>
                            <a href="#v<?= $marker['start']; ?>" class="verse-nav-item">
                                <?php if ($marker['start'] === $marker['end']): ?>
                                    v. <?= $marker['start']; ?>
                                <?php else: ?>
                                    vv. <?= $marker['start']; ?>-<?= $marker['end']; ?>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </aside>

        <!-- Main Content -->
        <div class="study-content">
            <div class="container narrow">
                <?php if ($study['summary'] || $canEdit): ?>
                    <div class="study-summary">
                        <h2>Overview</h2>
                        <p<?= $canEdit ? ' data-editable="summary"' : ''; ?>><?= htmlspecialchars($study['summary'] ?: ($canEdit ? 'Click to add summary...' : '')); ?></p>
                    </div>
                <?php endif; ?>

                <div class="study-body">
                    <div class="study-actions">
                        <button id="listen-btn" class="study-action-btn listen-fab" title="Listen to study">
                            <svg class="play-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor"/>
                                <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                                <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                            </svg>
                            <svg class="pause-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                                <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                            </svg>
                        </button>
                        <div class="font-settings-wrapper">
                            <button id="font-settings-btn" class="study-action-btn" title="Font settings">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <text x="3" y="17" font-size="14" font-weight="bold" fill="currentColor" stroke="none">A</text>
                                    <text x="13" y="17" font-size="10" fill="currentColor" stroke="none">A</text>
                                </svg>
                            </button>
                            <div id="font-settings-panel" class="font-settings-panel">
                                <div class="font-setting-group">
                                    <label>Size</label>
                                    <div class="font-size-controls">
                                        <button class="font-size-btn" data-action="decrease" title="Decrease font size">−</button>
                                        <span class="font-size-display">100%</span>
                                        <button class="font-size-btn" data-action="increase" title="Increase font size">+</button>
                                    </div>
                                </div>
                                <div class="font-setting-group">
                                    <label>Font</label>
                                    <select id="font-family-select" class="font-family-select">
                                        <option value="default">Default</option>
                                        <option value="serif">Serif</option>
                                        <option value="sans-serif">Sans-serif</option>
                                        <option value="georgia">Georgia</option>
                                        <option value="times">Times New Roman</option>
                                        <option value="arial">Arial</option>
                                        <option value="verdana">Verdana</option>
                                        <option value="openDyslexic">OpenDyslexic</option>
                                    </select>
                                </div>
                                <button class="font-reset-btn" title="Reset to defaults">Reset</button>
                            </div>
                        </div>
                        <button onclick="window.print()" class="study-action-btn print-fab" title="Print study">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 6 2 18 2 18 9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                        </button>
                    </div>
                    <div class="study-text"<?= $canEdit ? ' data-editable="content" data-raw-content="' . htmlspecialchars($study['content'], ENT_QUOTES) . '"' : ''; ?>>
                        <?= $processedContent; ?>
                    </div>
                </div>

                <?php if (!empty($crossReferences)): ?>
                <!-- Cross References -->
                <div class="cross-references">
                    <h3>Scripture References</h3>
                    <div class="cross-ref-list">
                        <?php foreach ($crossReferences as $ref): ?>
                            <?php
                            $refText = $ref['book_name'] . ' ' . $ref['target_chapter'];
                            if ($ref['target_verse_start']) {
                                $refText .= ':' . $ref['target_verse_start'];
                                if ($ref['target_verse_end'] && $ref['target_verse_end'] != $ref['target_verse_start']) {
                                    $refText .= '-' . $ref['target_verse_end'];
                                }
                            }
                            $hasStudy = !empty($ref['linked_study_id']);
                            ?>
                            <?php if ($hasStudy): ?>
                                <a href="/bible-study/<?= htmlspecialchars($ref['book_slug']); ?>/<?= $ref['target_chapter']; ?><?= $ref['target_verse_start'] ? '#v' . $ref['target_verse_start'] : ''; ?>" class="cross-ref-link has-study">
                                    <?= htmlspecialchars($refText); ?>
                                    <span class="study-available">Study available</span>
                                </a>
                            <?php else: ?>
                                <span class="cross-ref-link no-study">
                                    <?= htmlspecialchars($refText); ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($relatedStudies)): ?>
                <!-- Related Studies -->
                <div class="related-studies">
                    <h3>Related Studies</h3>
                    <div class="related-studies-grid">
                        <?php foreach ($relatedStudies as $related): ?>
                            <a href="/bible-study/<?= htmlspecialchars($related['book_slug']); ?>/<?= $related['chapter']; ?>" class="related-study-card">
                                <span class="related-book"><?= htmlspecialchars($related['book_name']); ?> <?= $related['chapter']; ?></span>
                                <?php if ($related['title']): ?>
                                    <span class="related-title"><?= htmlspecialchars($related['title']); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($relatedSermons)): ?>
                <!-- Related Sermons -->
                <div class="related-sermons">
                    <h3>Related Sermons</h3>
                    <div class="related-sermons-list">
                        <?php foreach ($relatedSermons as $sermon): ?>
                            <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" class="related-sermon-card">
                                <?php if ($sermon['thumbnail_url'] || $sermon['youtube_video_id']): ?>
                                    <div class="sermon-thumb">
                                        <?php if ($sermon['thumbnail_url']): ?>
                                            <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" alt="">
                                        <?php elseif ($sermon['youtube_video_id']): ?>
                                            <img src="https://img.youtube.com/vi/<?= htmlspecialchars($sermon['youtube_video_id']); ?>/mqdefault.jpg" alt="">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="sermon-info">
                                    <span class="sermon-title"><?= htmlspecialchars($sermon['title']); ?></span>
                                    <span class="sermon-meta">
                                        <?= $sermon['speaker'] ? htmlspecialchars($sermon['speaker']) : ''; ?>
                                        <?= $sermon['sermon_date'] ? ' • ' . date('M j, Y', strtotime($sermon['sermon_date'])) : ''; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Chapter Navigation -->
                <nav class="chapter-pagination">
                    <?php if ($prevChapter): ?>
                        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $prevChapter; ?>" class="chapter-nav prev">
                            <span class="nav-direction">&larr; Previous</span>
                            <span class="nav-chapter">Chapter <?= $prevChapter; ?></span>
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="btn btn-outline">All Chapters</a>

                    <?php if ($nextChapter): ?>
                        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $nextChapter; ?>" class="chapter-nav next">
                            <span class="nav-direction">Next &rarr;</span>
                            <span class="nav-chapter">Chapter <?= $nextChapter; ?></span>
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- Print-only Footer (hidden on screen, visible when printing) -->
    <div class="print-footer screen-hidden">
        <p>Bible studies produced by Alive Church in Norwich, UK and free for all.</p>
    </div>
</article>

<!-- Floating Audio Player -->
<div id="audio-player" class="audio-player">
    <div class="audio-player-inner">
        <div class="audio-info">
            <span class="audio-title"><?= htmlspecialchars($book['name']); ?> <?= $chapter; ?></span>
            <span class="audio-progress-text">Ready to play</span>
        </div>
        <div class="audio-controls">
            <button class="audio-btn audio-speed-btn" title="Playback speed">1x</button>
            <button class="audio-btn audio-back-btn" title="Back 15 seconds">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                    <path d="M3 3v5h5"/>
                    <text x="12" y="15" text-anchor="middle" font-size="7" fill="currentColor" stroke="none">15</text>
                </svg>
            </button>
            <button class="audio-btn audio-play-btn" title="Play/Pause">
                <svg class="play-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
                <svg class="pause-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                    <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                </svg>
            </button>
            <button class="audio-btn audio-forward-btn" title="Forward 15 seconds">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                    <path d="M21 3v5h-5"/>
                    <text x="12" y="15" text-anchor="middle" font-size="7" fill="currentColor" stroke="none">15</text>
                </svg>
            </button>
            <button class="audio-btn audio-close-btn" title="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>
    <div class="audio-progress-bar">
        <div class="audio-progress-fill"></div>
    </div>
</div>

<script>
const studyId = <?= $study['id']; ?>;
const isLoggedIn = <?= $currentUser ? 'true' : 'false'; ?>;
const existingHighlights = <?= json_encode($userHighlights); ?>;

// Smooth scroll for verse navigation
document.querySelectorAll('.verse-nav-item').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('highlighted');
            setTimeout(() => target.classList.remove('highlighted'), 2000);
        }
    });
});

// ==================== SAVE/BOOKMARK FUNCTIONALITY (Sidebar) ====================
const saveBtn = document.querySelector('.save-study-btn');

if (saveBtn && isLoggedIn) {
    saveBtn.addEventListener('click', async () => {
        const isSaved = saveBtn.classList.contains('saved');
        const action = isSaved ? 'unsave_study' : 'save_study';

        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('study_id', studyId);

            const response = await fetch('/api/user-studies.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Update sidebar button
                saveBtn.classList.toggle('saved', result.saved);
                const icon = saveBtn.querySelector('svg');
                if (icon) icon.setAttribute('fill', result.saved ? 'currentColor' : 'none');
                const text = saveBtn.querySelector('.save-text');
                if (text) text.textContent = result.saved ? 'Saved' : 'Save';

                // Update mobile button too (it exists by now via deferred script)
                const mobileBtn = document.querySelector('.mobile-save-btn');
                if (mobileBtn) {
                    mobileBtn.classList.toggle('saved', result.saved);
                    const mobileIcon = mobileBtn.querySelector('svg');
                    if (mobileIcon) mobileIcon.setAttribute('fill', result.saved ? 'currentColor' : 'none');
                }

                showToast(result.saved ? 'Saved to My Studies' : 'Removed from My Studies');
            }
        } catch (error) {
            console.error('Error saving:', error);
        }
    });
}

// ==================== TEXT HIGHLIGHTING ====================
const studyBody = document.querySelector('.study-body');

// Track highlighted texts to prevent duplicates
const highlightedTexts = new Set(existingHighlights.map(h => h.highlighted_text.toLowerCase()));

// Apply existing highlights on load
function applyExistingHighlights() {
    if (!existingHighlights.length) return;

    existingHighlights.forEach(highlight => {
        const searchText = highlight.highlighted_text;
        const className = `user-highlight highlight-${highlight.color}`;
        const dataAttrs = {
            highlightId: highlight.id,
            highlightText: highlight.highlighted_text,
            highlightColor: highlight.color
        };

        // Get all text nodes (excluding whitespace-only nodes)
        const textNodes = [];
        const walker = document.createTreeWalker(studyBody, NodeFilter.SHOW_TEXT, null, false);
        let node;
        while (node = walker.nextNode()) {
            // Skip whitespace-only nodes
            if (node.textContent.trim() === '') continue;
            textNodes.push(node);
        }

        // Build combined text and track positions
        // Normalize whitespace to single spaces to match how selection.toString() works
        let combinedText = '';
        const nodePositions = [];
        textNodes.forEach((node, idx) => {
            // Add space between nodes if needed (simulates how selection joins text)
            if (idx > 0 && combinedText.length > 0 && !combinedText.endsWith(' ') && !node.textContent.startsWith(' ')) {
                combinedText += ' ';
            }
            nodePositions.push({
                node: node,
                start: combinedText.length,
                end: combinedText.length + node.textContent.length
            });
            combinedText += node.textContent;
        });

        // Find the highlight text in combined text
        let matchIndex = combinedText.indexOf(searchText);

        // If not found, try with normalized whitespace
        if (matchIndex === -1) {
            const normalizedCombined = combinedText.replace(/\s+/g, ' ');
            const normalizedSearch = searchText.replace(/\s+/g, ' ');
            matchIndex = normalizedCombined.indexOf(normalizedSearch);
        }

        if (matchIndex === -1) {
            console.log('Highlight not found:', searchText.substring(0, 50) + '...');
            return;
        }

        const matchEnd = matchIndex + searchText.length;

        // Find which text nodes contain the match
        const nodesToWrap = [];
        nodePositions.forEach(pos => {
            if (pos.end > matchIndex && pos.start < matchEnd) {
                const startInNode = Math.max(0, matchIndex - pos.start);
                const endInNode = Math.min(pos.node.textContent.length, matchEnd - pos.start);
                nodesToWrap.push({
                    node: pos.node,
                    start: startInNode,
                    end: endInNode
                });
            }
        });

        // Wrap each text node segment (in reverse to preserve offsets)
        nodesToWrap.reverse().forEach(item => {
            try {
                wrapTextNode(item.node, item.start, item.end, className, {...dataAttrs});
            } catch (e) {
                console.error('Error wrapping highlight:', e);
            }
        });
    });
}

// Handle text selection for highlighting
if (isLoggedIn && studyBody) {
    studyBody.addEventListener('mouseup', handleTextSelection);
    studyBody.addEventListener('touchend', handleTextSelection);
}

// Available highlight colors
const highlightColors = [
    { name: 'yellow', color: '#fef08a' },
    { name: 'green', color: '#bbf7d0' },
    { name: 'blue', color: '#bfdbfe' },
    { name: 'pink', color: '#fbcfe8' },
    { name: 'orange', color: '#fed7aa' }
];

let pendingHighlight = null;
let colorPickerMenu = null;

let highlightButton = null;
const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

function handleTextSelection(e) {
    // Ignore if clicking on a highlight, color picker, or highlight button
    if (e.target.closest('.user-highlight') || e.target.closest('.highlight-color-picker') || e.target.closest('.highlight-btn')) return;

    const isTouch = e.type === 'touchend';

    // Small delay to let selection stabilize
    setTimeout(() => {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return;

        const selectedText = selection.toString().trim();

        if (selectedText.length < 3 || selectedText.length > 5000) {
            hideColorPicker();
            hideHighlightButton();
            return;
        }

        // Check if selection is within study body
        if (!selection.anchorNode || !studyBody.contains(selection.anchorNode)) return;

        // Don't highlight if already highlighted
        const range = selection.getRangeAt(0);
        const container = range.commonAncestorContainer;
        if (container.closest && container.closest('.user-highlight')) return;
        if (container.parentElement && container.parentElement.closest('.user-highlight')) return;

        // Check for duplicate highlight
        if (highlightedTexts.has(selectedText.toLowerCase())) {
            showToast('Already highlighted');
            window.getSelection().removeAllRanges();
            return;
        }

        // Get position for picker/button
        const rect = range.getBoundingClientRect();
        let posX = rect.left + rect.width / 2;
        let posY = rect.top;

        // On touch devices, show a "Highlight" button instead of auto-showing color picker
        if (isTouchDevice) {
            showHighlightButton(posX, posY);
        } else {
            // Desktop: show color picker immediately
            pendingHighlight = { text: selectedText, range: range.cloneRange() };
            showColorPicker(posX, posY);
        }
    }, 10);
}

function showHighlightButton(x, y) {
    hideHighlightButton();
    hideColorPicker(false);

    const btn = document.createElement('button');
    btn.className = 'highlight-btn';
    btn.textContent = 'Highlight';
    btn.type = 'button';

    document.body.appendChild(btn);
    highlightButton = btn;

    // Position the button
    const btnRect = btn.getBoundingClientRect();
    const viewportWidth = window.innerWidth;

    let left = x - btnRect.width / 2;
    let top = y - btnRect.height - 10;

    // Keep within viewport
    if (left < 8) left = 8;
    if (left + btnRect.width > viewportWidth - 8) left = viewportWidth - btnRect.width - 8;
    if (top < 8) top = y + 30; // Show below if no room above

    btn.style.left = left + 'px';
    btn.style.top = top + 'px';

    requestAnimationFrame(() => btn.classList.add('show'));

    // Handle button tap
    const handleTap = (e) => {
        e.preventDefault();
        e.stopPropagation();

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            hideHighlightButton();
            return;
        }

        const selectedText = selection.toString().trim();
        if (selectedText.length < 3) {
            hideHighlightButton();
            return;
        }

        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();

        // Store the pending highlight
        pendingHighlight = { text: selectedText, range: range.cloneRange() };

        // Clear selection to dismiss iOS UI
        window.getSelection().removeAllRanges();

        // Hide button and show color picker
        hideHighlightButton();
        showColorPicker(rect.left + rect.width / 2, rect.top);
    };

    btn.addEventListener('click', handleTap);
    btn.addEventListener('touchend', handleTap);
}

function hideHighlightButton() {
    if (highlightButton) {
        highlightButton.remove();
        highlightButton = null;
    }
}

let colorPickerJustOpened = false;

function showColorPicker(x, y) {
    hideColorPicker(false);

    const picker = document.createElement('div');
    picker.className = 'highlight-color-picker';
    picker.innerHTML = highlightColors.map(c => `
        <button class="color-option" data-color="${c.name}" style="background: ${c.color};" title="${c.name}"></button>
    `).join('');

    document.body.appendChild(picker);
    colorPickerMenu = picker;

    // Prevent immediate close from the click event that follows mouseup
    colorPickerJustOpened = true;
    setTimeout(() => { colorPickerJustOpened = false; }, 100);

    // Position the picker - use viewport-relative coordinates
    const pickerRect = picker.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;

    // Clamp input coordinates to viewport first
    const clampedX = Math.max(0, Math.min(x, viewportWidth));
    const clampedY = Math.max(0, Math.min(y, viewportHeight));

    let left = clampedX - pickerRect.width / 2;
    let top = clampedY - pickerRect.height - 10;

    // Keep within viewport horizontally
    if (left < 8) left = 8;
    if (left + pickerRect.width > viewportWidth - 8) left = viewportWidth - pickerRect.width - 8;

    // Keep within viewport vertically
    if (top < 8) top = clampedY + 25; // Show below if no room above
    if (top + pickerRect.height > viewportHeight - 8) top = viewportHeight - pickerRect.height - 8;

    picker.style.left = left + 'px';
    picker.style.top = top + 'px';

    // Animate in
    requestAnimationFrame(() => picker.classList.add('show'));

    // Handle color selection - use both click and touchend for iOS compatibility
    picker.querySelectorAll('.color-option').forEach(btn => {
        const handleColorSelect = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const color = btn.dataset.color;
            if (pendingHighlight) {
                saveHighlight(pendingHighlight.text, pendingHighlight.range, color);
                pendingHighlight = null;
            }
            hideColorPicker(false);
        };
        btn.addEventListener('click', handleColorSelect);
        btn.addEventListener('touchend', handleColorSelect);
    });
}

function hideColorPicker(clearPending = true) {
    if (colorPickerMenu) {
        colorPickerMenu.remove();
        colorPickerMenu = null;
    }
    if (clearPending && pendingHighlight) {
        window.getSelection().removeAllRanges();
        pendingHighlight = null;
    }
}

// Close color picker and highlight button when clicking/tapping outside
function handleOutsideClick(e) {
    if (colorPickerMenu && !colorPickerJustOpened && !e.target.closest('.highlight-color-picker')) {
        hideColorPicker();
    }
    if (highlightButton && !e.target.closest('.highlight-btn')) {
        // Check if there's still a selection - if not, hide the button
        const selection = window.getSelection();
        if (!selection || selection.toString().trim().length < 3) {
            hideHighlightButton();
        }
    }
}
document.addEventListener('click', handleOutsideClick);
document.addEventListener('touchstart', handleOutsideClick);

// Also hide highlight button when selection changes to nothing
document.addEventListener('selectionchange', () => {
    if (highlightButton) {
        const selection = window.getSelection();
        if (!selection || selection.toString().trim().length < 3) {
            hideHighlightButton();
        }
    }
});

// Get all text nodes within a range (excluding whitespace-only nodes)
function getTextNodesInRange(range) {
    const textNodes = [];
    const walker = document.createTreeWalker(
        range.commonAncestorContainer.nodeType === Node.TEXT_NODE
            ? range.commonAncestorContainer.parentNode
            : range.commonAncestorContainer,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );

    let node;
    while (node = walker.nextNode()) {
        // Skip whitespace-only text nodes (newlines between elements, etc.)
        if (node.textContent.trim() === '') continue;
        if (range.intersectsNode(node)) {
            textNodes.push(node);
        }
    }
    return textNodes;
}

// Wrap a portion of a text node in a mark element
function wrapTextNode(textNode, startOffset, endOffset, className, dataAttrs) {
    const text = textNode.textContent;
    const actualStart = Math.max(0, startOffset);
    const actualEnd = Math.min(text.length, endOffset);

    if (actualStart >= actualEnd) return null;

    // Skip if the portion to wrap is only whitespace
    const portionToWrap = text.slice(actualStart, actualEnd);
    if (portionToWrap.trim() === '') return null;

    const parent = textNode.parentNode;
    if (!parent) return null;

    // Create the mark element
    const mark = document.createElement('mark');
    mark.className = className;
    Object.keys(dataAttrs).forEach(key => {
        mark.dataset[key] = dataAttrs[key];
    });

    // Split and wrap
    if (actualEnd < text.length) {
        textNode.splitText(actualEnd);
    }
    const targetNode = actualStart > 0 ? textNode.splitText(actualStart) : textNode;

    parent.insertBefore(mark, targetNode);
    mark.appendChild(targetNode);

    return mark;
}

async function saveHighlight(text, range, color = 'yellow') {
    const marks = [];
    const className = `user-highlight highlight-${color}`;
    const dataAttrs = { highlightText: text, highlightColor: color };

    try {
        // Simple case: selection within single text node
        if (range.startContainer === range.endContainer &&
            range.startContainer.nodeType === Node.TEXT_NODE) {
            const mark = wrapTextNode(
                range.startContainer,
                range.startOffset,
                range.endOffset,
                className,
                dataAttrs
            );
            if (mark) marks.push(mark);
        } else {
            // Complex case: selection spans multiple nodes
            const textNodes = getTextNodesInRange(range);

            textNodes.forEach((node, index) => {
                let startOffset = 0;
                let endOffset = node.textContent.length;

                // First node: start from selection start
                if (node === range.startContainer) {
                    startOffset = range.startOffset;
                }
                // Last node: end at selection end
                if (node === range.endContainer) {
                    endOffset = range.endOffset;
                }

                const mark = wrapTextNode(node, startOffset, endOffset, className, {...dataAttrs});
                if (mark) marks.push(mark);
            });
        }
    } catch (e) {
        console.error('Highlight error:', e);
        showToast('Could not highlight this selection');
        window.getSelection().removeAllRanges();
        return;
    }

    if (marks.length === 0) {
        showToast('Could not highlight this selection');
        window.getSelection().removeAllRanges();
        return;
    }

    // Clear selection
    window.getSelection().removeAllRanges();

    // Add to tracked set
    highlightedTexts.add(text.toLowerCase());

    // Show confirmation immediately
    showToast('Highlighted!');

    // Save to server
    try {
        const formData = new FormData();
        formData.append('action', 'add_highlight');
        formData.append('study_id', studyId);
        formData.append('text', text);
        formData.append('start_offset', 0);
        formData.append('end_offset', text.length);
        formData.append('color', color);

        const response = await fetch('/api/user-studies.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            marks.forEach(mark => {
                mark.dataset.highlightId = result.highlight_id;
            });
        } else {
            // Remove highlights on failure
            marks.forEach(mark => {
                const parent = mark.parentNode;
                while (mark.firstChild) {
                    parent.insertBefore(mark.firstChild, mark);
                }
                mark.remove();
            });
            highlightedTexts.delete(text.toLowerCase());
            showToast('Failed to save highlight');
        }
    } catch (error) {
        console.error('Error saving highlight:', error);
        marks.forEach(mark => {
            const parent = mark.parentNode;
            while (mark.firstChild) {
                parent.insertBefore(mark.firstChild, mark);
            }
            mark.remove();
        });
        highlightedTexts.delete(text.toLowerCase());
        showToast('Failed to save highlight');
    }
}

// ==================== HIGHLIGHT DELETE FUNCTIONALITY ====================
let activeHighlightMenu = null;

function showHighlightMenu(highlight, x, y) {
    // Remove any existing menu
    hideHighlightMenu();

    const menu = document.createElement('div');
    menu.className = 'highlight-menu';
    menu.innerHTML = `
        <button class="highlight-menu-btn delete" data-action="delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Remove
        </button>
    `;

    document.body.appendChild(menu);
    activeHighlightMenu = menu;

    // Position the menu
    const menuRect = menu.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let left = x - menuRect.width / 2;
    let top = y - menuRect.height - 8;

    // Keep within viewport
    if (left < 8) left = 8;
    if (left + menuRect.width > viewportWidth - 8) left = viewportWidth - menuRect.width - 8;
    if (top < 8) top = y + 8; // Show below if no room above

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';

    // Animate in
    requestAnimationFrame(() => menu.classList.add('show'));

    // Handle delete click
    menu.querySelector('[data-action="delete"]').addEventListener('click', () => {
        deleteHighlight(highlight);
    });
}

function hideHighlightMenu() {
    if (activeHighlightMenu) {
        activeHighlightMenu.remove();
        activeHighlightMenu = null;
    }
}

async function deleteHighlight(highlight) {
    const highlightId = highlight.dataset.highlightId;
    const highlightText = highlight.dataset.highlightText || highlight.textContent;

    // Find ALL marks with the same highlight ID (for multi-node highlights)
    const allMarks = highlightId
        ? document.querySelectorAll(`mark[data-highlight-id="${highlightId}"]`)
        : [highlight];

    // Remove all marks visually
    allMarks.forEach(mark => {
        const parent = mark.parentNode;
        while (mark.firstChild) {
            parent.insertBefore(mark.firstChild, mark);
        }
        mark.remove();
    });

    hideHighlightMenu();

    // Remove from tracked set
    highlightedTexts.delete(highlightText.toLowerCase());

    showToast('Highlight removed');

    // Delete from server
    if (highlightId) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete_highlight');
            formData.append('highlight_id', highlightId);

            await fetch('/api/user-studies.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error deleting highlight:', error);
        }
    }
}

// Click/tap on highlight to show delete menu
if (isLoggedIn && studyBody) {
    studyBody.addEventListener('click', (e) => {
        const highlight = e.target.closest('.user-highlight');
        if (highlight) {
            e.preventDefault();
            e.stopPropagation();

            const rect = highlight.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top;

            showHighlightMenu(highlight, x, y);
        }
    });
}

// Close menu when clicking outside
document.addEventListener('click', (e) => {
    if (activeHighlightMenu && !e.target.closest('.highlight-menu') && !e.target.closest('.user-highlight')) {
        hideHighlightMenu();
    }
});

// Close menu on scroll
document.addEventListener('scroll', hideHighlightMenu, { passive: true });

// Toast notification
function showToast(message) {
    // Remove existing toast
    const existing = document.querySelector('.highlight-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'highlight-toast';
    toast.innerHTML = `<span class="toast-icon">✓</span> ${message}`;
    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Remove after delay
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// Initialize highlights on load
document.addEventListener('DOMContentLoaded', applyExistingHighlights);

// ==================== READING PROGRESS & STREAK TRACKING ====================
const storageKey = 'bible-study-progress';
const startTime = Date.now();
let maxScrollProgress = 0;
let streakUpdated = false;
let lastSaveTime = 0;

// Calculate scroll progress percentage
function getScrollProgress() {
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    if (docHeight <= 0) return 100;
    return Math.round((window.scrollY / docHeight) * 100);
}

// Send reading activity to server (updates streak)
async function recordReading(completed = false) {
    if (!isLoggedIn) return;

    const timeSpent = Math.round((Date.now() - startTime) / 1000);
    const scrollProgress = Math.max(maxScrollProgress, getScrollProgress());

    // Don't send too frequently (minimum 10 seconds between saves)
    if (Date.now() - lastSaveTime < 10000 && !completed) return;
    lastSaveTime = Date.now();

    try {
        const formData = new FormData();
        formData.append('action', 'record_reading');
        formData.append('study_id', studyId);
        formData.append('time_spent', timeSpent);
        formData.append('scroll_progress', scrollProgress);
        formData.append('completed', completed ? '1' : '0');

        await fetch('/api/user-studies.php', {
            method: 'POST',
            body: formData
        });

        if ((completed || scrollProgress >= 50) && !streakUpdated) {
            streakUpdated = true;
            console.log('Streak updated!');
        }
    } catch (error) {
        console.error('Error recording reading:', error);
    }
}

// Track scroll progress
let saveTimeout;
window.addEventListener('scroll', () => {
    const currentProgress = getScrollProgress();
    maxScrollProgress = Math.max(maxScrollProgress, currentProgress);

    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        // Save locally for scroll restoration
        const progress = JSON.parse(localStorage.getItem(storageKey) || '{}');
        progress[studyId] = {
            scroll: window.scrollY,
            lastRead: new Date().toISOString()
        };
        localStorage.setItem(storageKey, JSON.stringify(progress));

        // Send to server if logged in and scrolled past 50%
        if (isLoggedIn && maxScrollProgress >= 50 && !streakUpdated) {
            recordReading(false);
        }
    }, 500);
}, { passive: true });

// Record reading when leaving page
window.addEventListener('beforeunload', () => {
    if (isLoggedIn) {
        // Use sendBeacon for reliable delivery on page exit
        const timeSpent = Math.round((Date.now() - startTime) / 1000);
        const scrollProgress = Math.max(maxScrollProgress, getScrollProgress());

        // Only record if spent at least 30 seconds or scrolled past 30%
        if (timeSpent >= 30 || scrollProgress >= 30) {
            const data = new FormData();
            data.append('action', 'record_reading');
            data.append('study_id', studyId);
            data.append('time_spent', timeSpent);
            data.append('scroll_progress', scrollProgress);
            data.append('completed', scrollProgress >= 90 ? '1' : '0');

            navigator.sendBeacon('/api/user-studies.php', data);
        }
    }
});

// Record as completed if user reaches bottom
window.addEventListener('scroll', () => {
    if (isLoggedIn && !streakUpdated && getScrollProgress() >= 90) {
        recordReading(true);
    }
}, { passive: true });

// Restore scroll position on load
document.addEventListener('DOMContentLoaded', () => {
    const progress = JSON.parse(localStorage.getItem(storageKey) || '{}');
    if (progress[studyId] && progress[studyId].scroll) {
        const lastRead = new Date(progress[studyId].lastRead);
        const daysSince = (new Date() - lastRead) / (1000 * 60 * 60 * 24);
        if (daysSince < 7) {
            window.scrollTo(0, progress[studyId].scroll);
        }
    }
});
</script>

<!-- Mobile Floating Action Bar -->
<div class="mobile-study-bar">
    <button class="mobile-bar-btn mobile-chapters-btn" aria-label="Chapters" data-panel="chapters">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
        <span>Ch. <?= $chapter; ?></span>
    </button>

    <?php if ($prevChapter): ?>
        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $prevChapter; ?>" class="mobile-bar-btn" aria-label="Previous chapter">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </a>
    <?php else: ?>
        <span class="mobile-bar-btn disabled"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></span>
    <?php endif; ?>

    <?php if ($currentUser): ?>
        <button class="mobile-bar-btn mobile-save-btn <?= $isSaved ? 'saved' : ''; ?>" data-study-id="<?= $study['id']; ?>" aria-label="<?= $isSaved ? 'Saved' : 'Save'; ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="<?= $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
    <?php else: ?>
        <button class="mobile-bar-btn login-prompt-trigger" data-feature="save studies to your library" aria-label="Log in to save">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
    <?php endif; ?>

    <?php if ($nextChapter): ?>
        <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $nextChapter; ?>" class="mobile-bar-btn" aria-label="Next chapter">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
    <?php else: ?>
        <span class="mobile-bar-btn disabled"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
    <?php endif; ?>

    <button class="mobile-bar-btn mobile-browse-books-btn" aria-label="Browse all books">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
        </svg>
    </button>

    <button class="mobile-bar-btn mobile-more-btn" aria-label="More options" data-panel="more">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>
        </svg>
    </button>
</div>

<!-- Mobile Slide-up Panels -->
<div class="mobile-panel-overlay"></div>

<div class="mobile-panel" id="chapters-panel">
    <div class="mobile-panel-header">
        <h3><?= htmlspecialchars($book['name']); ?></h3>
        <button class="mobile-panel-close" aria-label="Close">&times;</button>
    </div>
    <div class="mobile-panel-content">
        <div class="mobile-chapter-grid">
            <?php for ($i = 1; $i <= $book['chapters']; $i++): ?>
                <?php $hasStudy = isset($chaptersWithStudies[$i]); ?>
                <?php if ($hasStudy): ?>
                    <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>/<?= $i; ?>"
                       class="mobile-chapter-item <?= $i == $chapter ? 'active' : ''; ?>">
                        <?= $i; ?>
                    </a>
                <?php else: ?>
                    <span class="mobile-chapter-item unavailable"><?= $i; ?></span>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="mobile-panel" id="more-panel">
    <div class="mobile-panel-header">
        <h3>Options</h3>
        <button class="mobile-panel-close" aria-label="Close">&times;</button>
    </div>
    <div class="mobile-panel-content">
        <?php if (!empty($verseMarkers)): ?>
        <div class="mobile-panel-section">
            <h4>Jump to Verse</h4>
            <div class="mobile-verse-grid">
                <?php foreach ($verseMarkers as $marker): ?>
                    <a href="#v<?= $marker['start']; ?>" class="mobile-verse-item">
                        <?= $marker['start']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="mobile-panel-section">
            <button id="mobile-listen-btn" class="mobile-action-btn mobile-listen-btn">
                <svg class="play-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
                <svg class="pause-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                    <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                </svg>
                <span class="listen-text">Listen to Study</span>
            </button>
            <button onclick="window.print()" class="mobile-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print Study
            </button>
            <a href="/bible-study/<?= htmlspecialchars($book['slug']); ?>" class="mobile-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                All <?= htmlspecialchars($book['name']); ?> Chapters
            </a>
        </div>
    </div>
</div>

<script>
// ==================== MOBILE BAR & PANELS ====================
(function() {
    const overlay = document.querySelector('.mobile-panel-overlay');
    const panels = document.querySelectorAll('.mobile-panel');
    const mobileSaveBtn = document.querySelector('.mobile-save-btn');

    function openPanel(panelId) {
        const panel = document.getElementById(panelId + '-panel');
        if (panel) {
            overlay.classList.add('active');
            panel.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeAllPanels() {
        overlay.classList.remove('active');
        panels.forEach(p => p.classList.remove('active'));
        document.body.style.overflow = '';
    }

    // Panel triggers
    document.querySelectorAll('[data-panel]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openPanel(btn.dataset.panel);
        });
    });

    // Close buttons
    document.querySelectorAll('.mobile-panel-close').forEach(btn => {
        btn.addEventListener('click', closeAllPanels);
    });

    // Close on overlay click
    if (overlay) {
        overlay.addEventListener('click', closeAllPanels);
    }

    // Close panels when clicking verse links
    document.querySelectorAll('.mobile-verse-item').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            closeAllPanels();
            const target = document.querySelector(link.getAttribute('href'));
            if (target) {
                setTimeout(() => {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.classList.add('highlighted');
                    setTimeout(() => target.classList.remove('highlighted'), 2000);
                }, 300);
            }
        });
    });

    // Mobile save button
    if (mobileSaveBtn) {
        mobileSaveBtn.addEventListener('click', async () => {
            const studyId = mobileSaveBtn.dataset.studyId;
            const isSaved = mobileSaveBtn.classList.contains('saved');
            const action = isSaved ? 'unsave_study' : 'save_study';

            try {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('study_id', studyId);

                const response = await fetch('/api/user-studies.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Update mobile button
                    mobileSaveBtn.classList.toggle('saved', result.saved);
                    const mobileIcon = mobileSaveBtn.querySelector('svg');
                    if (mobileIcon) mobileIcon.setAttribute('fill', result.saved ? 'currentColor' : 'none');

                    // Update sidebar button too
                    const sidebarBtn = document.querySelector('.save-study-btn');
                    if (sidebarBtn) {
                        sidebarBtn.classList.toggle('saved', result.saved);
                        const sidebarIcon = sidebarBtn.querySelector('svg');
                        if (sidebarIcon) sidebarIcon.setAttribute('fill', result.saved ? 'currentColor' : 'none');
                        const sidebarText = sidebarBtn.querySelector('.save-text');
                        if (sidebarText) sidebarText.textContent = result.saved ? 'Saved' : 'Save';
                    }

                    // Show toast
                    if (typeof showToast === 'function') {
                        showToast(result.saved ? 'Saved to My Studies' : 'Removed from My Studies');
                    }
                }
            } catch (error) {
                console.error('Error saving:', error);
            }
        });
    }
})();

// ==================== AUDIO PLAYER (Text-to-Speech) ====================
(function() {
    // Check for speech synthesis support
    if (!('speechSynthesis' in window)) {
        // Hide listen buttons if not supported
        document.querySelectorAll('.listen-btn, .mobile-listen-btn').forEach(btn => {
            btn.style.display = 'none';
        });
        return;
    }

    const synth = window.speechSynthesis;
    const audioPlayer = document.getElementById('audio-player');
    const listenBtn = document.getElementById('listen-btn');
    const mobileListenBtn = document.getElementById('mobile-listen-btn');
    const playBtn = audioPlayer.querySelector('.audio-play-btn');
    const speedBtn = audioPlayer.querySelector('.audio-speed-btn');
    const backBtn = audioPlayer.querySelector('.audio-back-btn');
    const forwardBtn = audioPlayer.querySelector('.audio-forward-btn');
    const closeBtn = audioPlayer.querySelector('.audio-close-btn');
    const progressText = audioPlayer.querySelector('.audio-progress-text');
    const progressFill = audioPlayer.querySelector('.audio-progress-fill');

    let utterances = [];
    let currentUtteranceIndex = 0;
    let isPlaying = false;
    let isPaused = false;
    let playbackRate = 1;
    const speeds = [0.75, 1, 1.25, 1.5, 1.75, 2];
    let currentSpeedIndex = 1;
    let selectedVoice = null;

    // Find the best available voice
    function selectBestVoice() {
        const voices = synth.getVoices();
        if (!voices.length) return null;

        // Priority list of voice name patterns (most natural/modern first)
        const priorities = [
            // Premium neural/natural voices
            v => v.name.includes('Neural') && v.lang.startsWith('en'),
            v => v.name.includes('Natural') && v.lang.startsWith('en'),
            // Google voices (generally good quality)
            v => v.name.includes('Google UK English Female'),
            v => v.name.includes('Google UK English Male'),
            v => v.name.includes('Google US English'),
            // Microsoft voices
            v => v.name.includes('Microsoft') && v.name.includes('Online') && v.lang.startsWith('en'),
            // Apple voices
            v => v.name === 'Samantha',
            v => v.name === 'Daniel' && v.lang.startsWith('en-GB'),
            v => v.name === 'Karen',
            v => v.name === 'Moira',
            // Fallback to any English voice
            v => v.lang.startsWith('en-GB'),
            v => v.lang.startsWith('en-US'),
            v => v.lang.startsWith('en')
        ];

        for (const check of priorities) {
            const voice = voices.find(check);
            if (voice) {
                console.log('Selected voice:', voice.name);
                return voice;
            }
        }

        return voices[0];
    }

    // Extract text from study with proper structure for pauses
    function getStudySegments() {
        const studyBody = document.querySelector('.study-body');
        if (!studyBody) return [];

        const segments = [];

        // Walk through child elements to preserve structure
        const processNode = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent.trim();
                if (text) return text;
                return null;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) return null;

            // Skip certain elements
            if (node.classList.contains('verse-marker')) return null;
            if (node.classList.contains('study-actions')) return null;
            if (node.tagName === 'SCRIPT' || node.tagName === 'STYLE') return null;

            const tagName = node.tagName.toLowerCase();

            // Headings get their own segment with a pause marker
            if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(tagName)) {
                let text = node.textContent.trim();
                // Remove verse references like [1], [1-5]
                text = text.replace(/\[\d+(?:-\d+)?\]/g, '').trim();
                if (text) {
                    return { type: 'heading', text: text };
                }
                return null;
            }

            // Paragraphs and list items get their own segments
            if (['p', 'li', 'blockquote'].includes(tagName)) {
                let text = node.textContent.trim();
                // Remove verse references like [1], [1-5], [12-14]
                text = text.replace(/\[\d+(?:-\d+)?\]/g, '').trim();
                // Clean up any double spaces left behind
                text = text.replace(/\s+/g, ' ');
                if (text) {
                    return { type: 'paragraph', text: text };
                }
                return null;
            }

            // For other elements, process children
            const childTexts = [];
            node.childNodes.forEach(child => {
                const result = processNode(child);
                if (result) childTexts.push(result);
            });

            return childTexts.length ? childTexts : null;
        };

        // Flatten and process
        const flatten = (items) => {
            const result = [];
            const process = (item) => {
                if (!item) return;
                if (Array.isArray(item)) {
                    item.forEach(process);
                } else if (typeof item === 'string') {
                    result.push({ type: 'text', text: item });
                } else {
                    result.push(item);
                }
            };
            process(items);
            return result;
        };

        const raw = [];
        studyBody.childNodes.forEach(child => {
            const result = processNode(child);
            if (result) raw.push(result);
        });

        return flatten(raw);
    }

    // Create utterances with proper pauses
    function createUtterances() {
        const segments = getStudySegments();
        utterances = [];

        if (!selectedVoice) {
            selectedVoice = selectBestVoice();
        }

        segments.forEach((segment, index) => {
            // Create utterance for the text
            const utterance = new SpeechSynthesisUtterance(segment.text);
            utterance.rate = playbackRate;
            utterance.pitch = 1;

            if (selectedVoice) {
                utterance.voice = selectedVoice;
            }

            // Add pause after headings
            if (segment.type === 'heading') {
                utterance.rate = playbackRate * 0.9; // Slightly slower for headings
            }

            utterance.onstart = () => {
                currentUtteranceIndex = utterances.length;
                updateProgress();
            };

            utterance.onerror = (e) => {
                if (e.error !== 'interrupted') {
                    console.error('Speech error:', e);
                }
            };

            // Add a natural pause after headings by appending to the text
            if (segment.type === 'heading') {
                utterance.text = segment.text + '.';
            }

            utterances.push(utterance);
        });

        return utterances;
    }

    // Update progress display
    function updateProgress() {
        const percent = utterances.length > 0
            ? ((currentUtteranceIndex + 1) / utterances.length) * 100
            : 0;
        progressFill.style.width = percent + '%';

        if (isPlaying && !isPaused) {
            progressText.textContent = `Playing... (${currentUtteranceIndex + 1}/${utterances.length})`;
        } else if (isPaused) {
            progressText.textContent = 'Paused';
        } else {
            progressText.textContent = 'Ready to play';
        }
    }

    // Start playback
    function startPlayback() {
        // Cancel any existing speech
        synth.cancel();

        // Create new utterances
        createUtterances();

        if (utterances.length === 0) return;

        // Show player
        audioPlayer.classList.add('active');
        isPlaying = true;
        isPaused = false;
        currentUtteranceIndex = 0;

        // Update UI
        updatePlayButtons(true);
        updateProgress();

        // Start speaking
        speakFromIndex(0);
    }

    // Speak from a specific index
    function speakFromIndex(index) {
        if (index >= utterances.length) {
            stopPlayback();
            return;
        }

        currentUtteranceIndex = index;

        // Speak each utterance sequentially
        for (let i = index; i < utterances.length; i++) {
            const utterance = utterances[i];
            utterance.rate = playbackRate;

            // Track when each one starts
            utterance.onstart = () => {
                currentUtteranceIndex = i;
                updateProgress();
            };

            // Handle end of all utterances
            if (i === utterances.length - 1) {
                utterance.onend = () => stopPlayback();
            }

            synth.speak(utterance);
        }
    }

    // Toggle play/pause
    function togglePlayback() {
        if (!isPlaying) {
            startPlayback();
        } else if (isPaused) {
            synth.resume();
            isPaused = false;
            updatePlayButtons(true);
            progressText.textContent = `Playing... (${currentUtteranceIndex + 1}/${utterances.length})`;
        } else {
            synth.pause();
            isPaused = true;
            updatePlayButtons(false);
            progressText.textContent = 'Paused';
        }
    }

    // Stop playback
    function stopPlayback() {
        synth.cancel();
        isPlaying = false;
        isPaused = false;
        currentUtteranceIndex = 0;
        audioPlayer.classList.remove('active');
        updatePlayButtons(false);
        progressFill.style.width = '0%';
    }

    // Skip forward/back
    function skip(direction) {
        if (!isPlaying || utterances.length === 0) return;

        synth.cancel();

        // Skip ~3 chunks (roughly 15 seconds)
        const skipAmount = 3;
        currentUtteranceIndex = Math.max(0, Math.min(
            utterances.length - 1,
            currentUtteranceIndex + (direction * skipAmount)
        ));

        speakFromIndex(currentUtteranceIndex);
        updateProgress();
    }

    // Change speed
    function cycleSpeed() {
        currentSpeedIndex = (currentSpeedIndex + 1) % speeds.length;
        playbackRate = speeds[currentSpeedIndex];
        speedBtn.textContent = playbackRate + 'x';

        // If currently playing, restart from current position with new speed
        if (isPlaying && !isPaused) {
            synth.cancel();
            speakFromIndex(currentUtteranceIndex);
        }
    }

    // Update play button icons
    function updatePlayButtons(playing) {
        // Update listen FAB
        if (listenBtn) {
            listenBtn.classList.toggle('playing', playing);
        }

        // Update mobile and audio player buttons
        const buttons = [mobileListenBtn, playBtn];
        buttons.forEach(btn => {
            if (!btn) return;
            const playIcon = btn.querySelector('.play-icon');
            const pauseIcon = btn.querySelector('.pause-icon');
            const text = btn.querySelector('.listen-text');

            if (playIcon) playIcon.style.display = playing ? 'none' : 'block';
            if (pauseIcon) pauseIcon.style.display = playing ? 'block' : 'none';
            if (text) text.textContent = playing ? 'Pause' : 'Listen to Study';
        });
    }

    // Event listeners
    if (listenBtn) {
        listenBtn.addEventListener('click', togglePlayback);
    }

    if (mobileListenBtn) {
        mobileListenBtn.addEventListener('click', () => {
            // Close mobile panel first
            document.querySelector('.mobile-panel-overlay')?.classList.remove('active');
            document.querySelectorAll('.mobile-panel').forEach(p => p.classList.remove('active'));
            document.body.style.overflow = '';

            togglePlayback();
        });
    }

    playBtn.addEventListener('click', togglePlayback);
    speedBtn.addEventListener('click', cycleSpeed);
    backBtn.addEventListener('click', () => skip(-1));
    forwardBtn.addEventListener('click', () => skip(1));
    closeBtn.addEventListener('click', stopPlayback);

    // Load voices (they may not be available immediately)
    if (synth.onvoiceschanged !== undefined) {
        synth.onvoiceschanged = () => {
            selectedVoice = selectBestVoice();
        };
    }

    // Try to load voices immediately too
    selectedVoice = selectBestVoice();

    // Stop playback when leaving page
    window.addEventListener('beforeunload', () => {
        synth.cancel();
    });

    // Handle visibility change (pause when tab hidden on mobile)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && isPlaying && !isPaused) {
            // Some browsers stop speech when tab is hidden
            // We'll just update the UI to reflect the state
        }
    });
})();

// ==================== FONT SETTINGS ====================
(function() {
    const studyBody = document.querySelector('.study-body');
    const fontSettingsBtn = document.getElementById('font-settings-btn');
    const fontSettingsPanel = document.getElementById('font-settings-panel');
    const fontFamilySelect = document.getElementById('font-family-select');
    const fontSizeDisplay = document.querySelector('.font-size-display');
    const fontResetBtn = document.querySelector('.font-reset-btn');
    const isLoggedIn = <?= $currentUser ? 'true' : 'false'; ?>;

    if (!studyBody || !fontSettingsBtn) return;

    // Font family mappings
    const fontFamilies = {
        'default': 'inherit',
        'serif': 'Georgia, "Times New Roman", serif',
        'sans-serif': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        'georgia': 'Georgia, serif',
        'times': '"Times New Roman", Times, serif',
        'arial': 'Arial, Helvetica, sans-serif',
        'verdana': 'Verdana, Geneva, sans-serif',
        'openDyslexic': '"OpenDyslexic", sans-serif'
    };

    // Default settings
    const defaultSettings = { fontSize: 100, fontFamily: 'default' };
    let settings = { ...defaultSettings };

    // Load settings from localStorage or server
    async function loadSettings() {
        // First, load from localStorage for instant display
        const stored = localStorage.getItem('study-font-settings');
        if (stored) {
            try {
                settings = { ...defaultSettings, ...JSON.parse(stored) };
            } catch (e) {
                settings = { ...defaultSettings };
            }
        }
        applySettings();

        // If logged in, fetch from server (may override localStorage)
        if (isLoggedIn) {
            try {
                const response = await fetch('/api/user-studies.php?action=get_font_settings');
                const data = await response.json();
                if (data.fontSize || data.fontFamily) {
                    settings.fontSize = data.fontSize || defaultSettings.fontSize;
                    settings.fontFamily = data.fontFamily || defaultSettings.fontFamily;
                    // Update localStorage to match server
                    localStorage.setItem('study-font-settings', JSON.stringify(settings));
                    applySettings();
                }
            } catch (error) {
                console.error('Error loading font settings:', error);
            }
        }
    }

    // Save settings
    async function saveSettings() {
        // Always save to localStorage for immediate access
        localStorage.setItem('study-font-settings', JSON.stringify(settings));

        // If logged in, also save to server
        if (isLoggedIn) {
            try {
                const formData = new FormData();
                formData.append('action', 'save_font_settings');
                formData.append('font_size', settings.fontSize);
                formData.append('font_family', settings.fontFamily);

                await fetch('/api/user-studies.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Error saving font settings:', error);
            }
        }
    }

    // Apply settings to study body
    function applySettings() {
        studyBody.style.fontSize = settings.fontSize + '%';
        studyBody.style.fontFamily = fontFamilies[settings.fontFamily] || 'inherit';
        fontSizeDisplay.textContent = settings.fontSize + '%';
        fontFamilySelect.value = settings.fontFamily;
    }

    // Toggle panel
    fontSettingsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        fontSettingsPanel.classList.toggle('active');
    });

    // Close panel when clicking outside
    document.addEventListener('click', (e) => {
        if (!fontSettingsPanel.contains(e.target) && e.target !== fontSettingsBtn) {
            fontSettingsPanel.classList.remove('active');
        }
    });

    // Font size controls
    document.querySelectorAll('.font-size-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.action;
            if (action === 'increase' && settings.fontSize < 150) {
                settings.fontSize += 10;
            } else if (action === 'decrease' && settings.fontSize > 70) {
                settings.fontSize -= 10;
            }
            applySettings();
            saveSettings();
        });
    });

    // Font family select
    fontFamilySelect.addEventListener('change', () => {
        settings.fontFamily = fontFamilySelect.value;
        applySettings();
        saveSettings();
    });

    // Reset button
    fontResetBtn.addEventListener('click', () => {
        settings = { ...defaultSettings };
        applySettings();
        saveSettings();
    });

    // Load settings on init
    loadSettings();

    // Load OpenDyslexic font if needed
    if (!document.getElementById('opendyslexic-font')) {
        const link = document.createElement('link');
        link.id = 'opendyslexic-font';
        link.rel = 'stylesheet';
        link.href = 'https://fonts.cdnfonts.com/css/opendyslexic';
        document.head.appendChild(link);
    }
})();

// ==================== LOGIN PROMPT FOR NON-LOGGED-IN USERS ====================
<?php if (!$currentUser): ?>
document.addEventListener('DOMContentLoaded', function() {
    const studyBody = document.querySelector('.study-body');
    const loginModal = document.getElementById('login-prompt-modal');
    const modalOverlay = loginModal?.querySelector('.login-modal-overlay');
    const modalClose = loginModal?.querySelector('.login-modal-close');
    const featureText = loginModal?.querySelector('.login-feature-text');

    if (!studyBody || !loginModal) {
        console.log('Login modal elements not found');
        return;
    }

    // Show login prompt modal
    function showLoginPrompt(feature) {
        if (featureText) {
            featureText.textContent = feature || 'use this feature';
        }
        loginModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Close modal
    function closeLoginPrompt() {
        loginModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close handlers
    modalOverlay?.addEventListener('click', closeLoginPrompt);
    modalClose?.addEventListener('click', closeLoginPrompt);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLoginPrompt();
    });

    // Detect text selection for highlighting prompt
    studyBody.addEventListener('mouseup', handleSelectionPrompt);
    studyBody.addEventListener('touchend', handleSelectionPrompt);

    function handleSelectionPrompt(e) {
        const selection = window.getSelection();
        const selectedText = selection.toString().trim();

        if (selectedText.length >= 3 && selectedText.length <= 500) {
            // Check if selection is within study body
            if (selection.anchorNode && studyBody.contains(selection.anchorNode)) {
                // Clear selection first
                window.getSelection().removeAllRanges();
                // Show login prompt
                showLoginPrompt('highlight and save text');
            }
        }
    }

    // Handle clicks on login prompt trigger buttons (save buttons, etc.)
    document.querySelectorAll('.login-prompt-trigger').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const feature = btn.dataset.feature || 'use this feature';
            showLoginPrompt(feature);
        });
    });

    // Expose function globally for other uses
    window.showLoginPrompt = showLoginPrompt;
});
<?php endif; ?>
</script>

<?php if (!$currentUser): ?>
<!-- Login Prompt Modal -->
<div id="login-prompt-modal" class="login-prompt-modal">
    <div class="login-modal-overlay"></div>
    <div class="login-modal-content">
        <button class="login-modal-close" aria-label="Close">&times;</button>
        <div class="login-modal-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
        </div>
        <h3>Create a Free Account</h3>
        <p>Sign up or log in to <span class="login-feature-text">use this feature</span>, track your progress, and save your highlights.</p>
        <div class="login-modal-actions">
            <a href="/register?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">Sign Up Free</a>
            <a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-outline">Log In</a>
        </div>
        <p class="login-modal-benefits">
            <span>✓ Save studies</span>
            <span>✓ Highlight text</span>
            <span>✓ Track progress</span>
            <span>✓ Reading streaks</span>
        </p>
    </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<script src="/assets/js/bible-study-editor.js"></script>
<?php endif; ?>

<?php include __DIR__ . '/includes/bible-study-navigator.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
