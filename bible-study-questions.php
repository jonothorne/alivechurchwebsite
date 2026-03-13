<?php
/**
 * Bible Study - All Questions Page
 * Lists all questions people are asking, grouped by topic
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/BibleStudyTagger.php';

$pdo = getDbConnection();
$tagger = new BibleStudyTagger($pdo);

// Get filter parameters
$categorySlug = $_GET['category'] ?? '';
$sortBy = $_GET['sort'] ?? 'popular'; // popular, newest, alphabetical

// Build query
$whereClause = '';
$params = [];

if ($categorySlug) {
    // Get category ID
    $catStmt = $pdo->prepare("SELECT id, name FROM bible_study_topics WHERE slug = ? AND level = 0");
    $catStmt->execute([$categorySlug]);
    $selectedCategory = $catStmt->fetch();

    if ($selectedCategory) {
        $whereClause = "WHERE t.parent_id = ?";
        $params[] = $selectedCategory['id'];
    }
}

// Get all questions with topic info and study count
$orderBy = match($sortBy) {
    'newest' => 'q.created_at DESC',
    'alphabetical' => 'q.question ASC',
    default => 'study_count DESC, q.question ASC' // popular
};

$sql = "
    SELECT q.*,
           t.name as topic_name,
           t.slug as topic_slug,
           t.icon as topic_icon,
           p.name as category_name,
           p.slug as category_slug,
           COUNT(DISTINCT qt.study_id) as study_count
    FROM bible_study_questions q
    JOIN bible_study_topics t ON q.topic_id = t.id
    LEFT JOIN bible_study_topics p ON t.parent_id = p.id
    LEFT JOIN bible_study_question_tags qt ON q.id = qt.question_id
    LEFT JOIN bible_studies s ON qt.study_id = s.id AND s.status = 'published'
    $whereClause
    GROUP BY q.id
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Get all categories for filter
$categories = $pdo->query("
    SELECT t.*, COUNT(DISTINCT q.id) as question_count
    FROM bible_study_topics t
    LEFT JOIN bible_study_topics sub ON sub.parent_id = t.id
    LEFT JOIN bible_study_questions q ON q.topic_id = sub.id
    WHERE t.level = 0
    GROUP BY t.id
    HAVING question_count > 0
    ORDER BY t.display_order
")->fetchAll();

// Group questions by topic for display
$questionsByTopic = [];
foreach ($questions as $q) {
    $topicKey = $q['topic_slug'];
    if (!isset($questionsByTopic[$topicKey])) {
        $questionsByTopic[$topicKey] = [
            'name' => $q['topic_name'],
            'slug' => $q['topic_slug'],
            'icon' => $q['topic_icon'],
            'category' => $q['category_name'],
            'questions' => []
        ];
    }
    $questionsByTopic[$topicKey]['questions'][] = $q;
}

$page_title = 'Questions People Are Asking | Bible Study | ' . $site['name'];
$page_description = 'Find answers to life\'s biggest questions. Explore what the Bible says about topics like salvation, forgiveness, suffering, relationships, and more.';

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero questions-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <nav class="breadcrumb">
            <a href="/bible-study">Bible Study</a>
            <span>/</span>
            <a href="/bible-study/topics">Topics</a>
            <span>/</span>
            <span>Questions</span>
        </nav>
        <h1>Questions People Are Asking</h1>
        <p>Real questions from real people. Find what the Bible says about what you're going through.</p>

        <form action="/bible-study/search" method="GET" class="study-search-form hero-search" id="question-search-form">
            <div class="search-autocomplete-wrapper">
                <input type="text"
                       name="q"
                       id="question-search-input"
                       placeholder="Type your question (e.g., Why does God love me?)"
                       aria-label="Search questions"
                       autocomplete="off">
                <div class="autocomplete-dropdown" id="autocomplete-dropdown"></div>
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
</section>

<!-- Filters -->
<section class="questions-filters">
    <div class="container">
        <div class="filters-row">
            <div class="filter-group">
                <label>Category:</label>
                <div class="filter-pills">
                    <a href="/bible-study/questions" class="filter-pill <?= !$categorySlug ? 'active' : ''; ?>">All</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="/bible-study/questions?category=<?= urlencode($cat['slug']); ?>&sort=<?= $sortBy; ?>"
                           class="filter-pill <?= $categorySlug === $cat['slug'] ? 'active' : ''; ?>">
                            <?= $cat['icon']; ?> <?= htmlspecialchars($cat['name']); ?>
                            <span class="count">(<?= $cat['question_count']; ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="filter-group">
                <label>Sort:</label>
                <select onchange="window.location.href=this.value" class="sort-select">
                    <option value="?category=<?= urlencode($categorySlug); ?>&sort=popular" <?= $sortBy === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    <option value="?category=<?= urlencode($categorySlug); ?>&sort=newest" <?= $sortBy === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="?category=<?= urlencode($categorySlug); ?>&sort=alphabetical" <?= $sortBy === 'alphabetical' ? 'selected' : ''; ?>>A-Z</option>
                </select>
            </div>
        </div>
        <p class="results-count"><?= count($questions); ?> questions found</p>
    </div>
</section>

<!-- Questions by Topic -->
<section class="questions-list">
    <div class="container">
        <?php if (empty($questionsByTopic)): ?>
            <div class="no-results">
                <p>No questions found for this category.</p>
                <a href="/bible-study/questions" class="btn btn-primary">View All Questions</a>
            </div>
        <?php else: ?>
            <?php foreach ($questionsByTopic as $topicSlug => $topic): ?>
                <div class="topic-questions-section">
                    <h2 class="topic-heading">
                        <a href="/bible-study/topics/<?= htmlspecialchars($topicSlug); ?>">
                            <?= $topic['icon']; ?> <?= htmlspecialchars($topic['name']); ?>
                        </a>
                        <span class="question-count"><?= count($topic['questions']); ?> questions</span>
                    </h2>
                    <div class="questions-grid">
                        <?php foreach ($topic['questions'] as $question): ?>
                            <a href="/bible-study/questions/<?= htmlspecialchars($question['slug']); ?>" class="question-card">
                                <span class="question-text"><?= htmlspecialchars($question['question']); ?></span>
                                <?php if ($question['description']): ?>
                                    <span class="question-description"><?= htmlspecialchars(substr($question['description'], 0, 120)); ?><?= strlen($question['description']) > 120 ? '...' : ''; ?></span>
                                <?php endif; ?>
                                <span class="question-meta">
                                    <?php if ($question['study_count'] > 0): ?>
                                        <span class="study-count"><?= $question['study_count']; ?> <?= $question['study_count'] == 1 ? 'study' : 'studies'; ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Back Links -->
<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/bible-study/topics" class="btn btn-outline">&larr; Browse Topics</a>
        <a href="/bible-study" class="btn btn-outline">Browse by Book</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
