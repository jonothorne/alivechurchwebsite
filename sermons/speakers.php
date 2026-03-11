<?php
/**
 * Sermon Speakers Page
 * Browse sermons by speaker
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get all speakers with sermon counts
$speakersStmt = $pdo->query("
    SELECT s.speaker,
           s.speaker_user_id,
           u.full_name as user_full_name,
           u.avatar as user_avatar,
           u.avatar_color as user_avatar_color,
           COUNT(s.id) as sermon_count,
           MAX(s.sermon_date) as last_sermon
    FROM sermons s
    LEFT JOIN users u ON s.speaker_user_id = u.id
    WHERE s.visible = 1
      AND s.speaker IS NOT NULL
      AND s.speaker != ''
    GROUP BY s.speaker, s.speaker_user_id
    ORDER BY sermon_count DESC, s.speaker
");
$speakers = $speakersStmt->fetchAll();

// Check if viewing a specific speaker
$selectedSpeaker = $_GET['speaker'] ?? '';
$sermons = [];

if ($selectedSpeaker) {
    $sermonsStmt = $pdo->prepare("
        SELECT s.*, ss.title as series_title, ss.slug as series_slug
        FROM sermons s
        LEFT JOIN sermon_series ss ON s.series_id = ss.id
        WHERE s.visible = 1
          AND s.speaker = ?
        ORDER BY s.sermon_date DESC
    ");
    $sermonsStmt->execute([$selectedSpeaker]);
    $sermons = $sermonsStmt->fetchAll();
}

$page_title = $selectedSpeaker ? $selectedSpeaker . ' | Speakers | ' . $site['name'] : 'Speakers | ' . $site['name'];
$page_description = $selectedSpeaker
    ? 'Sermons by ' . $selectedSpeaker . ' at ' . $site['name']
    : 'Browse sermons by speaker at ' . $site['name'] . '.';

include __DIR__ . '/../includes/header.php';
?>

<section class="sermons-page">
    <div class="container">
        <?php if ($selectedSpeaker && !empty($sermons)): ?>
            <!-- Individual Speaker View -->
            <div class="page-header" style="margin-bottom: 2rem;">
                <a href="/sermons/speakers" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--color-primary); margin-bottom: 1rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    All Speakers
                </a>
                <h1><?= htmlspecialchars($selectedSpeaker); ?></h1>
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
            <!-- All Speakers Grid -->
            <div class="page-header" style="margin-bottom: 2rem;">
                <h1>Speakers</h1>
                <p style="color: var(--color-text-muted); margin-top: 0.5rem;">
                    <?= count($speakers); ?> speaker<?= count($speakers) != 1 ? 's' : ''; ?>
                </p>
            </div>

            <?php if (empty($speakers)): ?>
                <div class="empty-state">
                    <p>No speakers found.</p>
                </div>
            <?php else: ?>
                <div class="speakers-grid">
                    <?php foreach ($speakers as $speaker):
                        $displayName = $speaker['speaker'];
                        $avatarColor = $speaker['user_avatar_color'] ?? '#4b2679';
                    ?>
                        <a href="/sermons/speakers?speaker=<?= urlencode($speaker['speaker']); ?>" class="speaker-card">
                            <div class="speaker-avatar">
                                <?php if ($speaker['user_avatar']): ?>
                                    <img src="<?= htmlspecialchars($speaker['user_avatar']); ?>" alt="<?= htmlspecialchars($displayName); ?>">
                                <?php else: ?>
                                    <div class="avatar-placeholder" style="background: <?= htmlspecialchars($avatarColor); ?>;">
                                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="speaker-info">
                                <h3><?= htmlspecialchars($displayName); ?></h3>
                                <span class="sermon-count"><?= $speaker['sermon_count']; ?> sermon<?= $speaker['sermon_count'] != 1 ? 's' : ''; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
