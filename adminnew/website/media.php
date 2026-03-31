<?php
/**
 * Media Library - New Admin
 */
@ini_set('upload_max_filesize', '256M');
@ini_set('post_max_size', '512M');
@ini_set('max_execution_time', '600');
@ini_set('max_input_time', '600');
@ini_set('memory_limit', '512M');

$page_title = 'Media Library';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/ImageProcessor.php';
require_once __DIR__ . '/../../includes/ImageNameGenerator.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Fetch all tags
$all_tags = $pdo->query("SELECT * FROM media_tags ORDER BY name")->fetchAll();

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT file_path FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();

    if ($media) {
        $file_path = __DIR__ . '/../../' . $media['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
        if ($stmt->execute([$id])) {
            log_activity($_SESSION['admin_user_id'], 'delete', 'media', $id, 'Deleted media file');
            $success = 'Media file deleted successfully';
        }
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['file']) || isset($_FILES['files']))) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $upload_dir = __DIR__ . '/../../uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $files = [];
        if (isset($_FILES['files'])) {
            $fileCount = count($_FILES['files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $files[] = [
                        'name' => $_FILES['files']['name'][$i],
                        'type' => $_FILES['files']['type'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error' => $_FILES['files']['error'][$i],
                        'size' => $_FILES['files']['size'][$i]
                    ];
                }
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $files[] = $_FILES['file'];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'mp3', 'mp4', 'mov', 'avi', 'doc', 'docx', 'xls', 'xlsx', 'webp'];
        $uploadedCount = 0;
        $errorMessages = [];

        foreach ($files as $file) {
            $original_filename = basename($file['name']);
            $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed)) {
                $errorMessages[] = "{$original_filename}: File type not allowed";
                continue;
            } elseif ($file['size'] > 50 * 1024 * 1024) {
                $errorMessages[] = "{$original_filename}: File size exceeds 50MB limit";
                continue;
            }

            $temp_filename = 'temp_' . time() . '_' . uniqid() . '.' . $file_ext;
            $temp_path = $upload_dir . $temp_filename;

            if (move_uploaded_file($file['tmp_name'], $temp_path)) {
                $image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $video_types = ['mp4', 'mov', 'avi'];
                $audio_types = ['mp3'];

                if (in_array($file_ext, $image_types)) {
                    $file_type = 'image';
                } elseif (in_array($file_ext, $video_types)) {
                    $file_type = 'video';
                } elseif (in_array($file_ext, $audio_types)) {
                    $file_type = 'audio';
                } else {
                    $file_type = 'document';
                }

                $nameGenerator = new ImageNameGenerator();
                if ($file_type === 'image' && $nameGenerator->isConfigured()) {
                    $seo_name = $nameGenerator->generateName($temp_path, $original_filename);
                } else {
                    $seo_name = $nameGenerator->sanitizeFilename($original_filename);
                }

                $unique_filename = $nameGenerator->ensureUnique($seo_name, $file_ext, $upload_dir) . '.' . $file_ext;
                $upload_path = $upload_dir . $unique_filename;
                rename($temp_path, $upload_path);

                $width = null;
                $height = null;
                if ($file_type === 'image') {
                    $imageInfo = @getimagesize($upload_path);
                    if ($imageInfo) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }

                $file_path = 'uploads/' . $unique_filename;
                $file_url = '/uploads/' . $unique_filename;

                $stmt = $pdo->prepare("INSERT INTO media (filename, original_filename, file_type, file_size, file_path, file_url, width, height, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $unique_filename,
                    $original_filename,
                    $file_type,
                    $file['size'],
                    $file_path,
                    $file_url,
                    $width,
                    $height,
                    $_SESSION['admin_user_id']
                ]);

                $mediaId = $pdo->lastInsertId();

                if ($file_type === 'image') {
                    $processor = new ImageProcessor(__DIR__ . '/../../uploads');
                    $imageType = 'general';
                    if ($width && $height && ($width / $height) > 2) {
                        $imageType = 'hero';
                    }
                    $processor->process($upload_path, $imageType, false);
                }

                log_activity($_SESSION['admin_user_id'], 'upload', 'media', $mediaId, 'Uploaded: ' . $original_filename);
                $uploadedCount++;
            } else {
                $errorMessages[] = "{$original_filename}: Failed to save file";
            }
        }

        if ($uploadedCount > 0) {
            $success = $uploadedCount === 1 ? 'File uploaded successfully' : "{$uploadedCount} files uploaded successfully";
        }
        if (!empty($errorMessages)) {
            $error = implode('; ', $errorMessages);
        }
    }
}

