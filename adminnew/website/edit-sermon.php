<?php
/**
 * Edit Sermon - New Admin
 */
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/SermonManager.php';

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);

$success = '';
$error = '';

// Get sermon ID from URL
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$sermon = null;

// Load existing sermon if editing
if ($id) {
    $sermon = $sermonManager->getSermon($id);
    if (!$sermon) {
        header('Location: /adminnew/sermons');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'series_id' => !empty($_POST['series_id']) ? (int)$_POST['series_id'] : null,
            'title' => $_POST['title'],
            'slug' => $_POST['slug'] ?: null,
            'speaker' => $_POST['speaker'] ?: null,
            'speaker_user_id' => !empty($_POST['speaker_user_id']) ? (int)$_POST['speaker_user_id'] : null,
            'sermon_date' => $_POST['sermon_date'] ?: null,
            'description' => $_POST['description'] ?: null,
            'transcript' => $_POST['transcript'] ?: null,
            'youtube_video_id' => $_POST['youtube_video_id'] ?: null,
            'video_id' => $_POST['youtube_video_id'] ?: null,
            'thumbnail_url' => $_POST['thumbnail_url'] ?: null,
            'audio_url' => $_POST['audio_url'] ?: null,
            'length' => $_POST['length'] ?: null,
            'duration_seconds' => !empty($_POST['duration_seconds']) ? (int)$_POST['duration_seconds'] : null,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'featured_location' => $_POST['featured_location'] ?: null,
            'featured_order' => (int)($_POST['featured_order'] ?? 0),
            'visible' => isset($_POST['visible']) ? 1 : 0,
        ];

        if (!empty($_POST['youtube_video_id']) && !empty($_POST['youtube_fetched'])) {
            $data['youtube_fetched_at'] = date('Y-m-d H:i:s');
        }

        try {
            if ($id) {
                $sermonManager->updateSermon($id, $data);
                $success = 'Sermon updated successfully';
            } else {
                $id = $sermonManager->createSermon($data);
                $success = 'Sermon created successfully';
            }
            $sermon = $sermonManager->getSermon($id);

            if (!empty($data['transcript'])) {
                $sermonManager->saveScriptureReferences($id);
            }
        } catch (Exception $e) {
            $error = 'Error saving sermon: ' . $e->getMessage();
        }
    }
}

// Get all series for dropdown
$allSeries = $sermonManager->getSeriesList(false);
$speakers = $sermonManager->getSpeakers();

$page_title = $id ? 'Edit Sermon' : 'Add Sermon';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title"><?= $id ? 'Edit Sermon' : 'New Sermon'; ?></h1>
    </div>
    <a href="/adminnew/sermons" class="admin-btn admin-btn-secondary">&larr; Back to Sermons</a>
</div>

