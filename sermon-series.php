<?php
/**
 * Sermon Series Page
 * Shows all sermons in a series with series info
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/SermonManager.php';

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);

// Get series by slug
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: /sermons');
    exit;
}

// Get series info
$seriesStmt = $pdo->prepare("SELECT * FROM sermon_series WHERE slug = ? AND visible = 1");
$seriesStmt->execute([$slug]);
$series = $seriesStmt->fetch();

if (!$series) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Series Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero <?= $hero_texture_class; ?>">
        <div class="container narrow">
            <h1>Series Not Found</h1>
            <p>The sermon series you're looking for doesn't exist or has been removed.</p>
            <a href="/sermons" class="btn btn-primary">Browse All Series</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get all sermons in this series
$sermons = $sermonManager->getSermonsBySeries($series['id']);

// Get first sermon for "Start Watching" button
$firstSermon = !empty($sermons) ? $sermons[0] : null;

$page_title = $series['title'] . ' | Sermon Series | ' . $site['name'];
$page_description = $series['description'] ? substr(strip_tags($series['description']), 0, 155) : 'Watch the ' . $series['title'] . ' sermon series from ' . $site['name'];

include __DIR__ . '/includes/header.php';
?>

<article class="series-page">
    <!-- Series Header -->
    <section class="series-header">
        <div class="container">
            <a href="/sermons" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                All Series
            </a>

            <div class="series-header-content">
                <?php if ($series['image_url']): ?>
                    <div class="series-artwork">
                        <img src="<?= htmlspecialchars($series['image_url']); ?>" alt="<?= htmlspecialchars($series['title']); ?>">
                    </div>
                <?php endif; ?>

                <div class="series-info">
                    <div class="series-meta-badges">
                        <span class="badge"><?= count($sermons); ?> Message<?= count($sermons) !== 1 ? 's' : ''; ?></span>
                        <?php if ($series['date_range']): ?>
                            <span class="badge"><?= htmlspecialchars($series['date_range']); ?></span>
                        <?php elseif ($series['start_date']): ?>
                            <span class="badge">
                                <?= date('M Y', strtotime($series['start_date'])); ?>
                                <?php if ($series['end_date'] && $series['end_date'] !== $series['start_date']): ?>
                                    - <?= date('M Y', strtotime($series['end_date'])); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1><?= htmlspecialchars($series['title']); ?></h1>

                    <?php if ($series['description']): ?>
                        <p class="series-description"><?= nl2br(htmlspecialchars($series['description'])); ?></p>
                    <?php endif; ?>

                    <?php if ($firstSermon): ?>
                        <div class="series-actions">
                            <a href="/sermon/<?= htmlspecialchars($firstSermon['slug']); ?>" class="btn-play-series">
                                <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                Start Watching
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Sermons List -->
    <section class="series-sermons">
        <div class="container">
            <h2 class="section-title">All Messages</h2>

            <?php if (empty($sermons)): ?>
                <div class="empty-state">
                    <p>No sermons have been added to this series yet.</p>
                </div>
            <?php else: ?>
                <div class="sermons-list">
                    <?php foreach ($sermons as $i => $sermon): ?>
                        <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" class="sermon-card">
                            <div class="sermon-number"><?= $i + 1; ?></div>

                            <div class="sermon-thumbnail">
                                <?php if ($sermon['thumbnail_url']): ?>
                                    <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" alt="" loading="lazy">
                                <?php elseif ($sermon['youtube_video_id']): ?>
                                    <img src="https://img.youtube.com/vi/<?= htmlspecialchars($sermon['youtube_video_id']); ?>/mqdefault.jpg" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="thumb-placeholder"></div>
                                <?php endif; ?>
                                <div class="play-overlay">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </div>
                                <?php if ($sermon['length']): ?>
                                    <span class="duration"><?= htmlspecialchars($sermon['length']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="sermon-details">
                                <h3><?= htmlspecialchars($sermon['title']); ?></h3>

                                <div class="sermon-meta">
                                    <?php if ($sermon['speaker']): ?>
                                        <span class="speaker"><?= htmlspecialchars($sermon['speaker']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($sermon['sermon_date']): ?>
                                        <span class="date"><?= date('M j, Y', strtotime($sermon['sermon_date'])); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($sermon['description']): ?>
                                    <p class="sermon-excerpt">
                                        <?= htmlspecialchars(substr($sermon['description'], 0, 120)); ?><?= strlen($sermon['description']) > 120 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="sermon-arrow">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</article>


<?php include __DIR__ . '/includes/footer.php'; ?>
