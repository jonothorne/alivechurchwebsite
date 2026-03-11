<?php
/**
 * Bible Study - Question Page
 * SEO-optimized page for individual life questions
 * Shows related Bible studies that address this question
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/BibleStudyTagger.php';

$pdo = getDbConnection();
$tagger = new BibleStudyTagger($pdo);

$questionSlug = $_GET['question'] ?? '';

if (empty($questionSlug)) {
    header('Location: /bible-study/topics');
    exit;
}

// Get question details
$question = $tagger->getQuestionBySlug($questionSlug);

if (!$question) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Question Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero <?= $hero_texture_class; ?>">
        <div class="container narrow">
            <h1>Question Not Found</h1>
            <p>Sorry, we couldn't find that question.</p>
            <a href="/bible-study/topics" class="btn btn-primary">Browse Topics</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get studies that address this question
$studies = $tagger->getStudiesForQuestion($question['id'], 20);

// Get other questions in the same topic
$relatedQuestions = $pdo->prepare("
    SELECT q.*, COUNT(DISTINCT qt.study_id) as study_count
    FROM bible_study_questions q
    LEFT JOIN bible_study_question_tags qt ON q.id = qt.question_id
    LEFT JOIN bible_studies s ON qt.study_id = s.id AND s.status = 'published'
    WHERE q.topic_id = ? AND q.id != ?
    GROUP BY q.id
    ORDER BY study_count DESC
    LIMIT 6
");
$relatedQuestions->execute([$question['topic_id'], $question['id']]);
$relatedQuestions = $relatedQuestions->fetchAll();

// SEO
$seoTitle = $question['seo_title'] ?: $question['question'];
$seoDescription = $question['seo_description'] ?: $question['description'];

$page_title = $seoTitle . ' | ' . $site['name'];
$meta_description = $seoDescription;

include __DIR__ . '/includes/header.php';
?>

<article class="question-page" itemscope itemtype="https://schema.org/FAQPage">
    <section class="question-hero <?= $hero_texture_class; ?>">
        <div class="container">
            <div class="question-hero-content">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <a href="/bible-study">Bible Study</a>
                    <span>/</span>
                    <a href="/bible-study/topics">Topics</a>
                    <?php if ($question['category_slug']): ?>
                        <span>/</span>
                        <a href="/bible-study/topics/<?= htmlspecialchars($question['category_slug']); ?>">
                            <?= htmlspecialchars($question['category_name']); ?>
                        </a>
                    <?php endif; ?>
                    <span>/</span>
                    <a href="/bible-study/topics/<?= htmlspecialchars($question['topic_slug']); ?>">
                        <?= htmlspecialchars($question['topic_name']); ?>
                    </a>
                </nav>

                <div class="question-topic-badge">
                    <?= $question['topic_icon']; ?> <?= htmlspecialchars($question['topic_name']); ?>
                </div>

                <h1 itemprop="name"><?= htmlspecialchars($question['question']); ?></h1>

                <?php if ($question['description']): ?>
                    <p class="question-description"><?= htmlspecialchars($question['description']); ?></p>
                <?php endif; ?>

                <p class="question-stats">
                    <?= count($studies); ?> Bible <?= count($studies) == 1 ? 'study addresses' : 'studies address'; ?> this question
                </p>
            </div>
        </div>
    </section>

    <?php if (!empty($studies)): ?>
    <section class="question-studies" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
        <meta itemprop="name" content="<?= htmlspecialchars($question['question']); ?>">
        <div class="container">
            <h2>What the Bible Says</h2>
            <p class="section-intro">These Bible studies explore Scripture passages that speak to this question. Each study will give you insight and practical application.</p>

            <div class="question-studies-grid" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                <?php foreach ($studies as $index => $study): ?>
                    <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>"
                       class="question-study-card"
                       itemprop="<?= $index === 0 ? 'text' : ''; ?>">
                        <div class="study-rank"><?= $index + 1; ?></div>
                        <div class="study-details">
                            <span class="study-reference"><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></span>
                            <?php if ($study['title']): ?>
                                <h3 class="study-title"><?= htmlspecialchars($study['title']); ?></h3>
                            <?php endif; ?>
                            <?php if ($study['summary']): ?>
                                <p class="study-summary"><?= htmlspecialchars(substr($study['summary'], 0, 200)); ?><?= strlen($study['summary']) > 200 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <div class="study-meta">
                                <?php if ($study['reading_time']): ?>
                                    <span class="reading-time"><?= $study['reading_time']; ?> min read</span>
                                <?php endif; ?>
                                <span class="relevance-badge" title="How closely this study relates to your question">
                                    <?= round($study['relevance_score']); ?>% relevant
                                </span>
                            </div>
                        </div>
                        <span class="read-arrow">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php else: ?>
    <section class="content-section">
        <div class="container narrow">
            <div class="no-studies-message">
                <h2>Studies Coming Soon</h2>
                <p>We're working on connecting Bible studies to this question. In the meantime, you might find helpful content by:</p>
                <ul style="text-align: left; display: inline-block; margin: 1rem 0;">
                    <li>Searching for related keywords</li>
                    <li>Browsing the <?= htmlspecialchars($question['topic_name']); ?> topic</li>
                    <li>Exploring other questions below</li>
                </ul>
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
                    <a href="/bible-study/search?q=<?= urlencode($question['topic_name']); ?>" class="btn btn-primary">Search Related Content</a>
                    <a href="/bible-study/topics/<?= htmlspecialchars($question['topic_slug']); ?>" class="btn btn-outline">Browse <?= htmlspecialchars($question['topic_name']); ?></a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($relatedQuestions)): ?>
    <!-- Related Questions -->
    <section class="related-questions">
        <div class="container">
            <h2>Related Questions</h2>
            <div class="related-questions-grid">
                <?php foreach ($relatedQuestions as $related): ?>
                    <a href="/bible-study/questions/<?= htmlspecialchars($related['slug']); ?>" class="related-question-card">
                        <span class="question-text"><?= htmlspecialchars($related['question']); ?></span>
                        <?php if ($related['study_count'] > 0): ?>
                            <span class="study-count"><?= $related['study_count']; ?> <?= $related['study_count'] == 1 ? 'study' : 'studies'; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action -->
    <section class="question-cta">
        <div class="container narrow">
            <div class="cta-box">
                <h3>Still Have Questions?</h3>
                <p>The Bible has answers for every aspect of life. Keep exploring, or reach out to our pastoral team for personal guidance.</p>
                <div class="cta-buttons">
                    <a href="/bible-study/topics" class="btn btn-primary">Explore More Topics</a>
                    <a href="/contact" class="btn btn-outline">Talk to Someone</a>
                </div>
            </div>
        </div>
    </section>
</article>

<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/bible-study/topics/<?= htmlspecialchars($question['topic_slug']); ?>" class="btn btn-outline">
            &larr; More on <?= htmlspecialchars($question['topic_name']); ?>
        </a>
        <a href="/bible-study/topics" class="btn btn-outline">All Topics</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