<form method="post" id="sermon-form">
    <?= csrf_field(); ?>
    <input type="hidden" name="youtube_fetched" id="youtube_fetched" value="">
    <input type="hidden" name="duration_seconds" id="duration_seconds" value="<?= $sermon['duration_seconds'] ?? ''; ?>">

    <div class="sermon-editor">
        <!-- Main Content -->
        <div class="main-column">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>YouTube Video</h3>
                </div>
                <div class="admin-card-body">
                    <div class="youtube-fetch-section">
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="youtube_input" id="youtube_input" class="admin-form-input"
                                   placeholder="Paste YouTube URL or video ID"
                                   value="<?= htmlspecialchars($sermon['youtube_video_id'] ?? ''); ?>">
                            <button type="button" id="fetch-youtube-btn" class="admin-btn admin-btn-secondary">
                                Fetch from YouTube
                            </button>
                        </div>
                        <input type="hidden" name="youtube_video_id" id="youtube_video_id"
                               value="<?= htmlspecialchars($sermon['youtube_video_id'] ?? ''); ?>">
                        <div id="youtube-status" style="margin-top: 0.5rem; font-size: 0.875rem;"></div>

                        <?php if (!empty($sermon['youtube_video_id'])): ?>
                            <div class="video-preview" id="video-preview">
                                <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($sermon['youtube_video_id']); ?>"
                                        allowfullscreen></iframe>
                            </div>
                        <?php else: ?>
                            <div class="video-preview" id="video-preview" style="display: none;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Sermon Details</h3>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Title *</label>
                        <input type="text" name="title" id="title" class="admin-form-input" required
                               value="<?= htmlspecialchars($sermon['title'] ?? ''); ?>">
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">URL Slug</label>
                        <input type="text" name="slug" id="slug" class="admin-form-input"
                               value="<?= htmlspecialchars($sermon['slug'] ?? ''); ?>"
                               placeholder="auto-generated-from-title">
                    </div>

                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Series</label>
                            <select name="series_id" class="admin-form-select">
                                <option value="">No Series</option>
                                <?php foreach ($allSeries as $series): ?>
                                    <option value="<?= $series['id']; ?>" <?= ($sermon['series_id'] ?? '') == $series['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($series['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Sermon Date</label>
                            <input type="date" name="sermon_date" class="admin-form-input"
                                   value="<?= $sermon['sermon_date'] ?? date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Speaker (from Users)</label>
                            <select name="speaker_user_id" class="admin-form-select">
                                <option value="">Select speaker...</option>
                                <?php foreach ($speakers['users'] as $user): ?>
                                    <option value="<?= $user['id']; ?>" <?= ($sermon['speaker_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Speaker Name (Override)</label>
                            <input type="text" name="speaker" class="admin-form-input"
                                   value="<?= htmlspecialchars($sermon['speaker'] ?? ''); ?>"
                                   placeholder="Only use if speaker isn't in the system">
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Description</label>
                        <textarea name="description" class="admin-form-textarea" rows="4" id="description"><?= htmlspecialchars($sermon['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Transcript</label>
                        <textarea name="transcript" class="admin-form-textarea transcript-textarea" rows="10" id="transcript"
                                  placeholder="Paste or fetch transcript from YouTube..."><?= htmlspecialchars($sermon['transcript'] ?? ''); ?></textarea>
                    </div>

                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Thumbnail URL</label>
                            <input type="text" name="thumbnail_url" id="thumbnail_url" class="admin-form-input"
                                   value="<?= htmlspecialchars($sermon['thumbnail_url'] ?? ''); ?>">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Duration</label>
                            <input type="text" name="length" id="length" class="admin-form-input"
                                   value="<?= htmlspecialchars($sermon['length'] ?? ''); ?>" placeholder="45 mins">
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Audio URL (Optional)</label>
                        <input type="text" name="audio_url" class="admin-form-input"
                               value="<?= htmlspecialchars($sermon['audio_url'] ?? ''); ?>"
                               placeholder="https://example.com/sermon.mp3">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar-column">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Publish</h3>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="visible" value="1"
                                   <?= ($sermon['visible'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Visible on website</span>
                        </label>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                        <?= $id ? 'Update Sermon' : 'Create Sermon'; ?>
                    </button>

                    <?php if ($id): ?>
                        <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" target="_blank" class="admin-btn admin-btn-secondary" style="width: 100%; margin-top: 0.5rem;">
                            View on Site
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Featured</h3>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="is_featured" id="is_featured" value="1"
                                   <?= ($sermon['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                            <span>Feature this sermon</span>
                        </label>
                    </div>

                    <div class="featured-options <?= ($sermon['is_featured'] ?? 0) ? 'active' : ''; ?>" id="featured-options">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Location</label>
                            <select name="featured_location" class="admin-form-select">
                                <option value="homepage" <?= ($sermon['featured_location'] ?? '') === 'homepage' ? 'selected' : ''; ?>>Homepage</option>
                                <option value="watch_page" <?= ($sermon['featured_location'] ?? '') === 'watch_page' ? 'selected' : ''; ?>>Watch Page</option>
                                <option value="sidebar" <?= ($sermon['featured_location'] ?? '') === 'sidebar' ? 'selected' : ''; ?>>Sidebar</option>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Display Order</label>
                            <input type="number" name="featured_order" class="admin-form-input" value="<?= $sermon['featured_order'] ?? 0; ?>" min="0">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style <?= csp_nonce(); ?>>
.sermon-editor { display: grid; grid-template-columns: 1fr 300px; gap: 1.5rem; align-items: start; }
@media (max-width: 1024px) { .sermon-editor { grid-template-columns: 1fr; } }
.admin-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 768px) { .admin-form-row { grid-template-columns: 1fr; } }
.youtube-fetch-section { background: var(--admin-bg); border-radius: var(--admin-radius); padding: 1rem; }
.video-preview { margin-top: 1rem; border-radius: var(--admin-radius); overflow: hidden; background: #000; }
.video-preview iframe { width: 100%; aspect-ratio: 16/9; border: 0; }
.transcript-textarea { font-family: monospace; font-size: 0.875rem; line-height: 1.6; }
.featured-options { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--admin-border); }
.featured-options.active { display: block; }
.admin-checkbox { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.admin-checkbox input { width: auto; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const youtubeInput = document.getElementById('youtube_input');
    const youtubeVideoIdField = document.getElementById('youtube_video_id');
    const fetchBtn = document.getElementById('fetch-youtube-btn');
    const youtubeStatus = document.getElementById('youtube-status');
    const videoPreview = document.getElementById('video-preview');
    const titleField = document.getElementById('title');
    const descriptionField = document.getElementById('description');
    const transcriptField = document.getElementById('transcript');
    const thumbnailField = document.getElementById('thumbnail_url');
    const lengthField = document.getElementById('length');
    const durationSecondsField = document.getElementById('duration_seconds');
    const youtubeFetchedField = document.getElementById('youtube_fetched');
    const isFeaturedCheckbox = document.getElementById('is_featured');
    const featuredOptions = document.getElementById('featured-options');

    isFeaturedCheckbox.addEventListener('change', function() {
        featuredOptions.classList.toggle('active', this.checked);
    });

    function extractVideoId(input) {
        const patterns = [
            /(?:youtube\.com\/(?:watch\?v=|embed\/|live\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
            /[?&]v=([a-zA-Z0-9_-]{11})/
        ];
        for (const pattern of patterns) {
            const match = input.match(pattern);
            if (match) return match[1];
        }
        if (/^[a-zA-Z0-9_-]{11}$/.test(input.trim())) return input.trim();
        return null;
    }

    youtubeInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            const videoId = extractVideoId(youtubeInput.value);
            if (videoId) {
                youtubeVideoIdField.value = videoId;
                fetchYouTubeData(videoId);
            }
        }, 100);
    });

    fetchBtn.addEventListener('click', function() {
        const videoId = extractVideoId(youtubeInput.value);
        if (videoId) {
            youtubeVideoIdField.value = videoId;
            fetchYouTubeData(videoId);
        } else {
            youtubeStatus.innerHTML = '<span style="color: var(--admin-danger);">Invalid YouTube URL or video ID</span>';
        }
    });

    function fetchYouTubeData(videoId) {
        youtubeStatus.innerHTML = '<span style="color: var(--admin-primary);">Fetching data from YouTube...</span>';
        fetchBtn.disabled = true;

        const formData = new FormData();
        formData.append('video_id', videoId);
        formData.append('csrf_token', csrfToken);
        formData.append('fetch_transcript', '1');

        fetch('/api/sermons/youtube-fetch.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            fetchBtn.disabled = false;

            if (data.success) {
                youtubeFetchedField.value = '1';
                videoPreview.innerHTML = '<iframe src="https://www.youtube.com/embed/' + videoId + '" allowfullscreen></iframe>';
                videoPreview.style.display = 'block';

                if (!titleField.value) titleField.value = data.data.title;
                if (!descriptionField.value) descriptionField.value = data.data.description || '';
                if (!thumbnailField.value) thumbnailField.value = data.data.thumbnail_url;
                if (data.data.duration_formatted && !lengthField.value) lengthField.value = data.data.duration_formatted;
                if (data.data.duration_seconds) durationSecondsField.value = data.data.duration_seconds;

                if (data.data.transcript && !transcriptField.value) {
                    transcriptField.value = data.data.transcript;
                    youtubeStatus.innerHTML = '<span style="color: var(--admin-success);">✓ Video data and transcript fetched</span>';
                } else {
                    youtubeStatus.innerHTML = '<span style="color: var(--admin-success);">✓ Video data fetched</span>';
                }
            } else {
                youtubeStatus.innerHTML = '<span style="color: var(--admin-danger);">Error: ' + (data.error || 'Unknown error') + '</span>';
            }
        })
        .catch(error => {
            fetchBtn.disabled = false;
            youtubeStatus.innerHTML = '<span style="color: var(--admin-danger);">Network error. Please try again.</span>';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