// Fetch all media
$filter = $_GET['filter'] ?? 'all';
$tag_filter = $_GET['tag'] ?? '';
$search = $_GET['search'] ?? '';

$baseQuery = "
    SELECT m.*, MAX(u.username) as username,
           COALESCE(MAX(iv.variant_path), m.file_path) as thumbnail_path,
           GROUP_CONCAT(mt.name) as tag_names,
           GROUP_CONCAT(mt.id) as tag_ids
    FROM media m
    LEFT JOIN users u ON m.uploaded_by = u.id
    LEFT JOIN image_variants iv ON m.id = iv.media_id AND iv.variant_name = 'thumbnail'
    LEFT JOIN media_tag_assignments mta ON m.id = mta.media_id
    LEFT JOIN media_tags mt ON mta.tag_id = mt.id
";

$conditions = [];
$params = [];

if ($filter !== 'all') {
    $conditions[] = "m.file_type = ?";
    $params[] = $filter;
}

if (!empty($tag_filter)) {
    $conditions[] = "mta.tag_id = ?";
    $params[] = $tag_filter;
}

if (!empty($search)) {
    $conditions[] = "(m.original_filename LIKE ? OR m.alt_text LIKE ? OR m.caption LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($conditions)) {
    $baseQuery .= " WHERE " . implode(" AND ", $conditions);
}

$baseQuery .= " GROUP BY m.id ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($baseQuery);
$stmt->execute($params);
$media_files = $stmt->fetchAll();
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Media Library</h1>
        <p class="admin-page-subtitle"><?= count($media_files); ?> files</p>
    </div>
</div>

<!-- Upload Zone -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Upload</h3>
    </div>
    <div class="admin-card-body">
        <form method="post" enctype="multipart/form-data" id="upload-form">
            <?= csrf_field(); ?>
            <div class="upload-dropzone" id="upload-dropzone">
                <input type="file" name="files[]" id="file-input" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.mp3,.pdf,.doc,.docx,.xls,.xlsx">
                <div class="upload-dropzone-content">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p><strong>Drop files here</strong> or click to browse</p>
                    <span class="upload-hint">Supports images, videos, audio, PDFs, and documents</span>
                </div>
                <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="upload-progress-bar" id="upload-progress-bar"></div>
                    <span class="upload-progress-text" id="upload-progress-text">Uploading...</span>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Filter + Grid -->
<div class="admin-card">
    <div class="admin-card-header" style="flex-wrap: wrap; gap: 1rem;">
        <div class="admin-filter-tabs">
            <a href="/adminnew/media?filter=all<?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="/adminnew/media?filter=image<?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'image' ? 'active' : ''; ?>">Images</a>
            <a href="/adminnew/media?filter=video<?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'video' ? 'active' : ''; ?>">Video</a>
            <a href="/adminnew/media?filter=audio<?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'audio' ? 'active' : ''; ?>">Audio</a>
            <a href="/adminnew/media?filter=document<?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'document' ? 'active' : ''; ?>">Docs</a>
        </div>
        <form method="get" style="display: flex; gap: 0.5rem;">
            <input type="hidden" name="module" value="media">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search files..." class="admin-form-input" style="width: 200px;">
            <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
            <?php if ($search): ?>
                <a href="/adminnew/media?filter=<?= $filter; ?>" class="admin-btn admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($media_files)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <h3 class="admin-empty-title">No files yet</h3>
            <p class="admin-empty-text">Upload files using the dropzone above.</p>
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($media_files as $media): ?>
                <div class="media-card">
                    <div class="media-preview">
                        <?php if ($media['file_type'] === 'image'): ?>
                            <?php
                            $thumbPath = $media['thumbnail_path'];
                            if (strpos($thumbPath, '/uploads/') !== false) {
                                $thumbPath = '/uploads/' . basename($thumbPath);
                            } elseif (strpos($thumbPath, 'uploads/') === 0) {
                                $thumbPath = '/' . $thumbPath;
                            } else {
                                $thumbPath = '/' . $media['file_path'];
                            }
                            ?>
                            <img src="<?= htmlspecialchars($thumbPath); ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="media-icon">
                                <?= $media['file_type'] === 'video' ? '🎥' : ($media['file_type'] === 'audio' ? '🎵' : '📄'); ?>
                            </span>
                        <?php endif; ?>
                        <div class="media-overlay">
                            <button class="admin-btn admin-btn-sm" onclick="copyToClipboard('<?= htmlspecialchars('https://alivechur.ch/' . $media['file_path']); ?>', this)">Copy URL</button>
                            <a href="/adminnew/media?filter=<?= $filter; ?>&delete=<?= $media['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this file?')">Delete</a>
                        </div>
                    </div>
                    <div class="media-info">
                        <span class="media-name" title="<?= htmlspecialchars($media['original_filename']); ?>"><?= htmlspecialchars($media['original_filename']); ?></span>
                        <span class="media-size"><?= number_format($media['file_size'] / 1024, 0); ?> KB</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
