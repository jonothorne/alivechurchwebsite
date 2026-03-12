<?php
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/SermonManager.php';

// Ensure admin is logged in
session_start();
if (empty($_SESSION['admin_user_id'])) {
    header('Location: /admin/login');
    exit;
}

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
        header('Location: /admin/sermons');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'save') {
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

            // Set youtube_fetched_at if we have YouTube data
            if (!empty($_POST['youtube_video_id']) && !empty($_POST['youtube_fetched'])) {
                $data['youtube_fetched_at'] = date('Y-m-d H:i:s');
            }

            try {
                if ($id) {
                    $sermonManager->updateSermon($id, $data);
                    log_activity($_SESSION['admin_user_id'], 'update', 'sermon', $id, 'Updated sermon: ' . $data['title']);
                    $success = 'Sermon updated successfully';
                } else {
                    $id = $sermonManager->createSermon($data);
                    log_activity($_SESSION['admin_user_id'], 'create', 'sermon', $id, 'Created sermon: ' . $data['title']);
                    $success = 'Sermon created successfully';
                }

                // Reload sermon after save
                $sermon = $sermonManager->getSermon($id);

                // Handle study link confirmations
                if (isset($_POST['confirm_links']) && is_array($_POST['confirm_links'])) {
                    foreach ($_POST['confirm_links'] as $studyId) {
                        $sermonManager->confirmStudyLink($id, (int)$studyId, $_SESSION['admin_user_id']);
                    }
                }

                // Save scripture references
                if (!empty($data['transcript'])) {
                    $sermonManager->saveScriptureReferences($id);
                }

            } catch (Exception $e) {
                $error = 'Error saving sermon: ' . $e->getMessage();
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] === 'created') {
    $success = 'Sermon created successfully';
}

// Get all series for dropdown
$allSeries = $sermonManager->getSeriesList(false);

// Get speakers for dropdown
$speakers = $sermonManager->getSpeakers();

// Get existing study links and suggestions
$studyLinks = $id ? $sermonManager->getStudyLinks($id) : [];
$studySuggestions = $id && $sermon && !empty($sermon['transcript']) ? $sermonManager->suggestStudyLinks($id) : [];

// Set page title
$page_title = $id ? 'Edit Sermon' : 'Add Sermon';

// NOW include the header (after all redirects are done)
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.sermon-editor {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
    align-items: start;
}

@media (max-width: 1024px) {
    .sermon-editor {
        grid-template-columns: 1fr;
    }
}

.youtube-fetch-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.youtube-fetch-section input[name="youtube_input"] {
    flex: 1;
}

.fetch-btn {
    white-space: nowrap;
}

.video-preview {
    margin-top: 1rem;
    border-radius: 0.5rem;
    overflow: hidden;
    background: #000;
}

.video-preview iframe {
    width: 100%;
    aspect-ratio: 16/9;
    border: 0;
}

.thumbnail-preview {
    max-width: 200px;
    border-radius: 0.5rem;
    margin-top: 0.5rem;
}

.study-link-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
}

.study-link-item.confirmed {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
}

.study-link-item.suggested {
    background: #fefce8;
    border: 1px solid #fde68a;
}

.relevance-badge {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 1rem;
    background: #e2e8f0;
}

.transcript-textarea {
    font-family: monospace;
    font-size: 0.875rem;
    line-height: 1.6;
}

.sidebar-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.sidebar-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: #334155;
}

