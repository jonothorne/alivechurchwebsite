<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/SermonManager.php';

$page_title = 'Alive Church | You Belong Here';

// Initialize CMS for homepage
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('home');
}

// Get featured sermon from database (falls back to config.php data)
$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);
$db_featured_sermon = $sermonManager->getFeaturedSermon('homepage');

// If no database sermon, use the hardcoded one from config.php
if ($db_featured_sermon) {
    $featured_sermon = [
        'title' => $db_featured_sermon['title'],
        'speaker' => $db_featured_sermon['speaker'] ?? '',
        'date' => $db_featured_sermon['sermon_date'] ? date('F j, Y', strtotime($db_featured_sermon['sermon_date'])) : '',
        'length' => $db_featured_sermon['length'] ?? '',
        'video_id' => $db_featured_sermon['youtube_video_id'] ?: $db_featured_sermon['video_id'],
        'slug' => $db_featured_sermon['slug']
    ];
}

// Get last 3 sermons for the "latest messages" section
$recentSermons = $sermonManager->getRecentSermons(3);

include __DIR__ . '/includes/header.php';
?>
<section class="hero" id="visit">
    <div class="container hero-content">
        <p class="hero-tag" data-cms-editable="hero_tagline" data-cms-page="home" data-cms-type="text"><?= $cms->text('hero_tagline', $site['tagline']); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('hero_headline', 'Church for everyone, including you.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="home" data-cms-type="text"><?= $cms->text('hero_subtext', 'Life-giving worship, practical teaching, and real community in Norwich and online.'); ?></p>
        <div class="hero-ctas">
            <a class="btn btn-primary" href="/visit">Plan Your Visit</a>
            <a class="btn btn-secondary" href="/watch">Watch Online</a>
        </div>

        <!-- Service Times & Location (CRITICAL - 3 second rule) -->
        <div class="hero-info-cards">
            <div class="hero-info-card">
                <span class="info-icon">📍</span>
                <div class="info-content">
                    <strong>Find Us</strong>
                    <p><?= htmlspecialchars($site['location']); ?></p>
                    <a href="<?= htmlspecialchars($site['maps_url']); ?>" target="_blank" class="text-link-light">Get Directions →</a>
                </div>
            </div>
            <div class="hero-info-card">
                <span class="info-icon">🕐</span>
                <div class="info-content">
                    <strong>Service Times</strong>
                    <p><?= htmlspecialchars($site['service_times']); ?><br>
                    <small style="opacity: 0.9;"><?= htmlspecialchars($site['service_details']); ?></small></p>
                    <a href="/visit" class="text-link-light">Plan Your Visit →</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- I'm New Here CTA Card -->
<section class="new-here-cta">
    <div class="container">
        <div class="cta-card">
            <div class="cta-content">
                <span class="eyebrow" data-cms-editable="newhere_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('newhere_eyebrow', 'First Time?'); ?></span>
                <h2 data-cms-editable="newhere_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('newhere_headline', 'We saved you a seat.'); ?></h2>
                <div data-cms-editable="newhere_text" data-cms-page="home" data-cms-type="html"><?= $cms->html('newhere_text', '<p>Let us know you are visiting and we will save you a seat and make sure you are welcome. Alive Church is for everyone, and we want to make you feel welcome, no matter what you believe or where you are on your journey.<br><strong>We are so excited to meet you!</strong></p>'); ?></div>
                <a class="btn btn-primary" href="/visit">Plan Your First Visit</a>
            </div>
            <div class="cta-image">
                <img src="/assets/imgs/gallery/alive-church-christmas-service-celebration.jpg"
                     alt="Welcoming team at Alive Church"
                     data-cms-editable="newhere_image" data-cms-page="home" data-cms-type="image">
            </div>
        </div>
    </div>
</section>

<!-- Featured Sermon with Video Player -->
<section class="sermon-feature">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="sermon_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('sermon_eyebrow', 'Latest Message'); ?></p>
            <h2 data-cms-editable="sermon_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('sermon_headline', 'Watch this week\'s teaching.'); ?></h2>
        </div>

        <!-- Embedded YouTube Player -->
        <div class="video-player-wrapper">
            <iframe
                src="https://www.youtube.com/embed/<?= $featured_sermon['video_id']; ?>"
                title="<?= htmlspecialchars($featured_sermon['title']); ?>"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media;
                       gyroscope; picture-in-picture"
                allowfullscreen>
            </iframe>
        </div>

        <div class="sermon-info">
            <h3><?= htmlspecialchars($featured_sermon['title']); ?></h3>
            <p class="sermon-meta">
                <?= htmlspecialchars($featured_sermon['speaker']); ?> •
                <?= htmlspecialchars($featured_sermon['date']); ?> •
                <?= htmlspecialchars($featured_sermon['length']); ?>
            </p>
        </div>

        <div class="hero-ctas" style="justify-content:center; margin-top: 1.5rem;">
            <?php if (!empty($featured_sermon['slug'])): ?>
                <a class="btn btn-primary" href="/sermon/<?= htmlspecialchars($featured_sermon['slug']); ?>">View Full Message</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="/sermons">Watch More Messages</a>
        </div>
    </div>
</section>

<!-- This Weekend at Alive -->
<section class="weekend-preview">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="weekend_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('weekend_eyebrow', 'This Weekend'); ?></p>
            <h2 data-cms-editable="weekend_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('weekend_headline', 'Happening at Alive'); ?></h2>
        </div>

        <div class="weekend-grid">
            <?php foreach ($weekend_events as $event): ?>
                <article class="weekend-card">
                    <div class="event-time"><?= htmlspecialchars($event['time']); ?></div>
                    <h3><?= htmlspecialchars($event['title']); ?></h3>
                    <p><?= htmlspecialchars($event['description']); ?></p>
                    <a class="text-link" href="<?= htmlspecialchars($event['url']); ?>">
                        Learn more →
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="center-link">
            <a class="btn btn-outline" href="/events">View All Events</a>
        </div>
    </div>
</section>

<section class="about" id="about">
    <div class="container split">
        <div>
            <h2 data-cms-editable="about_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('about_headline', 'Live fully alive in Jesus.'); ?></h2>
            <div data-cms-editable="about_text" data-cms-page="home" data-cms-type="html"><?= $cms->html('about_text', '<p>We exist to help every person know God, find family, discover purpose, and make a difference. Whether you grew up in church or you\'re just exploring faith, you can belong here in our family.</p>'); ?></div>
            <div class="stats-grid">
                <div>
                    <p class="stat" data-cms-editable="stat1_number" data-cms-page="home" data-cms-type="text"><?= $cms->text('stat1_number', '6'); ?></p>
                    <p class="stat-label" data-cms-editable="stat1_label" data-cms-page="home" data-cms-type="text"><?= $cms->text('stat1_label', 'campuses & online'); ?></p>
                </div>
                <div>
                    <p class="stat" data-cms-editable="stat2_number" data-cms-page="home" data-cms-type="text"><?= $cms->text('stat2_number', '120+'); ?></p>
                    <p class="stat-label" data-cms-editable="stat2_label" data-cms-page="home" data-cms-type="text"><?= $cms->text('stat2_label', 'groups & teams'); ?></p>
                </div>
                <div>
                    <p class="stat" data-cms-editable="stat3_number" data-cms-page="home" data-cms-type="text"><?= $cms->text('stat3_number', '800'); ?></p>
                    <p class="stat-label" data-cms-editable="stat3_label" data-cms-page="home" data-cms-type="text"><?= $cms->text('stat3_label', 'meals served last month'); ?></p>
                </div>
            </div>
            <img src="/assets/imgs/gallery/alive-church-family-worship-lincolnshire.jpg" alt="Alive Church community worship in Lincolnshire" style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;" data-cms-editable="about_image" data-cms-page="home" data-cms-type="image">
        </div>
        <div class="card">
            <p class="eyebrow light">New here?</p>
            <h3 data-cms-editable="sidebar_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('sidebar_headline', 'We saved you a seat.'); ?></h3>
            <p data-cms-editable="sidebar_text" data-cms-page="home" data-cms-type="text"><?= $cms->text('sidebar_text', 'Pre-register and we\'ll connect you with a host to show you around, introduce the team, and help you make new friends.'); ?></p>
            <a class="btn btn-primary" href="/visit">Be Our Guest</a>
        </div>
    </div>
</section>
<section class="ministries" id="ministries">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow" data-cms-editable="ministries_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('ministries_eyebrow', 'Ministries & Projects'); ?></p>
            <h2 data-cms-editable="ministries_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('ministries_headline', 'There\'s a place for you to grow and lead.'); ?></h2>
        </div>
        <div class="card-grid">
            <?php foreach ($ministries as $item): ?>
                <article class="ministry-card">
                    <h3><?= $item['title']; ?></h3>
                    <p><?= $item['summary']; ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="center-link">
            <a class="btn btn-outline" href="/ministries.php">Explore ministries</a>
        </div>
    </div>
</section>
<section class="watch" id="watch">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="watch_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('watch_eyebrow', 'Watch & Listen'); ?></p>
            <h2 data-cms-editable="watch_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('watch_headline', 'Catch up on the latest messages.'); ?></h2>
        </div>
        <div class="sermon-grid">
            <?php
            $fallback_images = [
                '/assets/imgs/gallery/alive-church-worship-team-stage.jpg',
                '/assets/imgs/gallery/alive-church-live-worship-band-lincolnshire.jpg',
                '/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg'
            ];
            foreach ($recentSermons as $index => $sermon):
                $img = $sermon['thumbnail'] ?? $fallback_images[$index] ?? $fallback_images[0];
                $speaker = $sermon['speaker'] ?? $sermon['speaker_name'] ?? '';
                $length = $sermon['length'] ?? '';
            ?>
                <article class="sermon-card">
                    <img src="<?= htmlspecialchars($img); ?>" alt="<?= htmlspecialchars($sermon['title']); ?> at Alive Church" class="sermon-image">
                    <p class="sermon-meta"><?= htmlspecialchars($speaker); ?><?= $length ? ' • ' . htmlspecialchars($length) : ''; ?></p>
                    <h3><?= htmlspecialchars($sermon['title']); ?></h3>
                    <a class="text-link" href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>">Watch now →</a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="hero-ctas" style="justify-content:center;">
            <a class="btn btn-primary" href="/watch">Watch Live</a>
            <a class="btn btn-secondary" href="/watch">Listen on Alive One</a>
        </div>
    </div>
</section>
<section class="events" id="events">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="events_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('events_eyebrow', 'Don\'t miss out'); ?></p>
            <h2 data-cms-editable="events_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('events_headline', 'What\'s happening at Alive.'); ?></h2>
        </div>
        <div class="card-grid">
            <?php foreach ($events as $event): ?>
                <article class="event-card">
                    <p class="event-date"><?= $event['date']; ?></p>
                    <h3><?= $event['title']; ?></h3>
                    <p><?= $event['description']; ?></p>
                    <a class="text-link" href="<?= $event['cta']; ?>">Learn more →</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="next-steps" id="next-steps">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="nextsteps_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('nextsteps_eyebrow', 'Your next step'); ?></p>
            <h2 data-cms-editable="nextsteps_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('nextsteps_headline', 'We\'ll walk with you every step of the journey.'); ?></h2>
        </div>
        <div class="card-grid">
            <?php foreach ($next_steps as $step): ?>
                <article class="step-card">
                    <h3><?= $step['title']; ?></h3>
                    <p><?= $step['copy']; ?></p>
                    <a href="<?= $step['link']; ?>" class="text-link">Discover more →</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="photo-gallery">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="gallery_eyebrow" data-cms-page="home" data-cms-type="text"><?= $cms->text('gallery_eyebrow', 'Our Community'); ?></p>
            <h2 data-cms-editable="gallery_headline" data-cms-page="home" data-cms-type="text"><?= $cms->text('gallery_headline', 'See what happens when we gather.'); ?></h2>
        </div>
        <div class="gallery-grid">
            <img src="/assets/imgs/gallery/alive-church-worship-congregation.jpg" alt="Alive Church worship service with congregation" class="gallery-img">
            <img src="/assets/imgs/gallery/alive-church-drummer-worship-team.jpg" alt="Alive Church drummer during worship" class="gallery-img">
            <img src="/assets/imgs/gallery/alive-church-community-cafe-outdoor.jpg" alt="Alive Church community café outdoor gathering" class="gallery-img">
            <img src="/assets/imgs/gallery/alive-church-christmas-worship-service.jpg" alt="Alive Church Christmas worship service" class="gallery-img">
            <img src="/assets/imgs/gallery/alive-church-christmas-service-celebration.jpg" alt="Alive Church Christmas celebration service" class="gallery-img">
            <img src="/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg" alt="Alive Church acoustic worship and prayer" class="gallery-img">
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
