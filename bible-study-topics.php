<?php
/**
 * Bible Study - Topics Page
 * Browse studies by life topics and spiritual themes
 * Hierarchical: Categories -> Sub-topics -> Questions
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/BibleStudyTagger.php';

$pdo = getDbConnection();
$tagger = new BibleStudyTagger($pdo);

// Get main categories (level 0 topics)
$categories = $tagger->getMainCategories();

// Get popular questions (most linked to studies)
$popularQuestions = $tagger->getPopularQuestions(8);

$page_title = 'Bible Study Topics | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero topics-hero">
    <div class="container narrow">
        <p class="eyebrow">Bible Study Library</p>
        <h1>What Are You Going Through?</h1>
        <p>Find Bible studies that speak to where you are in life. Whether you're facing a challenge, seeking answers, or wanting to grow deeper in faith, explore Scripture through the lens of life's real questions.</p>

        <form action="/bible-study/search" method="GET" class="study-search-form hero-search">
            <input type="text" name="q" placeholder="Search questions or enter a topic (e.g., anxiety, forgiveness)..." aria-label="Search Bible studies">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
</section>

<?php if (!empty($popularQuestions)): ?>
<!-- Popular Questions -->
<section class="questions-popular">
    <div class="container">
        <h2>Questions People Are Asking</h2>
        <div class="popular-questions-grid">
            <?php foreach ($popularQuestions as $question): ?>
                <a href="/bible-study/questions/<?= htmlspecialchars($question['slug']); ?>" class="popular-question-card">
                    <span class="question-text"><?= htmlspecialchars($question['question']); ?></span>
                    <span class="question-meta">
                        <span class="topic-badge"><?= htmlspecialchars($question['topic_name']); ?></span>
                        <span class="study-count"><?= $question['study_count']; ?> <?= $question['study_count'] == 1 ? 'study' : 'studies'; ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Main Categories -->
<section class="topics-categories">
    <div class="container">
        <h2>Browse by Category</h2>
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <a href="/bible-study/topics/<?= htmlspecialchars($category['slug']); ?>" class="category-card">
                    <span class="category-icon"><?= $category['icon']; ?></span>
                    <div class="category-info">
                        <span class="category-name"><?= htmlspecialchars($category['name']); ?></span>
                        <span class="category-description"><?= htmlspecialchars($category['description']); ?></span>
                    </div>
                    <div class="category-stats">
                        <span class="subtopic-count"><?= $category['subtopic_count']; ?> topics</span>
                        <?php if ($category['study_count'] > 0): ?>
                            <span class="study-count"><?= $category['study_count']; ?> studies</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Quick Links to All Sub-topics -->
<section class="topics-all-subtopics">
    <div class="container">
        <h2>All Topics A-Z</h2>
        <div class="subtopics-alpha-grid">
            <?php
            $allSubtopics = $pdo->query("
                SELECT t.*, COUNT(DISTINCT tt.study_id) as study_count
                FROM bible_study_topics t
                LEFT JOIN bible_study_topic_tags tt ON t.id = tt.topic_id
                LEFT JOIN bible_studies s ON tt.study_id = s.id AND s.status = 'published'
                WHERE t.level = 1
                GROUP BY t.id
                ORDER BY t.name
            ")->fetchAll();

            foreach ($allSubtopics as $topic):
                $hasStudies = $topic['study_count'] > 0;
            ?>
                <a href="/bible-study/topics/<?= htmlspecialchars($topic['slug']); ?>"
                   class="subtopic-link <?= $hasStudies ? 'has-content' : 'no-content'; ?>">
                    <?= $topic['icon']; ?> <?= htmlspecialchars($topic['name']); ?>
                    <?php if ($hasStudies): ?>
                        <span class="count">(<?= $topic['study_count']; ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Back to Library -->
<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/bible-study" class="btn btn-outline">&larr; Back to Library</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
