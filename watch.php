<?php
require __DIR__ . '/config.php';
$page_title = 'Watch | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('watch');
}
?>
<section class="page-hero watch-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <?php if ($is_live): ?>
            <div class="live-indicator">
                <span class="live-dot"></span>
                LIVE NOW
            </div>
        <?php endif; ?>

        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="watch" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Watch & Listen'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="watch" data-cms-type="text"><?= $cms->text('hero_headline', $is_live ? 'Join us live right now!' : 'Church wherever you are.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="watch" data-cms-type="text"><?= $cms->text('hero_subtext', 'Stream this week\'s message, browse past series, or listen on your favorite podcast platform.'); ?></p>
    </div>
</section>

<!-- Live Stream Section -->
<section class="live-stream-section">
    <div class="container">
        <?php if ($is_live): ?>
            <div class="video-player-wrapper live-player">
                <iframe
                    src="<?= htmlspecialchars($live_stream_url); ?>"
                    title="Alive Church Live Stream"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media;
                           gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div>

            <div class="live-chat-cta">
                <h3>Join the conversation</h3>
                <p>Chat with our online community, share prayer requests,
                   and connect with our team.</p>
                <a class="btn btn-primary" href="<?= htmlspecialchars($site['social']['youtube']); ?>"
                   target="_blank" rel="noopener">Open Live Chat</a>
            </div>
        <?php else: ?>
            <div class="next-live-info">
                <h3 data-cms-editable="next_live_title" data-cms-page="watch" data-cms-type="text"><?= $cms->text('next_live_title', 'Next Live Stream'); ?></h3>
                <p class="next-live-time" data-cms-editable="next_live_time" data-cms-page="watch" data-cms-type="text"><?= $cms->text('next_live_time', 'This Sunday at 10:55AM GMT'); ?></p>
                <p data-cms-editable="next_live_text" data-cms-page="watch" data-cms-type="text"><?= $cms->text('next_live_text', 'Set a reminder and we\'ll notify you when we go live.'); ?></p>
                <a class="btn btn-primary" href="<?= htmlspecialchars($site['social']['youtube']); ?>"
                   target="_blank" rel="noopener">Subscribe on YouTube</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<!-- Sermon Archive -->
<section class="sermon-archive">
    <div class="container">
        <div class="section-heading">
            <h2 data-cms-editable="series_headline" data-cms-page="watch" data-cms-type="text"><?= $cms->text('series_headline', 'Recent series'); ?></h2>
            <p data-cms-editable="series_subtext" data-cms-page="watch" data-cms-type="text"><?= $cms->text('series_subtext', 'Browse by series, speaker, or topic.'); ?></p>
        </div>

        <!-- Sermon Series Grid -->
        <div class="series-grid">
            <?php foreach ($sermon_series as $series): ?>
                <article class="series-card">
                    <div class="series-image-wrapper">
                        <img src="<?= htmlspecialchars($series['image']); ?>"
                             alt="<?= htmlspecialchars($series['title']); ?> series at Alive Church"
                             class="series-image">
                        <div class="series-overlay">
                            <span class="sermon-count"><?= $series['message_count']; ?> Messages</span>
                        </div>
                    </div>
                    <h3><?= htmlspecialchars($series['title']); ?></h3>
                    <p class="series-meta"><?= htmlspecialchars($series['date_range']); ?></p>
                    <p><?= htmlspecialchars($series['description']); ?></p>
                    <a class="text-link" href="<?= htmlspecialchars($site['social']['youtube']); ?>" target="_blank" rel="noopener">
                        Watch series →
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="podcast-section">
    <div class="container split">
        <div>
            <h2 data-cms-editable="podcast_headline" data-cms-page="watch" data-cms-type="text"><?= $cms->text('podcast_headline', 'Listen on the go'); ?></h2>
            <p data-cms-editable="podcast_text" data-cms-page="watch" data-cms-type="text"><?= $cms->text('podcast_text', 'Subscribe to the Alive One podcast for weekly messages, interviews, and stories of God at work. Available on all major platforms.'); ?></p>

            <div class="podcast-buttons">
                <a class="podcast-btn" href="https://open.spotify.com/search/alive%20church" target="_blank" rel="noopener">
                    <span>🎵</span>
                    Spotify
                </a>
                <a class="podcast-btn" href="https://podcasts.apple.com/search?term=alive%20church" target="_blank" rel="noopener">
                    <span>🎧</span>
                    Apple Podcasts
                </a>
                <a class="podcast-btn" href="https://podcasts.google.com/search/alive%20church" target="_blank" rel="noopener">
                    <span>📻</span>
                    Google Podcasts
                </a>
            </div>
        </div>

        <div class="card">
            <img src="/assets/imgs/gallery/alive-church-worship-leaders-performance.jpg"
                 alt="Prayer room worship at Alive Church"
                 style="border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;"
                 data-cms-editable="prayer_room_image" data-cms-page="watch" data-cms-type="image">
            <h3 data-cms-editable="prayer_room_title" data-cms-page="watch" data-cms-type="text"><?= $cms->text('prayer_room_title', 'Prayer Room Live'); ?></h3>
            <p data-cms-editable="prayer_room_text" data-cms-page="watch" data-cms-type="text"><?= $cms->text('prayer_room_text', 'Weekdays at 7AM we stream live worship and intercession from the Prayer Room. Submit requests and we will pray with you in real time.'); ?></p>
            <a class="text-link" href="/connect#next-steps">Share a prayer need →</a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