.upload-dropzone {
    position: relative;
    border: 2px dashed var(--admin-border);
    border-radius: var(--admin-radius-lg);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all var(--admin-transition);
    background: var(--admin-bg);
}
.upload-dropzone:hover,
.upload-dropzone.dragover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, transparent);
}
.upload-dropzone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}
.upload-dropzone-content {
    pointer-events: none;
}
.upload-dropzone-content svg {
    color: var(--admin-text-muted);
    margin-bottom: 0.75rem;
}
.upload-dropzone-content p {
    margin: 0 0 0.25rem 0;
}
.upload-hint {
    font-size: 0.85rem;
    color: var(--admin-text-muted);
}
.upload-progress {
    margin-top: 1rem;
}
.upload-progress-bar {
    height: 6px;
    background: var(--current-app-color);
    border-radius: 3px;
    width: 0%;
    transition: width 0.3s ease;
}
.upload-progress-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--admin-text-muted);
}

.admin-filter-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--admin-bg);
    padding: 0.25rem;
    border-radius: var(--admin-radius);
}
.admin-filter-tab {
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: var(--admin-text-muted);
    border-radius: var(--admin-radius-sm);
    font-weight: 500;
    font-size: 0.875rem;
    transition: all var(--admin-transition);
}
.admin-filter-tab:hover {
    color: var(--admin-text);
}
.admin-filter-tab.active {
    background: var(--admin-card-bg);
    color: var(--admin-text);
    box-shadow: var(--admin-shadow-sm);
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    padding: var(--admin-spacing-lg);
}
.media-card {
    border-radius: var(--admin-radius);
    overflow: hidden;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
}
.media-preview {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-bg);
    position: relative;
    overflow: hidden;
}
.media-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.media-icon {
    font-size: 2rem;
}
.media-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity var(--admin-transition);
}
.media-card:hover .media-overlay {
    opacity: 1;
}
.media-info {
    padding: 0.5rem;
}
.media-name {
    display: block;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.media-size {
    font-size: 0.65rem;
    color: var(--admin-text-muted);
}

.admin-alert {
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}
.admin-alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--admin-success);
    border: 1px solid var(--admin-success);
}
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}
</style>

<script>
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy URL', 1000);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('file-input');
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressText = document.getElementById('upload-progress-text');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
    });

    dropzone.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            uploadFiles(files);
        }
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            uploadFiles(this.files);
        }
    });

    function uploadFiles(files) {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        progressDiv.style.display = 'block';
        progressBar.style.width = '0%';

        const xhr = new XMLHttpRequest();
        xhr.timeout = 600000;

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = percent < 100 ? `Uploading... ${percent}%` : 'Processing...';
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                progressText.textContent = 'Upload complete! Refreshing...';
                setTimeout(() => window.location.reload(), 500);
            } else {
                progressText.textContent = 'Upload failed. Please try again.';
                progressBar.style.background = '#ef4444';
            }
        });

        xhr.addEventListener('error', function() {
            progressText.textContent = 'Network error. Check your connection.';
            progressBar.style.background = '#ef4444';
        });

        xhr.open('POST', window.location.href);
        xhr.send(formData);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