.featured-options {
    display: none;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.featured-options.active {
    display: block;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="margin-bottom: 1rem;">
    <a href="/admin/sermons.php?view=messages" class="btn btn-outline">← Back to Sermons</a>
</div>

<form method="post" id="sermon-form">
    <?= csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="youtube_fetched" id="youtube_fetched" value="">
    <input type="hidden" name="duration_seconds" id="duration_seconds" value="<?= $sermon['duration_seconds'] ?? ''; ?>">

    <div class="sermon-editor">
        <!-- Main Content -->
        <div class="main-column">
            <div class="card">
                <div class="card-header">
                    <h2><?= $id ? 'Edit Sermon' : 'New Sermon'; ?></h2>
                </div>

                <!-- YouTube Fetch Section -->
                <div class="youtube-fetch-section">
                    <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">YouTube Video</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="youtube_input" id="youtube_input"
                               placeholder="Paste YouTube URL or video ID"
                               value="<?= htmlspecialchars($sermon['youtube_video_id'] ?? ''); ?>">
                        <button type="button" id="fetch-youtube-btn" class="btn btn-outline fetch-btn">
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

                <div class="form-group">
                    <label>Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="title" id="title" required
                           value="<?= htmlspecialchars($sermon['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text" name="slug" id="slug"
                           value="<?= htmlspecialchars($sermon['slug'] ?? ''); ?>"
                           placeholder="auto-generated-from-title">
                    <div class="form-help">Leave blank to auto-generate from title</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Series</label>
                        <select name="series_id">
                            <option value="">No Series</option>
                            <?php foreach ($allSeries as $series): ?>
                                <option value="<?= $series['id']; ?>" <?= ($sermon['series_id'] ?? '') == $series['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($series['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sermon Date</label>
                        <input type="date" name="sermon_date"
                               value="<?= $sermon['sermon_date'] ?? date('Y-m-d'); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Speaker (from Users)</label>
                        <select name="speaker_user_id" id="speaker_user_id">
                            <option value="">Select speaker...</option>
                            <?php foreach ($speakers['users'] as $user): ?>
                                <option value="<?= $user['id']; ?>" <?= ($sermon['speaker_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Speaker Name (Override)</label>
                        <input type="text" name="speaker" id="speaker"
                               value="<?= htmlspecialchars($sermon['speaker'] ?? ''); ?>"
                               placeholder="Leave blank to use selected user">
                        <div class="form-help">Only use if speaker isn't in the system</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" id="description"><?= htmlspecialchars($sermon['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Transcript</label>
                    <textarea name="transcript" rows="12" id="transcript" class="transcript-textarea"
                              placeholder="Paste or fetch transcript from YouTube..."><?= htmlspecialchars($sermon['transcript'] ?? ''); ?></textarea>
                    <div class="form-help">
                        Used for search and automatic Bible study linking.
                        <?php if (!empty($sermon['youtube_video_id'])): ?>
                            <button type="button" id="refetch-transcript-btn" class="btn btn-sm btn-outline" style="margin-left: 0.5rem;">
                                Re-fetch Transcript
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Thumbnail URL</label>
                        <input type="text" name="thumbnail_url" id="thumbnail_url"
                               value="<?= htmlspecialchars($sermon['thumbnail_url'] ?? ''); ?>">
                        <?php if (!empty($sermon['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($sermon['thumbnail_url']); ?>" class="thumbnail-preview" alt="Thumbnail">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Duration</label>
                        <input type="text" name="length" id="length"
                               value="<?= htmlspecialchars($sermon['length'] ?? ''); ?>"
                               placeholder="45 mins">
                    </div>
                </div>

                <div class="form-group">
                    <label>Audio URL (Optional)</label>
                    <input type="text" name="audio_url"
                           value="<?= htmlspecialchars($sermon['audio_url'] ?? ''); ?>"
                           placeholder="https://example.com/sermon.mp3">
                </div>
            </div>

            <?php if ($id && (!empty($studyLinks) || !empty($studySuggestions))): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Bible Study Links</h2>
                </div>

                <?php if (!empty($studyLinks)): ?>
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem;">Confirmed Links</h3>
                    <?php foreach ($studyLinks as $link): ?>
                        <?php if ($link['link_type'] !== 'auto_suggested'): ?>
                            <div class="study-link-item confirmed">
                                <span style="flex: 1;">
                                    <strong><?= htmlspecialchars($link['book_name']); ?> <?= $link['chapter']; ?></strong>
                                    <?php if ($link['study_title']): ?>
                                        - <?= htmlspecialchars($link['study_title']); ?>
                                    <?php endif; ?>
                                    <?php if ($link['verse_reference']): ?>
                                        <span style="color: #64748b;">(<?= htmlspecialchars($link['verse_reference']); ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <span class="relevance-badge"><?= round($link['relevance_score']); ?>%</span>
                                <a href="/bible-study/<?= htmlspecialchars($link['book_slug']); ?>/<?= $link['chapter']; ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php
                $pendingSuggestions = array_filter($studySuggestions, function($s) {
                    return $s['existing_link_type'] !== 'admin_confirmed' && $s['existing_link_type'] !== 'admin_added';
                });
                ?>

                <?php if (!empty($pendingSuggestions)): ?>
                    <h3 style="font-size: 1rem; margin: 1.5rem 0 0.75rem 0;">Suggested Links (confirm below)</h3>
                    <?php foreach ($pendingSuggestions as $suggestion): ?>
                        <div class="study-link-item suggested">
                            <input type="checkbox" name="confirm_links[]" value="<?= $suggestion['study_id']; ?>" style="width: auto;">
                            <span style="flex: 1;">
                                <strong><?= htmlspecialchars($suggestion['book_name']); ?> <?= $suggestion['chapter']; ?></strong>
                                <?php if ($suggestion['study_title']): ?>
                                    - <?= htmlspecialchars($suggestion['study_title']); ?>
                                <?php endif; ?>
                                <br>
                                <span style="color: #64748b; font-size: 0.875rem;"><?= htmlspecialchars($suggestion['verse_reference']); ?></span>
                            </span>
                            <span class="relevance-badge"><?= round($suggestion['relevance_score']); ?>%</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($studyLinks) && empty($studySuggestions)): ?>
                    <p style="color: #64748b; text-align: center; padding: 1rem;">
                        No Bible study links detected. Add a transcript to auto-detect scripture references.
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar-column">
            <div class="sidebar-card">
                <h3>Publish</h3>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="visible" value="1"
                               <?= ($sermon['visible'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                        <span>Visible on website</span>
                    </label>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <?= $id ? 'Update Sermon' : 'Create Sermon'; ?>
                    </button>
                </div>

                <?php if ($id): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                        <a href="/sermon/<?= htmlspecialchars($sermon['slug']); ?>" target="_blank" class="btn btn-outline" style="width: 100%;">
                            View on Site →
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar-card">
                <h3>Featured Sermon</h3>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_featured" id="is_featured" value="1"
                               <?= ($sermon['is_featured'] ?? 0) ? 'checked' : ''; ?> style="width: auto;">
                        <span>Feature this sermon</span>
                    </label>
                </div>

                <div class="featured-options <?= ($sermon['is_featured'] ?? 0) ? 'active' : ''; ?>" id="featured-options">
                    <div class="form-group">
                        <label>Featured Location</label>
                        <select name="featured_location">
                            <option value="homepage" <?= ($sermon['featured_location'] ?? '') === 'homepage' ? 'selected' : ''; ?>>Homepage</option>
                            <option value="watch_page" <?= ($sermon['featured_location'] ?? '') === 'watch_page' ? 'selected' : ''; ?>>Watch Page</option>
                            <option value="sidebar" <?= ($sermon['featured_location'] ?? '') === 'sidebar' ? 'selected' : ''; ?>>Sidebar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" name="featured_order" value="<?= $sermon['featured_order'] ?? 0; ?>" min="0">
                        <div class="form-help">Lower numbers appear first</div>
                    </div>
                </div>
            </div>

            <?php if ($id && $sermon && !empty($sermon['youtube_fetched_at'])): ?>
                <div class="sidebar-card">
                    <h3>YouTube Data</h3>
                    <p style="font-size: 0.875rem; color: #64748b;">
                        Last fetched: <?= date('M j, Y g:ia', strtotime($sermon['youtube_fetched_at'])); ?>
                    </p>
                    <button type="button" id="refetch-all-btn" class="btn btn-outline btn-sm" style="width: 100%;">
                        Re-fetch All Data
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

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

    // Toggle featured options
    isFeaturedCheckbox.addEventListener('change', function() {
        featuredOptions.classList.toggle('active', this.checked);
    });

    // Extract video ID from URL
    function extractVideoId(input) {
        // Handle various YouTube URL formats
        const patterns = [
            /(?:youtube\.com\/(?:watch\?v=|embed\/|live\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
            /[?&]v=([a-zA-Z0-9_-]{11})/
        ];
        for (const pattern of patterns) {
            const match = input.match(pattern);
            if (match) return match[1];
        }
        // Check if input is already a video ID
        if (/^[a-zA-Z0-9_-]{11}$/.test(input.trim())) return input.trim();
        return null;
    }

    // Auto-fetch on paste
    youtubeInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            const videoId = extractVideoId(youtubeInput.value);
            if (videoId) {
                youtubeVideoIdField.value = videoId;
                fetchYouTubeData(videoId);
            }
        }, 100);
    });

    // Fetch button click
    fetchBtn.addEventListener('click', function() {
        const videoId = extractVideoId(youtubeInput.value);
        if (videoId) {
            youtubeVideoIdField.value = videoId;
            fetchYouTubeData(videoId);
        } else {
            youtubeStatus.innerHTML = '<span style="color: #ef4444;">Invalid YouTube URL or video ID</span>';
        }
    });

    // Re-fetch transcript button
    const refetchTranscriptBtn = document.getElementById('refetch-transcript-btn');
    if (refetchTranscriptBtn) {
        refetchTranscriptBtn.addEventListener('click', function() {
            const videoId = youtubeVideoIdField.value;
            if (videoId) {
                fetchYouTubeData(videoId, true);
            }
        });
    }

    // Re-fetch all button
    const refetchAllBtn = document.getElementById('refetch-all-btn');
    if (refetchAllBtn) {
        refetchAllBtn.addEventListener('click', function() {
            const videoId = youtubeVideoIdField.value;
            if (videoId) {
                fetchYouTubeData(videoId, false, true);
            }
        });
    }

    function fetchYouTubeData(videoId, transcriptOnly = false, overwriteAll = false) {
        youtubeStatus.innerHTML = '<span style="color: #3b82f6;">Fetching data from YouTube...</span>';
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

                // Update video preview
                videoPreview.innerHTML = '<iframe src="https://www.youtube.com/embed/' + videoId + '" allowfullscreen></iframe>';
                videoPreview.style.display = 'block';

                if (!transcriptOnly || overwriteAll) {
                    // Fill in fields if empty or overwrite all
                    if (!titleField.value || overwriteAll) {
                        titleField.value = data.data.title;
                    }
                    if (!descriptionField.value || overwriteAll) {
                        descriptionField.value = data.data.description || '';
                    }
                    if (!thumbnailField.value || overwriteAll) {
                        thumbnailField.value = data.data.thumbnail_url;
                    }
                    if (data.data.duration_formatted && (!lengthField.value || overwriteAll)) {
                        lengthField.value = data.data.duration_formatted;
                    }
                    if (data.data.duration_seconds) {
                        durationSecondsField.value = data.data.duration_seconds;
                    }
                }

                // Always update transcript if available
                if (data.data.transcript) {
                    if (!transcriptField.value || transcriptOnly || overwriteAll) {
                        transcriptField.value = data.data.transcript;
                    }
                    youtubeStatus.innerHTML = '<span style="color: #22c55e;">✓ Video data and transcript fetched</span>';
                } else {
                    if (transcriptOnly) {
                        youtubeStatus.innerHTML = '<span style="color: #f59e0b;">⚠ Transcript not available - YouTube captions may be disabled or auto-captions not generated yet</span>';
                    } else {
                        youtubeStatus.innerHTML = '<span style="color: #22c55e;">✓ Video data fetched</span> <span style="color: #64748b;">(transcript not available - enter manually if needed)</span>';
                    }
                }
            } else {
                youtubeStatus.innerHTML = '<span style="color: #ef4444;">Error: ' + (data.error || 'Unknown error') + '</span>';
            }
        })
        .catch(error => {
            fetchBtn.disabled = false;
            youtubeStatus.innerHTML = '<span style="color: #ef4444;">Network error. Please try again.</span>';
            console.error('Fetch error:', error);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
