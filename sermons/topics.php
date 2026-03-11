<?php
/**
 * Sermon Topics Page
 * Browse sermons by topic
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get all topics with sermon counts
$topicsStmt = $pdo->query("
    SELECT t.id, t.name, t.slug, t.icon,
           COUNT(DISTINCT stt.sermon_id) as sermon_count
    FROM bible_study_topics t
    JOIN sermon_topic_tags stt ON stt.topic_id = t.id
    JOIN sermons s ON s.id = stt.sermon_id AND s.visible = 1
    GROUP BY t.id
    ORDER BY sermon_count DESC, t.name
");
$topics = $topicsStmt->fetchAll();

// Check if viewing a specific topic
$selectedTopicSlug = $_GET['topic'] ?? '';
$sermons = [];
$selectedTopic = null;

if ($selectedTopicSlug) {
    // Get topic info
    $topicStmt = $pdo->prepare("SELECT * FROM bible_study_topics WHERE slug = ?");
    $topicStmt->execute([$selectedTopicSlug]);
    $selectedTopic = $topicStmt->fetch();

    if ($selectedTopic) {
        $sermonsStmt = $pdo->prepare("
            SELECT s.*, ss.title as series_title, ss.slug as series_slug, stt.relevance_score
            FROM sermons s
            JOIN sermon_topic_tags stt ON stt.sermon_id = s.id
            LEFT JOIN sermon_series ss ON s.series_id = ss.id
            WHERE s.visible = 1
              AND stt.topic_id = ?
            ORDER BY stt.relevance_score DESC, s.sermon_date DESC
        ");
        $sermonsStmt->execute([$selectedTopic['id']]);
        $sermons = $sermonsStmt->fetchAll();
    }
}

$page_title = $selectedTopic ? $selectedTopic['name'] . ' | Topics | ' . $site['name'] : 'Topics | ' . $site['name'];
$page_description = $selectedTopic
    ? 'Sermons about ' . $selectedTopic['name'] . ' at ' . $site['name']
    : 'Browse sermons by topic at ' . $site['name'] . '.';

include __DIR__ . '/../includes/header.php';
?>

<section class="sermons-page">
    <div class="container">
        <?php if ($selectedTopic && !empty($sermons)): ?>
            <!-- Individual Topic View -->
            <div class="page-header" style="margin-bottom: 2rem;">
                <a href="/sermons/topics" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--color-primary); margin-bottom: 1rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    All Topics
                </a>
                <h1><?= htmlspecialchars($selectedTopic['name']); ?></h1>
                <p style="color: var(--color-text-muted); margin-top: 0.5rem;">
                    <?= count($sermons); ?> sermon<?= count($sermons) != 1 ? 's' : ''; ?>
                </p>
            </div>

            <div class="sermons-list">
                <?php foreach ($sermons as $sermon):
                    $thumbnail = $sermon['thumbnail_url'] ?: ($sermon['youtube_video_id'] ? 'https://img.youtube.com/vi/' . $sermon['youtube_video_id'] . '/maxresdefault.jpg' : '/assets/images/sermon-placeholder.jpg');
                ?>
                    <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" class="sermon-list-item">
                        <div class="sermon-thumbnail">
                            <img src="<?= htmlspecialchars($thumbnail); ?>" alt="<?= htmlspecialchars($sermon['title']); ?>" loading="lazy">
                            <?php if ($sermon['length']): ?>
                                <span class="sermon-duration"><?= htmlspecialchars($sermon['length']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sermon-info">
                            <h3><?= htmlspecialchars($sermon['title']); ?></h3>
                            <div class="sermon-meta">
                                <?php if ($sermon['speaker']): ?>
                                    <span class="speaker-name"><?= htmlspecialchars($sermon['speaker']); ?></span>
                                <?php endif; ?>
                                <?php if ($sermon['series_title']): ?>
                                    <span class="series-badge"><?= htmlspecialchars($sermon['series_title']); ?></span>
                                <?php endif; ?>
                                <span class="sermon-date"><?= date('M j, Y', strtotime($sermon['sermon_date'])); ?></span>
                            </div>
                            <?php if ($sermon['description']): ?>
                                <p class="sermon-description"><?= htmlspecialchars(substr($sermon['description'], 0, 150)); ?><?= strlen($sermon['description']) > 150 ? '...' : ''; ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- All Topics Grid -->
            <div class="page-header" style="margin-bottom: 2rem;">
                <h1>Topics</h1>
                <p style="color: var(--color-text-muted); margin-top: 0.5rem;">
                    Browse sermons by topic
                </p>
            </div>

            <?php if (empty($topics)): ?>
                <div class="empty-state">
                    <p>No topics with sermons found. Topics will appear here once sermons are tagged.</p>
                </div>
            <?php else: ?>
                <div class="topics-grid">
                    <?php foreach ($topics as $topic): ?>
                        <a href="/sermons/topics?topic=<?= urlencode($topic['slug']); ?>" class="topic-card">
                            <?php if ($topic['icon']): ?>
                                <span class="topic-icon"><?= $topic['icon']; ?></span>
                            <?php endif; ?>
                            <h3><?= htmlspecialchars($topic['name']); ?></h3>
                            <span class="sermon-count"><?= $topic['sermon_count']; ?> sermon<?= $topic['sermon_count'] != 1 ? 's' : ''; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
