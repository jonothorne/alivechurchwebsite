<?php
/**
 * Individual Sermon Page - Enhanced Design
 * Shows full sermon with video player, sidebar, transcript, share options, comments, and suggestions
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/SermonManager.php';
require_once __DIR__ . '/includes/profanity-filter.php';

$sermonManager = new SermonManager($pdo);

// Get sermon by slug
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: /sermons');
    exit;
}

$sermon = $sermonManager->getSermonBySlug($slug);

if (!$sermon) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Sermon Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero <?= $hero_texture_class; ?>">
        <div class="container narrow">
            <h1>Sermon Not Found</h1>
            <p>The sermon you're looking for doesn't exist or has been removed.</p>
            <a href="/sermons" class="btn btn-primary">Browse All Sermons</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get series info if this sermon is part of a series
$series = null;
$seriesSermons = [];
$prevSermon = null;
$nextSermon = null;

if ($sermon['series_id']) {
    $seriesStmt = $pdo->prepare("SELECT * FROM sermon_series WHERE id = ?");
    $seriesStmt->execute([$sermon['series_id']]);
    $series = $seriesStmt->fetch();

    // Get other sermons in this series for navigation
    $seriesSermons = $sermonManager->getSermonsBySeries($sermon['series_id']);

    // Find current sermon position and prev/next
    foreach ($seriesSermons as $i => $s) {
        if ($s['id'] === $sermon['id']) {
            $prevSermon = $seriesSermons[$i - 1] ?? null;
            $nextSermon = $seriesSermons[$i + 1] ?? null;
            break;
        }
    }
}

// Get related Bible studies
$relatedStudies = $sermonManager->getStudyLinks($sermon['id']);

// Get speaker info if linked to a user
$speaker = null;
if ($sermon['speaker_user_id']) {
    $speakerStmt = $pdo->prepare("SELECT id, full_name, username, bio, profile_image FROM users WHERE id = ?");
    $speakerStmt->execute([$sermon['speaker_user_id']]);
    $speaker = $speakerStmt->fetch();
}

// Get suggested sermons (same speaker or series, or recent)
$suggestedSermons = [];
$suggestStmt = $pdo->prepare("
    SELECT s.*, ss.title as series_title, ss.slug as series_slug
    FROM sermons s
    LEFT JOIN sermon_series ss ON s.series_id = ss.id
    WHERE s.visible = 1 AND s.id != ?
    ORDER BY
        CASE WHEN s.series_id = ? THEN 0 ELSE 1 END,
        CASE WHEN s.speaker = ? THEN 0 ELSE 1 END,
        s.sermon_date DESC
    LIMIT 8
");
$suggestStmt->execute([$sermon['id'], $sermon['series_id'] ?? 0, $sermon['speaker'] ?? '']);
$suggestedSermons = $suggestStmt->fetchAll();

// Get YouTube channel URL from site config (loaded from database)
$youtubeChannelUrl = $site['social']['youtube'] ?? 'https://www.youtube.com/@alivechurch';

// Get current user for comments
$currentUser = $auth->user();

// Get approved comments with user data
$commentStmt = $pdo->prepare("SELECT c.*, u.full_name as user_full_name, u.username as user_username, u.avatar as user_avatar, u.avatar_color as user_avatar_color
                              FROM sermon_comments c
                              LEFT JOIN users u ON c.user_id = u.id
                              WHERE c.sermon_id = ? AND c.status = 'approved' AND c.parent_id IS NULL
                              ORDER BY c.created_at ASC");
$commentStmt->execute([$sermon['id']]);
$comments = $commentStmt->fetchAll();

// Get comment replies with user data
$replyStmt = $pdo->prepare("SELECT c.*, u.full_name as user_full_name, u.username as user_username, u.avatar as user_avatar, u.avatar_color as user_avatar_color
                            FROM sermon_comments c
                            LEFT JOIN users u ON c.user_id = u.id
                            WHERE c.sermon_id = ? AND c.status = 'approved' AND c.parent_id IS NOT NULL
                            ORDER BY c.created_at ASC");
$replyStmt->execute([$sermon['id']]);
$replies = $replyStmt->fetchAll();

// Group replies by parent
$repliesByParent = [];
foreach ($replies as $reply) {
    $repliesByParent[$reply['parent_id']][] = $reply;
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $commentContent = trim($_POST['content'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

    if (empty($commentContent)) {
        $_SESSION['comment_error'] = 'Please enter a comment.';
    } else {
        // Check profanity
        $profanityCheck = checkProfanity($commentContent, $pdo);

        if ($currentUser) {
            // Logged-in user: use their data
            $userId = $currentUser['id'];
            $authorName = $currentUser['full_name'] ?? $currentUser['username'];
            $authorEmail = $currentUser['email'];

            // Auto-approve if no profanity, otherwise send for review
            $status = $profanityCheck['has_profanity'] ? 'pending' : 'approved';

            $insertStmt = $pdo->prepare("INSERT INTO sermon_comments (sermon_id, user_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$sermon['id'], $userId, $parentId, $authorName, $authorEmail, $commentContent, $status]);

            $_SESSION['comment_success'] = ($status === 'approved') ? 'auto' : 'pending';
        } else {
            // Anonymous user: require name and email
            $authorName = trim($_POST['author_name'] ?? '');
            $authorEmail = trim($_POST['author_email'] ?? '');

            if (empty($authorName) || empty($authorEmail)) {
                $_SESSION['comment_error'] = 'Please fill in all required fields.';
            } elseif (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['comment_error'] = 'Please enter a valid email address.';
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO sermon_comments (sermon_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $insertStmt->execute([$sermon['id'], $parentId, $authorName, $authorEmail, $commentContent]);
                $_SESSION['comment_success'] = 'pending';
            }
        }
    }

    // Redirect to prevent form resubmission and scroll to comments
    header('Location: /sermon/' . $slug . '#comments');
    exit;
}

// Get flash messages from session
$commentSuccess = $_SESSION['comment_success'] ?? false;
$commentError = $_SESSION['comment_error'] ?? '';
$commentAutoApproved = ($commentSuccess === 'auto');
unset($_SESSION['comment_success'], $_SESSION['comment_error']);

$page_title = $sermon['title'] . ' | ' . $site['name'];
$page_description = $sermon['description'] ? substr(strip_tags($sermon['description']), 0, 155) : 'Watch this sermon from ' . $site['name'];

// Share URLs
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$shareTitle = urlencode($sermon['title'] . ' - ' . $site['name']);
$shareUrl = urlencode($currentUrl);

// Social Media Meta Tags (Open Graph / Twitter Card)
$og_type = 'video.other';
$og_url = $currentUrl;
$og_title = $sermon['title'] . ' | ' . $site['name'];
$og_description = $page_description;

// Set thumbnail image for social sharing
if (!empty($sermon['thumbnail_url'])) {
    $og_image = $sermon['thumbnail_url'];
} elseif (!empty($sermon['youtube_video_id'])) {
    $og_image = 'https://img.youtube.com/vi/' . $sermon['youtube_video_id'] . '/maxresdefault.jpg';
} elseif (!empty($sermon['video_id'])) {
    $og_image = 'https://img.youtube.com/vi/' . $sermon['video_id'] . '/maxresdefault.jpg';
}

// Set video embed URL for rich sharing
if (!empty($sermon['youtube_video_id'])) {
    $og_video = 'https://www.youtube.com/embed/' . $sermon['youtube_video_id'];
} elseif (!empty($sermon['video_id'])) {
    $og_video = 'https://www.youtube.com/embed/' . $sermon['video_id'];
}

$twitter_card = 'player';

include __DIR__ . '/includes/header.php';
?>

<article class="sermon-page">
    <!-- Video Player Section -->
    <section class="sermon-player-section">
        <div class="player-container">
            <?php if ($sermon['youtube_video_id'] || $sermon['video_id']): ?>
                <div class="video-player">
                    <iframe
                        id="sermon-video"
                        src="https://www.youtube.com/embed/<?= htmlspecialchars($sermon['youtube_video_id'] ?: $sermon['video_id']); ?>?rel=0&modestbranding=1"
                        title="<?= htmlspecialchars($sermon['title']); ?>"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen>
                    </iframe>
                </div>
            <?php elseif ($sermon['thumbnail_url']): ?>
                <div class="sermon-thumbnail-full">
                    <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" alt="<?= htmlspecialchars($sermon['title']); ?>">
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Main Content + Sidebar -->
    <section class="sermon-content-section">
        <div class="container-wide">
            <div class="sermon-layout">
                <!-- Main Content -->
                <main class="sermon-main">
                    <!-- Header -->
                    <header class="sermon-header">
                        <?php if ($series): ?>
                            <a href="/sermons/series/<?= htmlspecialchars($series['slug']); ?>" class="series-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                </svg>
                                <?= htmlspecialchars($series['title']); ?>
                            </a>
                        <?php endif; ?>

                        <h1><?= htmlspecialchars($sermon['title']); ?></h1>

                        <div class="sermon-meta-row">
                            <div class="meta-left">
                                <?php if ($speaker): ?>
                                    <a href="/author/<?= htmlspecialchars($speaker['username']); ?>" class="speaker-link">
                                        <?php if ($speaker['profile_image']): ?>
                                            <img src="<?= htmlspecialchars($speaker['profile_image']); ?>" alt="" class="speaker-avatar">
                                        <?php else: ?>
                                            <div class="speaker-avatar-placeholder">
                                                <?= strtoupper(substr($speaker['full_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($speaker['full_name']); ?></span>
                                    </a>
                                <?php elseif ($sermon['speaker']): ?>
                                    <span class="speaker-name">
                                        <div class="speaker-avatar-placeholder">
                                            <?= strtoupper(substr($sermon['speaker'], 0, 1)); ?>
                                        </div>
                                        <?= htmlspecialchars($sermon['speaker']); ?>
                                    </span>
                                <?php endif; ?>

                                <div class="meta-details">
                                    <?php if ($sermon['sermon_date']): ?>
                                        <span class="date"><?= date('F j, Y', strtotime($sermon['sermon_date'])); ?></span>
                                    <?php endif; ?>
                                    <?php if ($sermon['length']): ?>
                                        <span class="duration"><?= htmlspecialchars($sermon['length']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </header>

                    <!-- Action Bar -->
                    <div class="sermon-actions">
                        <div class="action-group">
                            <?php if ($sermon['transcript']): ?>
                                <button class="action-btn" onclick="toggleTranscript()" id="transcript-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                        <polyline points="10 9 9 9 8 9"/>
                                    </svg>
                                    <span>Transcript</span>
                                </button>
                            <?php endif; ?>

                            <?php if ($sermon['audio_url']): ?>
                                <a href="<?= htmlspecialchars($sermon['audio_url']); ?>" class="action-btn" download>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    <span>Download Audio</span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="action-group share-group">
                            <button class="action-btn share-toggle" onclick="toggleShareMenu()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="18" cy="5" r="3"/>
                                    <circle cx="6" cy="12" r="3"/>
                                    <circle cx="18" cy="19" r="3"/>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                                </svg>
                                <span>Share</span>
                            </button>
                            <div class="share-menu" id="share-menu">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl; ?>" target="_blank" rel="noopener" class="share-option">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                    </svg>
                                    Facebook
                                </a>
                                <a href="https://twitter.com/intent/tweet?text=<?= $shareTitle; ?>&url=<?= $shareUrl; ?>" target="_blank" rel="noopener" class="share-option">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                    </svg>
                                    X (Twitter)
                                </a>
                                <a href="https://api.whatsapp.com/send?text=<?= $shareTitle; ?>%20<?= $shareUrl; ?>" target="_blank" rel="noopener" class="share-option">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    WhatsApp
                                </a>
                                <a href="mailto:?subject=<?= $shareTitle; ?>&body=Check%20out%20this%20sermon:%20<?= $shareUrl; ?>" class="share-option">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                    Email
                                </a>
                                <button class="share-option" onclick="copyLink()">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <span id="copy-text">Copy Link</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if ($sermon['description']): ?>
                        <div class="sermon-description">
                            <h2>About This Message</h2>
                            <div class="description-content">
                                <?= nl2br(htmlspecialchars($sermon['description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Audio Player -->
                    <?php if ($sermon['audio_url']): ?>
                        <div class="sermon-audio">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                                </svg>
                                Listen to Audio
                            </h3>
                            <audio controls preload="metadata">
                                <source src="<?= htmlspecialchars($sermon['audio_url']); ?>" type="audio/mpeg">
                                Your browser does not support the audio element.
                            </audio>
                        </div>
                    <?php endif; ?>

                    <!-- Transcript (visible for SEO) -->
                    <?php if ($sermon['transcript']): ?>
                        <div class="sermon-transcript" id="sermon-transcript">
                            <div class="transcript-header">
                                <h2>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                    Transcript
                                </h2>
                                <button class="transcript-toggle-btn" onclick="toggleTranscript()" id="transcript-toggle-btn">
                                    <span>Collapse</span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="18 15 12 9 6 15"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="transcript-content" id="transcript-content">
                                <?= nl2br(htmlspecialchars($sermon['transcript'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Related Bible Studies -->
                    <?php if (!empty($relatedStudies)): ?>
                        <div class="sermon-related-studies">
                            <h2>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                                Related Bible Studies
                            </h2>
                            <div class="studies-grid">
                                <?php foreach ($relatedStudies as $study): ?>
                                    <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" class="study-card">
                                        <span class="study-ref"><?= htmlspecialchars($study['book_name']); ?> <?= $study['chapter']; ?></span>
                                        <span class="study-title"><?= htmlspecialchars($study['title'] ?? $study['book_name'] . ' ' . $study['chapter']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Series Navigation -->
                    <?php if ($series && count($seriesSermons) > 1): ?>
                        <div class="series-navigation">
                            <h2>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                </svg>
                                More from <?= htmlspecialchars($series['title']); ?>
                            </h2>
                            <div class="series-list">
                                <?php foreach ($seriesSermons as $i => $seriesSermon): ?>
                                    <a href="/sermon/<?= htmlspecialchars($seriesSermon['slug']); ?>"
                                       class="series-item <?= $seriesSermon['id'] === $sermon['id'] ? 'current' : ''; ?>">
                                        <span class="item-number"><?= $i + 1; ?></span>
                                        <div class="item-info">
                                            <span class="item-title"><?= htmlspecialchars($seriesSermon['title']); ?></span>
                                            <span class="item-meta">
                                                <?= $seriesSermon['speaker'] ? htmlspecialchars($seriesSermon['speaker']) : ''; ?>
                                                <?= $seriesSermon['sermon_date'] ? ' • ' . date('M j', strtotime($seriesSermon['sermon_date'])) : ''; ?>
                                            </span>
                                        </div>
                                        <?php if ($seriesSermon['id'] === $sermon['id']): ?>
                                            <span class="now-playing-badge">Now Playing</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <a href="/sermons/series/<?= htmlspecialchars($series['slug']); ?>" class="view-series-link">
                                View Full Series
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </main>

                <!-- Sidebar -->
                <aside class="sermon-sidebar">
                    <!-- Subscribe CTA -->
                    <div class="sidebar-card subscribe-card">
                        <div class="subscribe-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
                            </svg>
                        </div>
                        <h3>Subscribe on YouTube</h3>
                        <p>Never miss a message. Subscribe to get notified of new sermons.</p>
                        <a href="<?= htmlspecialchars($youtubeChannelUrl); ?>?sub_confirmation=1" target="_blank" rel="noopener" class="subscribe-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
                            </svg>
                            Subscribe
                        </a>
                    </div>

                    <!-- Speaker Card -->
                    <?php if ($speaker || $sermon['speaker']): ?>
                        <div class="sidebar-card speaker-card">
                            <?php if ($speaker): ?>
                                <a href="/author/<?= htmlspecialchars($speaker['username']); ?>" class="speaker-profile">
                                    <?php if ($speaker['profile_image']): ?>
                                        <img src="<?= htmlspecialchars($speaker['profile_image']); ?>" alt="" class="speaker-img">
                                    <?php else: ?>
                                        <div class="speaker-img-placeholder">
                                            <?= strtoupper(substr($speaker['full_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="speaker-details">
                                        <h4><?= htmlspecialchars($speaker['full_name']); ?></h4>
                                        <?php if ($speaker['bio']): ?>
                                            <p><?= htmlspecialchars(substr($speaker['bio'], 0, 80)); ?><?= strlen($speaker['bio']) > 80 ? '...' : ''; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <a href="/sermons?speaker=<?= urlencode($speaker['full_name']); ?>" class="more-from-speaker">
                                    More from <?= htmlspecialchars($speaker['full_name']); ?>
                                </a>
                            <?php else: ?>
                                <div class="speaker-profile">
                                    <div class="speaker-img-placeholder">
                                        <?= strtoupper(substr($sermon['speaker'], 0, 1)); ?>
                                    </div>
                                    <div class="speaker-details">
                                        <h4><?= htmlspecialchars($sermon['speaker']); ?></h4>
                                    </div>
                                </div>
                                <a href="/sermons?speaker=<?= urlencode($sermon['speaker']); ?>" class="more-from-speaker">
                                    More from <?= htmlspecialchars($sermon['speaker']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Prev/Next Navigation -->
                    <?php if ($prevSermon || $nextSermon): ?>
                        <div class="sidebar-card nav-card">
                            <?php if ($prevSermon): ?>
                                <a href="/sermon/<?= htmlspecialchars($prevSermon['slug']); ?>" class="nav-link prev">
                                    <span class="nav-label">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M15 18l-6-6 6-6"/>
                                        </svg>
                                        Previous
                                    </span>
                                    <span class="nav-title"><?= htmlspecialchars($prevSermon['title']); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($nextSermon): ?>
                                <a href="/sermon/<?= htmlspecialchars($nextSermon['slug']); ?>" class="nav-link next">
                                    <span class="nav-label">
                                        Next
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 18l6-6-6-6"/>
                                        </svg>
                                    </span>
                                    <span class="nav-title"><?= htmlspecialchars($nextSermon['title']); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Links -->
                    <div class="sidebar-card quick-links">
                        <h4>Quick Links</h4>
                        <a href="/sermons">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                            </svg>
                            Browse All Sermons
                        </a>
                        <a href="/bible-study">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            Bible Study Library
                        </a>
                        <a href="/visit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            Plan a Visit
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <!-- Comments Section -->
    <section id="comments" class="sermon-comments">
        <div class="container-wide">
            <h2>Comments (<?= count($comments); ?>)</h2>

            <?php if ($commentSuccess): ?>
                <?php if ($commentAutoApproved): ?>
                    <div class="alert alert-success">Your comment has been posted!</div>
                <?php else: ?>
                    <div class="alert alert-success">Thank you for your comment! It will appear after moderation.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($commentError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($commentError); ?></div>
            <?php endif; ?>

            <?php
            $maxLength = 300; // Character limit before truncation
            ?>
            <?php if (!empty($comments)): ?>
                <div class="comments-list">
                    <?php foreach ($comments as $comment): ?>
                        <?php
                        $commentText = htmlspecialchars($comment['content']);
                        $isLong = strlen($comment['content']) > $maxLength;
                        $displayName = $comment['user_id'] ? ($comment['user_full_name'] ?? $comment['author_name']) : $comment['author_name'];
                        ?>
                        <div class="comment" id="comment-<?= $comment['id']; ?>">
                            <div class="comment-header">
                                <?php if ($comment['user_id'] && $comment['user_avatar']): ?>
                                    <img src="<?= htmlspecialchars($comment['user_avatar']); ?>" alt="" class="comment-avatar">
                                <?php elseif ($comment['user_id']): ?>
                                    <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($comment['user_avatar_color'] ?? '#4b2679'); ?>">
                                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="comment-avatar comment-avatar-initials">
                                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($comment['user_id'] && $comment['user_username']): ?>
                                    <a href="/user/<?= htmlspecialchars($comment['user_username']); ?>" class="comment-author-link"><?= htmlspecialchars($displayName); ?></a>
                                <?php else: ?>
                                    <strong class="comment-author"><?= htmlspecialchars($displayName); ?></strong>
                                <?php endif; ?>
                                <span class="comment-date"><?= date('M j, Y \a\t g:ia', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-content <?= $isLong ? 'truncated' : ''; ?>" id="comment-content-<?= $comment['id']; ?>">
                                <?= nl2br($commentText); ?>
                            </div>
                            <?php if ($isLong): ?>
                                <button class="read-more-btn" onclick="toggleComment(<?= $comment['id']; ?>)">Read more</button>
                            <?php endif; ?>
                            <button class="reply-btn" onclick="showReplyForm(<?= $comment['id']; ?>)">Reply</button>

                            <!-- Reply Form (hidden by default) -->
                            <div class="reply-form-container" id="reply-form-<?= $comment['id']; ?>" style="display: none;">
                                <div class="form-message reply-form-message" style="display: none;"></div>
                                <form method="POST" class="comment-form reply-comment-form" data-comment-type="sermon" data-content-id="<?= $sermon['id']; ?>" data-parent-id="<?= $comment['id']; ?>">
                                    <?php if ($currentUser): ?>
                                        <div class="comment-form-user">
                                            <?php if ($currentUser['avatar']): ?>
                                                <img src="<?= htmlspecialchars($currentUser['avatar']); ?>" alt="" class="comment-avatar">
                                            <?php else: ?>
                                                <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#4b2679'); ?>">
                                                    <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="comment-form-username"><?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-row">
                                            <input type="text" name="author_name" placeholder="Your Name *" required>
                                            <input type="email" name="author_email" placeholder="Your Email *" required>
                                        </div>
                                    <?php endif; ?>
                                    <textarea name="content" placeholder="Your reply..." required></textarea>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <span class="btn-text">Post Reply</span>
                                            <span class="btn-spinner" style="display: none;">
                                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                                    </path>
                                                </svg>
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-outline" onclick="hideReplyForm(<?= $comment['id']; ?>)">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Replies -->
                            <?php if (isset($repliesByParent[$comment['id']])): ?>
                                <div class="comment-replies">
                                    <?php foreach ($repliesByParent[$comment['id']] as $reply): ?>
                                        <?php
                                        $replyText = htmlspecialchars($reply['content']);
                                        $isReplyLong = strlen($reply['content']) > $maxLength;
                                        $replyDisplayName = $reply['user_id'] ? ($reply['user_full_name'] ?? $reply['author_name']) : $reply['author_name'];
                                        ?>
                                        <div class="comment reply">
                                            <div class="comment-header">
                                                <?php if ($reply['user_id'] && $reply['user_avatar']): ?>
                                                    <img src="<?= htmlspecialchars($reply['user_avatar']); ?>" alt="" class="comment-avatar">
                                                <?php elseif ($reply['user_id']): ?>
                                                    <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($reply['user_avatar_color'] ?? '#4b2679'); ?>">
                                                        <?= strtoupper(substr($replyDisplayName, 0, 1)); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="comment-avatar comment-avatar-initials">
                                                        <?= strtoupper(substr($replyDisplayName, 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($reply['user_id'] && $reply['user_username']): ?>
                                                    <a href="/user/<?= htmlspecialchars($reply['user_username']); ?>" class="comment-author-link"><?= htmlspecialchars($replyDisplayName); ?></a>
                                                <?php else: ?>
                                                    <strong class="comment-author"><?= htmlspecialchars($replyDisplayName); ?></strong>
                                                <?php endif; ?>
                                                <span class="comment-date"><?= date('M j, Y \a\t g:ia', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                            <div class="comment-content <?= $isReplyLong ? 'truncated' : ''; ?>" id="comment-content-<?= $reply['id']; ?>">
                                                <?= nl2br($replyText); ?>
                                            </div>
                                            <?php if ($isReplyLong): ?>
                                                <button class="read-more-btn" onclick="toggleComment(<?= $reply['id']; ?>)">Read more</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Comment Form -->
            <div class="comment-form-section">
                <h3>Leave a Comment</h3>
                <div class="form-message comment-form-message" id="main-comment-message" style="display: none;"></div>
                <?php if ($currentUser): ?>
                    <form method="POST" class="comment-form" id="main-comment-form" data-comment-type="sermon" data-content-id="<?= $sermon['id']; ?>">
                        <div class="comment-form-user">
                            <?php if ($currentUser['avatar']): ?>
                                <img src="<?= htmlspecialchars($currentUser['avatar']); ?>" alt="" class="comment-avatar">
                            <?php else: ?>
                                <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#4b2679'); ?>">
                                    <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <span class="comment-form-username"><?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']); ?></span>
                        </div>
                        <textarea name="content" placeholder="Share your thoughts on this message..." rows="4" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Post Comment</span>
                            <span class="btn-spinner" style="display: none;">
                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="comment-form" id="main-comment-form" data-comment-type="sermon" data-content-id="<?= $sermon['id']; ?>">
                        <div class="form-row">
                            <input type="text" name="author_name" placeholder="Your Name *" required>
                            <input type="email" name="author_email" placeholder="Your Email *" required>
                        </div>
                        <textarea name="content" placeholder="Share your thoughts on this message..." rows="4" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Post Comment</span>
                            <span class="btn-spinner" style="display: none;">
                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </form>
                    <p class="comment-login-prompt">Have an account? <a href="/login?redirect=<?= urlencode('/sermon/' . $sermon['slug']); ?>">Log in</a> to post instantly.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Suggested Sermons -->
    <?php if (!empty($suggestedSermons)): ?>
        <section class="suggested-sermons">
            <div class="container-wide">
                <h2>You Might Also Like</h2>
                <div class="suggested-grid">
                    <?php foreach ($suggestedSermons as $suggested): ?>
                        <?php $suggestedUrl = $suggested['slug'] ? '/sermon/' . htmlspecialchars($suggested['slug']) : '#'; ?>
                        <a href="<?= $suggestedUrl; ?>" class="suggested-card">
                            <div class="suggested-thumb">
                                <?php if (!empty($suggested['thumbnail_url'])): ?>
                                    <img src="<?= htmlspecialchars($suggested['thumbnail_url']); ?>" alt="" loading="lazy">
                                <?php elseif (!empty($suggested['youtube_video_id'])): ?>
                                    <img src="https://img.youtube.com/vi/<?= htmlspecialchars($suggested['youtube_video_id']); ?>/mqdefault.jpg" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="thumb-placeholder"></div>
                                <?php endif; ?>
                                <div class="play-overlay">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </div>
                                <?php if (!empty($suggested['length'])): ?>
                                    <span class="duration"><?= htmlspecialchars($suggested['length']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="suggested-info">
                                <h3><?= htmlspecialchars($suggested['title']); ?></h3>
                                <p class="meta">
                                    <?= $suggested['speaker'] ? htmlspecialchars($suggested['speaker']) : ''; ?>
                                    <?= $suggested['sermon_date'] ? ' • ' . date('M j, Y', strtotime($suggested['sermon_date'])) : ''; ?>
                                </p>
                                <?php if (!empty($suggested['series_title'])): ?>
                                    <span class="series-tag"><?= htmlspecialchars($suggested['series_title']); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</article>


<script>
// Toggle Transcript
function toggleTranscript() {
    const transcript = document.getElementById('sermon-transcript');
    const toggleBtn = document.getElementById('transcript-toggle-btn');
    const actionBtn = document.getElementById('transcript-btn');

    if (transcript) {
        transcript.classList.toggle('collapsed');
        const isCollapsed = transcript.classList.contains('collapsed');

        if (toggleBtn) {
            toggleBtn.querySelector('span').textContent = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (actionBtn) {
            actionBtn.querySelector('span').textContent = isCollapsed ? 'Show Transcript' : 'Hide Transcript';
        }

        if (!isCollapsed) {
            transcript.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

// Toggle Share Menu
function toggleShareMenu() {
    const menu = document.getElementById('share-menu');
    menu.classList.toggle('show');
}

// Close share menu when clicking outside
document.addEventListener('click', function(e) {
    const shareGroup = document.querySelector('.share-group');
    const menu = document.getElementById('share-menu');

    if (shareGroup && menu && !shareGroup.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// Copy Link
function copyLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        const copyText = document.getElementById('copy-text');
        const originalText = copyText.textContent;
        copyText.textContent = 'Copied!';
        setTimeout(() => {
            copyText.textContent = originalText;
        }, 2000);
    }).catch(err => {
        // Fallback for older browsers
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);

        const copyText = document.getElementById('copy-text');
        copyText.textContent = 'Copied!';
        setTimeout(() => {
            copyText.textContent = 'Copy Link';
        }, 2000);
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // T for Transcript
    if (e.key === 't' && !e.ctrlKey && !e.metaKey && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
        toggleTranscript();
    }
});

// Comment Functions
function showReplyForm(commentId) {
    document.getElementById('reply-form-' + commentId).style.display = 'block';
}

function hideReplyForm(commentId) {
    document.getElementById('reply-form-' + commentId).style.display = 'none';
}

function toggleComment(commentId) {
    const content = document.getElementById('comment-content-' + commentId);
    const btn = content.nextElementSibling;
    if (content.classList.contains('truncated')) {
        content.classList.remove('truncated');
        btn.textContent = 'Show less';
    } else {
        content.classList.add('truncated');
        btn.textContent = 'Read more';
    }
}

// AJAX Comment Submission
async function submitComment(form, messageEl) {
    const btn = form.querySelector('button[type="submit"]');
    const btnText = btn.querySelector('.btn-text');
    const btnSpinner = btn.querySelector('.btn-spinner');

    // Show loading state
    btn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnSpinner) btnSpinner.style.display = 'inline-block';
    messageEl.style.display = 'none';

    const formData = new FormData(form);
    formData.append('comment_type', form.dataset.commentType);
    formData.append('content_id', form.dataset.contentId);
    if (form.dataset.parentId) {
        formData.append('parent_id', form.dataset.parentId);
    }

    try {
        const response = await fetch('/api/comments/submit', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            messageEl.className = 'form-message success';
            messageEl.textContent = data.message;
            messageEl.style.display = 'block';
            form.reset();

            // If comment was auto-approved, add it to the page
            if (data.approved && data.comment) {
                if (form.dataset.parentId) {
                    // Reply - find the parent comment's replies section
                    const parentComment = document.getElementById('reply-form-' + form.dataset.parentId).closest('.comment');
                    let repliesContainer = parentComment.querySelector('.comment-replies');
                    if (!repliesContainer) {
                        repliesContainer = document.createElement('div');
                        repliesContainer.className = 'comment-replies';
                        parentComment.appendChild(repliesContainer);
                    }
                    repliesContainer.insertAdjacentHTML('beforeend', data.comment);
                    hideReplyForm(form.dataset.parentId);
                } else {
                    // Main comment - add to comments list
                    const commentsList = document.querySelector('.comments-list');
                    if (commentsList) {
                        commentsList.insertAdjacentHTML('beforeend', data.comment);
                    } else {
                        const section = document.querySelector('.comment-form-section');
                        section.insertAdjacentHTML('beforebegin', '<div class="comments-list">' + data.comment + '</div>');
                    }
                }

                // Highlight new comment briefly
                setTimeout(() => {
                    const newComment = document.querySelector('.new-comment');
                    if (newComment) {
                        newComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        newComment.classList.add('highlight');
                        setTimeout(() => newComment.classList.remove('new-comment', 'highlight'), 2000);
                    }
                }, 100);
            }
        } else {
            messageEl.className = 'form-message error';
            messageEl.textContent = data.error || 'Failed to submit comment.';
            messageEl.style.display = 'block';
        }
    } catch (error) {
        messageEl.className = 'form-message error';
        messageEl.textContent = 'Something went wrong. Please try again.';
        messageEl.style.display = 'block';
    }

    // Reset button
    btn.disabled = false;
    if (btnText) btnText.style.display = 'inline';
    if (btnSpinner) btnSpinner.style.display = 'none';
}

// Main comment form
const mainForm = document.getElementById('main-comment-form');
if (mainForm) {
    mainForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitComment(this, document.getElementById('main-comment-message'));
    });
}

// Reply forms
document.querySelectorAll('.reply-comment-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const messageEl = this.closest('.reply-form-container').querySelector('.reply-form-message');
        submitComment(this, messageEl);
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
