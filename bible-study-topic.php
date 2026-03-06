<?php
/**
 * Bible Study - Single Topic Page
 * Handles both main categories (showing sub-topics) and sub-topics (showing questions & studies)
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/BibleStudyTagger.php';

$pdo = getDbConnection();
$tagger = new BibleStudyTagger($pdo);

$topicSlug = $_GET['topic'] ?? '';

if (empty($topicSlug)) {
    header('Location: /bible-study/topics');
    exit;
}

// Get topic info with parent data
$topic = $tagger->getTopicBySlug($topicSlug);

if (!$topic) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Topic Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero">
        <div class="container narrow">
            <h1>Topic Not Found</h1>
            <p>Sorry, we couldn't find that topic.</p>
            <a href="/bible-study/topics" class="btn btn-primary">Browse All Topics</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Check if this is a main category (level 0) or a sub-topic (level 1)
$isMainCategory = ($topic['level'] == 0);

if ($isMainCategory) {
    // Get sub-topics for this category
    $subTopics = $tagger->getSubTopics($topic['id']);

    $page_title = $topic['name'] . ' - Bible Study Topics | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>

    <section class="topic-hero category-hero">
        <div class="container">
            <div class="topic-hero-content">
                <a href="/bible-study/topics" class="back-link">&larr; All Topics</a>
                <div class="topic-hero-icon"><?= $topic['icon']; ?></div>
                <h1><?= htmlspecialchars($topic['name']); ?></h1>
                <?php if ($topic['description']): ?>
                    <p class="topic-description"><?= htmlspecialchars($topic['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="category-subtopics">
        <div class="container">
            <h2>Topics in <?= htmlspecialchars($topic['name']); ?></h2>
            <div class="subtopics-grid">
                <?php foreach ($subTopics as $subTopic): ?>
                    <?php $hasContent = $subTopic['study_count'] > 0 || $subTopic['question_count'] > 0; ?>
                    <a href="/bible-study/topics/<?= htmlspecialchars($subTopic['slug']); ?>"
                       class="subtopic-card <?= $hasContent ? 'has-content' : 'no-content'; ?>">
                        <span class="subtopic-icon"><?= $subTopic['icon']; ?></span>
                        <div class="subtopic-info">
                            <span class="subtopic-name"><?= htmlspecialchars($subTopic['name']); ?></span>
                            <?php if ($subTopic['description']): ?>
                                <span class="subtopic-description"><?= htmlspecialchars($subTopic['description']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="subtopic-stats">
                            <?php if ($subTopic['question_count'] > 0): ?>
                                <span class="question-count"><?= $subTopic['question_count']; ?> questions</span>
                            <?php endif; ?>
                            <?php if ($subTopic['study_count'] > 0): ?>
                                <span class="study-count"><?= $subTopic['study_count']; ?> studies</span>
                            <?php endif; ?>
                            <?php if (!$hasContent): ?>
                                <span class="coming-soon">Coming soon</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php
} else {
    // This is a sub-topic - show questions and studies

    // Get questions for this topic
    $questions = $tagger->getQuestionsForTopic($topic['id']);

    // Get studies for this topic
    $studies = $tagger->getStudiesByTopic($topic['id'], 50);

    // Get sibling topics (same parent)
    $siblingTopics = [];
    if ($topic['parent_id']) {
        $stmt = $pdo->prepare("
            SELECT t.*, COUNT(DISTINCT tt.study_id) as study_count
            FROM bible_study_topics t
            LEFT JOIN bible_study_topic_tags tt ON t.id = tt.topic_id
            LEFT JOIN bible_studies s ON tt.study_id = s.id AND s.status = 'published'
            WHERE t.parent_id = ? AND t.id != ?
            GROUP BY t.id
            ORDER BY t.display_order
            LIMIT 8
        ");
        $stmt->execute([$topic['parent_id'], $topic['id']]);
        $siblingTopics = $stmt->fetchAll();
    }

    $page_title = $topic['name'] . ' - Bible Study Topics | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>

    <section class="topic-hero subtopic-hero">
        <div class="container">
            <div class="topic-hero-content">
                <?php if ($topic['parent_name']): ?>
                    <a href="/bible-study/topics/<?= htmlspecialchars($topic['parent_slug']); ?>" class="back-link">
                        &larr; <?= htmlspecialchars($topic['parent_name']); ?>
                    </a>
                <?php else: ?>
                    <a href="/bible-study/topics" class="back-link">&larr; All Topics</a>
                <?php endif; ?>
                <div class="topic-hero-icon"><?= $topic['icon']; ?></div>
                <h1><?= htmlspecialchars($topic['name']); ?></h1>
                <?php if ($topic['description']): ?>
                    <p class="topic-description"><?= htmlspecialchars($topic['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($questions)): ?>
    <!-- Questions Section -->
    <section class="topic-questions">
        <div class="container">
            <h2>Questions About <?= htmlspecialchars($topic['name']); ?></h2>
            <div class="questions-grid">
                <?php foreach ($questions as $question): ?>
                    <a href="/bible-study/questions/<?= htmlspecialchars($question['slug']); ?>" class="question-card">
                        <span class="question-text"><?= htmlspecialchars($question['question']); ?></span>
                        <?php if ($question['description']): ?>
                            <span class="question-description"><?= htmlspecialchars($question['description']); ?></span>
                        <?php endif; ?>
                        <?php if ($question['study_count'] > 0): ?>
                            <span class="question-studies"><?= $question['study_count']; ?> <?= $question['study_count'] == 1 ? 'study' : 'studies'; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($studies)): ?>
    <!-- Studies Section -->
    <section class="topic-studies">
        <div class="container">
            <h2>Bible Studies on <?= htmlspecialchars($topic['name']); ?></h2>
            <div class="topic-studies-grid">
                <?php foreach ($studies as $study): ?>
                    <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" class="topic-study-card">
                        <div class="study-card-header">
                            <span class="study-book"><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></span>
                            <span class="study-relevance" title="Relevance score">
                                <?= round($study['relevance_score']); ?>%
                            </span>
                        </div>
                        <?php if ($study['title']): ?>
                            <h3 class="study-title"><?= htmlspecialchars($study['title']); ?></h3>
                        <?php endif; ?>
                        <?php if ($study['summary']): ?>
                            <p class="study-summary"><?= htmlspecialchars(substr($study['summary'], 0, 150)); ?><?= strlen($study['summary']) > 150 ? '...' : ''; ?></p>
                        <?php endif; ?>
                        <?php if ($study['reading_time']): ?>
                            <span class="study-time"><?= $study['reading_time']; ?> min read</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php elseif (empty($questions)): ?>
    <section class="content-section">
        <div class="container narrow">
            <div class="no-studies-message">
                <h2>Content Coming Soon</h2>
                <p>We're working on adding studies and questions about <?= htmlspecialchars($topic['name']); ?>.</p>
                <p>In the meantime, try searching for related keywords or browse other topics.</p>
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
                    <a href="/bible-study/search?q=<?= urlencode($topic['name']); ?>" class="btn btn-primary">Search "<?= htmlspecialchars($topic['name']); ?>"</a>
                    <a href="/bible-study/topics" class="btn btn-outline">Browse Topics</a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($siblingTopics)): ?>
    <!-- Related Topics -->
    <section class="related-topics">
        <div class="container">
            <h2>Related Topics</h2>
            <div class="related-topics-grid">
                <?php foreach ($siblingTopics as $sibling): ?>
                    <a href="/bible-study/topics/<?= htmlspecialchars($sibling['slug']); ?>" class="related-topic-card">
                        <span class="topic-icon"><?= $sibling['icon']; ?></span>
                        <span class="topic-name"><?= htmlspecialchars($sibling['name']); ?></span>
                        <?php if ($sibling['study_count'] > 0): ?>
                            <span class="topic-count"><?= $sibling['study_count']; ?> studies</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php
}
?>

<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/bible-study/topics" class="btn btn-outline">&larr; All Topics</a>
        <a href="/bible-study" class="btn btn-outline">Browse by Book</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
