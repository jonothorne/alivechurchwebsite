<?php
/**
 * All Sermon Series Page
 * Browse all sermon series
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get all visible series with sermon counts
$seriesStmt = $pdo->query("
    SELECT ss.*,
           COUNT(s.id) as sermon_count,
           MIN(s.sermon_date) as first_sermon,
           MAX(s.sermon_date) as last_sermon
    FROM sermon_series ss
    LEFT JOIN sermons s ON s.series_id = ss.id AND s.visible = 1
    WHERE ss.visible = 1
    GROUP BY ss.id
    HAVING sermon_count > 0
    ORDER BY ss.start_date DESC, ss.id DESC
");
$allSeries = $seriesStmt->fetchAll();

$page_title = 'Sermon Series | ' . $site['name'];
$page_description = 'Browse all sermon series from ' . $site['name'] . '.';

include __DIR__ . '/../includes/header.php';
?>

<section class="sermons-page">
    <div class="container">
        <div class="page-header" style="margin-bottom: 2rem;">
            <h1>All Sermon Series</h1>
            <p style="color: var(--color-text-muted); margin-top: 0.5rem;">
                <?= count($allSeries); ?> series available
            </p>
        </div>

        <?php if (empty($allSeries)): ?>
            <div class="empty-state">
                <p>No sermon series found.</p>
            </div>
        <?php else: ?>
            <div class="series-grid">
                <?php foreach ($allSeries as $series):
                    $seriesImage = $series['image_url'] ?: '/assets/images/series-placeholder.jpg';
                ?>
                    <a href="/sermons/series/<?= htmlspecialchars($series['slug']); ?>" class="series-card">
                        <div class="series-card-image">
                            <img src="<?= htmlspecialchars($seriesImage); ?>" alt="<?= htmlspecialchars($series['title']); ?>" loading="lazy">
                            <div class="series-card-overlay">
                                <span class="sermon-count"><?= $series['sermon_count']; ?> sermon<?= $series['sermon_count'] != 1 ? 's' : ''; ?></span>
                            </div>
                        </div>
                        <div class="series-card-content">
                            <h3><?= htmlspecialchars($series['title']); ?></h3>
                            <?php if ($series['description']): ?>
                                <p><?= htmlspecialchars(substr($series['description'], 0, 100)); ?><?= strlen($series['description']) > 100 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <?php if ($series['first_sermon']): ?>
                                <span class="series-dates">
                                    <?= date('M Y', strtotime($series['first_sermon'])); ?>
                                    <?php if ($series['last_sermon'] && $series['first_sermon'] !== $series['last_sermon']): ?>
                                        - <?= date('M Y', strtotime($series['last_sermon'])); ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
