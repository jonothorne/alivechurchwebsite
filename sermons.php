<?php
/**
 * Sermons Browse Page - Netflix Style
 * Browse all sermons by series, speaker, or search
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/SermonManager.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('sermons');
}

$sermonManager = new SermonManager($pdo);

// Get all visible series
$allSeries = $sermonManager->getSeriesList();

// Get recent sermons for hero and "Continue Watching" style section
$recentSermonsStmt = $pdo->query("
    SELECT s.*, ss.title as series_title, ss.slug as series_slug, ss.image_url as series_image
    FROM sermons s
    LEFT JOIN sermon_series ss ON s.series_id = ss.id
    WHERE s.visible = 1
    ORDER BY s.sermon_date DESC, s.created_at DESC
    LIMIT 12
");
$recentSermons = $recentSermonsStmt->fetchAll();

// Get featured/latest sermon for hero
$heroSermon = !empty($recentSermons) ? $recentSermons[0] : null;

// Get speakers for filter
$speakersStmt = $pdo->query("
    SELECT DISTINCT speaker, COUNT(*) as sermon_count
    FROM sermons
    WHERE speaker IS NOT NULL AND speaker != '' AND visible = 1
    GROUP BY speaker
    ORDER BY sermon_count DESC, speaker
");
$speakers = $speakersStmt->fetchAll();

// Get sermons grouped by series for horizontal rows
$seriesSermonsStmt = $pdo->query("
    SELECT ss.id as series_id, ss.title as series_title, ss.slug as series_slug,
           s.id, s.title, s.slug, s.speaker, s.sermon_date, s.length, s.thumbnail_url,
           s.youtube_video_id, s.description
    FROM sermon_series ss
    JOIN sermons s ON s.series_id = ss.id AND s.visible = 1
    WHERE ss.visible = 1
    ORDER BY ss.start_date DESC, ss.id DESC, s.display_order ASC, s.sermon_date DESC
");
$allSeriesSermons = $seriesSermonsStmt->fetchAll();

// Group sermons by series
$sermonsBySeries = [];
foreach ($allSeriesSermons as $sermon) {
    $seriesId = $sermon['series_id'];
    if (!isset($sermonsBySeries[$seriesId])) {
        $sermonsBySeries[$seriesId] = [
            'title' => $sermon['series_title'],
            'slug' => $sermon['series_slug'],
            'sermons' => []
        ];
    }
    $sermonsBySeries[$seriesId]['sermons'][] = $sermon;
}

$page_title = 'Sermons | ' . $site['name'];
$page_description = 'Watch and listen to sermons from ' . $site['name'] . '. Browse by series, speaker, or search our message archive.';

include __DIR__ . '/includes/header.php';
?>

<?php
// Handle search or speaker filter
$searchQuery = $_GET['q'] ?? '';
$speakerFilter = $_GET['speaker'] ?? '';

if ($searchQuery || $speakerFilter):
    $filters = [];
    if ($speakerFilter) {
        $filters['speaker'] = $speakerFilter;
    }
    $searchResults = $sermonManager->searchSermons($searchQuery, $filters);
?>

<!-- Search Results View -->
<section class="sermons-search-results">
    <div class="container">
        <div class="search-results-header">
            <a href="/sermons" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Sermons
            </a>
            <?php if ($speakerFilter && !$searchQuery): ?>
                <h1>Messages by <?= htmlspecialchars($speakerFilter); ?></h1>
            <?php elseif ($speakerFilter && $searchQuery): ?>
                <h1>Results for "<?= htmlspecialchars($searchQuery); ?>" by <?= htmlspecialchars($speakerFilter); ?></h1>
            <?php else: ?>
                <h1>Results for "<?= htmlspecialchars($searchQuery); ?>"</h1>
            <?php endif; ?>
            <p class="results-count"><?= count($searchResults); ?> message<?= count($searchResults) !== 1 ? 's' : ''; ?> found</p>
        </div>

        <?php if (empty($searchResults)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
                <h3>No sermons found</h3>
                <p>Try different keywords or <a href="/sermons">browse all series</a></p>
            </div>
        <?php else: ?>
            <div class="search-results-grid">
                <?php foreach ($searchResults as $sermon): ?>
                    <?php $sermonUrl = $sermon['slug'] ? '/sermon/' . htmlspecialchars($sermon['slug']) : '#'; ?>
                    <a href="<?= $sermonUrl; ?>" class="sermon-result-card">
                        <div class="sermon-result-thumb">
                            <?php if (!empty($sermon['thumbnail_url'])): ?>
                                <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" alt="" loading="lazy">
                            <?php elseif (!empty($sermon['youtube_video_id'])): ?>
                                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($sermon['youtube_video_id']); ?>/mqdefault.jpg" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="thumb-placeholder"></div>
                            <?php endif; ?>
                            <div class="play-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                            <?php if (!empty($sermon['length'])): ?>
                                <span class="duration"><?= htmlspecialchars($sermon['length']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sermon-result-info">
                            <h3><?= htmlspecialchars($sermon['title'] ?? ''); ?></h3>
                            <p class="meta">
                                <?= $sermon['speaker'] ? htmlspecialchars($sermon['speaker']) : ''; ?>
                                <?= $sermon['sermon_date'] ? ' • ' . date('M j, Y', strtotime($sermon['sermon_date'])) : ''; ?>
                            </p>
                            <?php if (!empty($sermon['series_title'])): ?>
                                <span class="series-badge"><?= htmlspecialchars($sermon['series_title']); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php else: ?>

<!-- Netflix-Style Hero -->
<?php if ($heroSermon): ?>
<section class="sermons-hero-netflix">
    <div class="hero-background">
        <?php if (!empty($heroSermon['thumbnail_url'])): ?>
            <img src="<?= htmlspecialchars($heroSermon['thumbnail_url']); ?>" alt="">
        <?php elseif (!empty($heroSermon['youtube_video_id'])): ?>
            <img src="https://img.youtube.com/vi/<?= htmlspecialchars($heroSermon['youtube_video_id']); ?>/maxresdefault.jpg" alt="">
        <?php endif; ?>
        <div class="hero-gradient"></div>
    </div>

    <div class="hero-content">
        <div class="container">
            <?php if ($is_live): ?>
                <div class="live-badge">
                    <span class="live-dot"></span>
                    LIVE NOW
                </div>
            <?php endif; ?>

            <?php if (!empty($heroSermon['series_title'])): ?>
                <span class="hero-series"><?= htmlspecialchars($heroSermon['series_title']); ?></span>
            <?php endif; ?>

            <h1><?= htmlspecialchars($heroSermon['title']); ?></h1>

            <div class="hero-meta">
                <?php if (!empty($heroSermon['speaker'])): ?>
                    <span><?= htmlspecialchars($heroSermon['speaker']); ?></span>
                <?php endif; ?>
                <?php if (!empty($heroSermon['sermon_date'])): ?>
                    <span><?= date('F j, Y', strtotime($heroSermon['sermon_date'])); ?></span>
                <?php endif; ?>
                <?php if (!empty($heroSermon['length'])): ?>
                    <span><?= htmlspecialchars($heroSermon['length']); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($heroSermon['description'])): ?>
                <p class="hero-description"><?= htmlspecialchars(substr($heroSermon['description'], 0, 200)); ?><?= strlen($heroSermon['description']) > 200 ? '...' : ''; ?></p>
            <?php endif; ?>

            <div class="hero-actions">
                <a href="/sermon/<?= htmlspecialchars($heroSermon['slug']); ?>" class="btn-play">
                    <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Watch Now
                </a>
                <?php if (!empty($heroSermon['series_slug'])): ?>
                    <a href="/sermons/series/<?= htmlspecialchars($heroSermon['series_slug']); ?>" class="btn-info">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 16v-4M12 8h.01"/>
                        </svg>
                        View Series
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Search Bar Section -->
<section class="sermons-search-bar">
    <div class="container">
        <form action="/sermons" method="get" class="search-form-netflix">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="search" name="q" placeholder="Search sermons, speakers, topics..." value="">
        </form>
    </div>
</section>

<!-- Latest Messages Row -->
<?php if (count($recentSermons) > 1): ?>
<section class="sermon-row">
    <div class="container-fluid">
        <div class="row-header">
            <h2>Latest Messages</h2>
            <span class="row-count"><?= count($recentSermons); ?> messages</span>
        </div>
        <div class="sermon-slider" data-scroll="horizontal">
            <?php foreach (array_slice($recentSermons, 1, 10) as $sermon): ?>
                <?php $sermonUrl = $sermon['slug'] ? '/sermon/' . htmlspecialchars($sermon['slug']) : '#'; ?>
                <a href="<?= $sermonUrl; ?>" class="sermon-card-netflix">
                    <div class="card-thumb">
                        <?php if (!empty($sermon['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" alt="" loading="lazy">
                        <?php elseif (!empty($sermon['youtube_video_id'])): ?>
                            <img src="https://img.youtube.com/vi/<?= htmlspecialchars($sermon['youtube_video_id']); ?>/mqdefault.jpg" alt="" loading="lazy">
                        <?php else: ?>
                            <div class="thumb-placeholder"></div>
                        <?php endif; ?>
                        <div class="card-overlay">
                            <div class="play-btn">
                                <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                        </div>
                        <?php if (!empty($sermon['length'])): ?>
                            <span class="duration"><?= htmlspecialchars($sermon['length']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-info">
                        <h3><?= htmlspecialchars($sermon['title'] ?? ''); ?></h3>
                        <p class="meta"><?= !empty($sermon['speaker']) ? htmlspecialchars($sermon['speaker']) : ''; ?></p>
                        <?php if (!empty($sermon['series_title'])): ?>
                            <span class="series-tag"><?= htmlspecialchars($sermon['series_title']); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Series Rows -->
<?php foreach ($sermonsBySeries as $seriesId => $seriesData): ?>
<?php if (count($seriesData['sermons']) >= 1): ?>
<section class="sermon-row">
    <div class="container-fluid">
        <div class="row-header">
            <h2><a href="/sermons/series/<?= htmlspecialchars($seriesData['slug']); ?>"><?= htmlspecialchars($seriesData['title']); ?></a></h2>
            <a href="/sermons/series/<?= htmlspecialchars($seriesData['slug']); ?>" class="see-all">
                See All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        </div>
        <div class="sermon-slider" data-scroll="horizontal">
            <?php foreach ($seriesData['sermons'] as $sermon): ?>
                <?php $sermonUrl = $sermon['slug'] ? '/sermon/' . htmlspecialchars($sermon['slug']) : '#'; ?>
                <a href="<?= $sermonUrl; ?>" class="sermon-card-netflix">
                    <div class="card-thumb">
                        <?php if (!empty($sermon['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" alt="" loading="lazy">
                        <?php elseif (!empty($sermon['youtube_video_id'])): ?>
                            <img src="https://img.youtube.com/vi/<?= htmlspecialchars($sermon['youtube_video_id']); ?>/mqdefault.jpg" alt="" loading="lazy">
                        <?php else: ?>
                            <div class="thumb-placeholder"></div>
                        <?php endif; ?>
                        <div class="card-overlay">
                            <div class="play-btn">
                                <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </div>
                        </div>
                        <?php if (!empty($sermon['length'])): ?>
                            <span class="duration"><?= htmlspecialchars($sermon['length']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-info">
                        <h3><?= htmlspecialchars($sermon['title'] ?? ''); ?></h3>
                        <p class="meta">
                            <?= !empty($sermon['speaker']) ? htmlspecialchars($sermon['speaker']) : ''; ?>
                            <?= !empty($sermon['sermon_date']) ? ' • ' . date('M j', strtotime($sermon['sermon_date'])) : ''; ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<!-- Browse All Series -->
<section class="series-browse-netflix">
    <div class="container">
        <div class="section-header">
            <h2>Browse All Series</h2>
            <p>Explore our complete collection of sermon series</p>
        </div>

        <?php if (empty($allSeries)): ?>
            <div class="empty-state">
                <p>No sermon series available yet.</p>
            </div>
        <?php else: ?>
            <div class="series-grid-netflix">
                <?php foreach ($allSeries as $series): ?>
                    <a href="/sermons/series/<?= htmlspecialchars($series['slug']); ?>" class="series-card-netflix">
                        <div class="series-thumb">
                            <?php if ($series['image_url']): ?>
                                <img src="<?= htmlspecialchars($series['image_url']); ?>" alt="<?= htmlspecialchars($series['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="series-placeholder">
                                    <svg viewBox="0 0 24 24" fill="currentColor" opacity="0.3">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/>
                                        <polygon points="7.5 18 12 13.5 14.5 16 18 12 18 18"/>
                                        <circle cx="9" cy="9" r="1.5"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="series-overlay">
                                <span class="message-count"><?= $series['message_count']; ?> Message<?= $series['message_count'] !== 1 ? 's' : ''; ?></span>
                            </div>
                        </div>
                        <div class="series-info">
                            <h3><?= htmlspecialchars($series['title']); ?></h3>
                            <?php if ($series['date_range']): ?>
                                <p class="date"><?= htmlspecialchars($series['date_range']); ?></p>
                            <?php elseif ($series['start_date']): ?>
                                <p class="date"><?= date('F Y', strtotime($series['start_date'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Browse by Speaker -->
<?php if (!empty($speakers)): ?>
<section class="speakers-section">
    <div class="container">
        <div class="section-header">
            <h2>Browse by Speaker</h2>
        </div>
        <div class="speakers-grid">
            <?php foreach ($speakers as $speaker): ?>
                <a href="/sermons?speaker=<?= urlencode($speaker['speaker']); ?>" class="speaker-card">
                    <div class="speaker-avatar">
                        <?= strtoupper(substr($speaker['speaker'], 0, 1)); ?>
                    </div>
                    <div class="speaker-info">
                        <h3><?= htmlspecialchars($speaker['speaker']); ?></h3>
                        <p><?= $speaker['sermon_count']; ?> message<?= $speaker['sermon_count'] != 1 ? 's' : ''; ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php endif; // end search check ?>


<script>
// Horizontal scroll with mouse wheel
document.querySelectorAll('.sermon-slider').forEach(slider => {
    slider.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
            e.preventDefault();
            slider.scrollLeft += e.deltaY;
        }
    }, { passive: false });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
