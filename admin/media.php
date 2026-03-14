<?php
$page_title = 'Media Library';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/ImageProcessor.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT file_path FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();

    if ($media) {
        // Delete file from filesystem
        $file_path = __DIR__ . '/../' . $media['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
        if ($stmt->execute([$id])) {
            log_activity($_SESSION['admin_user_id'], 'delete', 'media', $id, 'Deleted media file');
            $success = 'Media file deleted successfully';
        }
    }
}

// Handle File Upload (supports multiple files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['file']) || isset($_FILES['files']))) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $upload_dir = __DIR__ . '/../uploads/';

        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Normalize files array (handle both single and multiple uploads)
        $files = [];
        if (isset($_FILES['files'])) {
            // Multiple files uploaded
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
            // Single file (backwards compatibility)
            $files[] = $_FILES['file'];
        }

        // Allowed file types
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'mp3', 'mp4', 'mov', 'avi', 'doc', 'docx', 'xls', 'xlsx', 'webp'];

        $uploadedCount = 0;
        $errorMessages = [];

        foreach ($files as $file) {
            $original_filename = basename($file['name']);
            $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed)) {
                $errorMessages[] = "{$original_filename}: File type not allowed";
                continue;
            } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
                $errorMessages[] = "{$original_filename}: File size exceeds 50MB limit";
                continue;
            }

            // Generate unique filename
            $unique_filename = time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $unique_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Determine file type category
                $image_types = ['jpg', 'jpeg', 'png', 'gif'];
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

                // Get image dimensions if applicable
                $width = null;
                $height = null;
                if ($file_type === 'image') {
                    $imageInfo = @getimagesize($upload_path);
                    if ($imageInfo) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }

                // Insert into database
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

                // Process image for optimization (WebP conversion + resizing)
                if ($file_type === 'image') {
                    $processor = new ImageProcessor(__DIR__ . '/../uploads');

                    // Determine image type based on dimensions
                    $imageType = 'general';
                    if ($width && $height && ($width / $height) > 2) {
                        $imageType = 'hero';
                    }

                    // Process synchronously (async requires cron job)
                    $processingResult = $processor->process($upload_path, $imageType, false);
                    error_log("ImageProcessor result: " . json_encode($processingResult));

                    // Store variant information in database
                    if ($processingResult['success'] && !empty($processingResult['variants'])) {
                        $variantStmt = $pdo->prepare("
                            INSERT INTO image_variants (media_id, original_path, variant_name, variant_path, format, width, height, file_size)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        foreach ($processingResult['variants'] as $variantName => $variant) {
                            $variantStmt->execute([
                                $mediaId,
                                $upload_path,
                                $variantName,
                                $variant['path'],
                                pathinfo($variant['path'], PATHINFO_EXTENSION),
                                $variant['width'],
                                $variant['height'],
                                $variant['size']
                            ]);
                        }

                        // Store WebP variants
                        if (!empty($processingResult['webp_variants'])) {
                            foreach ($processingResult['webp_variants'] as $variantName => $variant) {
                                $variantStmt->execute([
                                    $mediaId,
                                    $upload_path,
                                    $variantName . '_webp',
                                    $variant['path'],
                                    'webp',
                                    null,
                                    null,
                                    $variant['size']
                                ]);
                            }
                        }

                    }
                }

                log_activity($_SESSION['admin_user_id'], 'upload', 'media', $mediaId, 'Uploaded: ' . $original_filename);
                $uploadedCount++;
            } else {
                $errorMessages[] = "{$original_filename}: Failed to save file";
            }
        }

        // Set success/error messages based on results
        if ($uploadedCount > 0) {
            $success = $uploadedCount === 1
                ? 'File uploaded and optimized successfully'
                : "{$uploadedCount} files uploaded and optimized successfully";
        }
        if (!empty($errorMessages)) {
            $error = implode('; ', $errorMessages);
        }
    }
}

// Handle Edit Metadata
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_meta'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'];
        $alt_text = $_POST['alt_text'];
        $caption = $_POST['caption'];

        $stmt = $pdo->prepare("UPDATE media SET alt_text = ?, caption = ? WHERE id = ?");
        $stmt->execute([$alt_text, $caption, $id]);
        log_activity($_SESSION['admin_user_id'], 'update', 'media', $id, 'Updated media metadata');
        $success = 'Media information updated';
    }
}

// Fetch all media with thumbnail variants
$filter = $_GET['filter'] ?? 'all';
$baseQuery = "
    SELECT m.*, u.username,
           COALESCE(iv.variant_path, m.file_path) as thumbnail_path
    FROM media m
    LEFT JOIN users u ON m.uploaded_by = u.id
    LEFT JOIN image_variants iv ON m.id = iv.media_id AND iv.variant_name = 'thumbnail'
";
if ($filter === 'all') {
    $media_files = $pdo->query($baseQuery . " ORDER BY m.created_at DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare($baseQuery . " WHERE m.file_type = ? ORDER BY m.created_at DESC");
    $stmt->execute([$filter]);
    $media_files = $stmt->fetchAll();
}

// Get media for editing
$edit_media = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_media = $stmt->fetch();
}
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Upload Zone -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Upload</h3>
    </div>
    <form method="post" enctype="multipart/form-data" id="upload-form">
        <?= csrf_field(); ?>
        <div class="upload-dropzone" id="upload-dropzone">
            <input type="file" name="files[]" id="file-input" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx">
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

<style>
.upload-dropzone {
    position: relative;
    border: 2px dashed var(--admin-border);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--admin-bg-subtle);
}

.upload-dropzone:hover,
.upload-dropzone.dragover {
    border-color: var(--admin-purple);
    background: rgba(75, 38, 121, 0.05);
}

.upload-dropzone.dragover {
    transform: scale(1.01);
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
    color: var(--admin-text);
}

.upload-dropzone-content .upload-hint {
    font-size: 0.85rem;
    color: var(--admin-text-muted);
}

.upload-progress {
    margin-top: 1rem;
}

.upload-progress-bar {
    height: 6px;
    background: var(--admin-purple);
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('file-input');
    const form = document.getElementById('upload-form');
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressText = document.getElementById('upload-progress-text');

    // Drag and drop handlers
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
    });

    // Handle dropped files
    dropzone.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            uploadFiles(files);
        }
    });

    // Handle selected files
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
        progressText.textContent = `Uploading ${files.length} file${files.length > 1 ? 's' : ''}...`;

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = `Uploading... ${percent}%`;
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                progressBar.style.width = '100%';
                progressText.textContent = 'Processing complete! Refreshing...';
                setTimeout(() => window.location.reload(), 500);
            } else {
                progressText.textContent = 'Upload failed. Please try again.';
                progressBar.style.background = '#ef4444';
            }
        });

        xhr.addEventListener('error', function() {
            progressText.textContent = 'Upload failed. Please try again.';
            progressBar.style.background = '#ef4444';
        });

        xhr.open('POST', window.location.href);
        xhr.send(formData);
    }
});
</script>

<!-- Compact Edit Form -->
<?php if ($edit_media): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Edit: <?= htmlspecialchars($edit_media['original_filename']); ?></h3>
        <a href="/admin/media.php" class="btn btn-xs btn-outline">Cancel</a>
    </div>
    <form method="post" style="display: flex; gap: 0.5rem; align-items: end;">
        <?= csrf_field(); ?>
        <input type="hidden" name="edit_meta" value="1">
        <input type="hidden" name="id" value="<?= $edit_media['id']; ?>">
        <div class="form-group" style="flex: 1;">
            <label>Alt Text</label>
            <input type="text" name="alt_text" value="<?= htmlspecialchars($edit_media['alt_text'] ?? ''); ?>" placeholder="Image description">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Caption</label>
            <input type="text" name="caption" value="<?= htmlspecialchars($edit_media['caption'] ?? ''); ?>" placeholder="Optional caption">
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Save</button>
    </form>
</div>
<?php endif; ?>

<!-- Filter + Grid -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Library</h3>
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="?filter=all" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=image" class="admin-filter-tab <?= $filter === 'image' ? 'active' : ''; ?>">Images</a>
            <a href="?filter=video" class="admin-filter-tab <?= $filter === 'video' ? 'active' : ''; ?>">Video</a>
            <a href="?filter=audio" class="admin-filter-tab <?= $filter === 'audio' ? 'active' : ''; ?>">Audio</a>
            <a href="?filter=document" class="admin-filter-tab <?= $filter === 'document' ? 'active' : ''; ?>">Docs</a>
        </div>
    </div>

    <?php if (empty($media_files)): ?>
        <p class="admin-muted-text">No files yet. Upload one above.</p>
    <?php else: ?>
        <div class="admin-media-grid">
            <?php foreach ($media_files as $media): ?>
                <div class="admin-media-card">
                    <!-- Preview with hover overlay -->
                    <div class="admin-media-preview">
                        <?php if ($media['file_type'] === 'image'): ?>
                            <?php
                            // Use thumbnail if available, converting full path to URL
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
                            <span class="admin-media-icon">
                                <?= $media['file_type'] === 'video' ? '🎥' : ($media['file_type'] === 'audio' ? '🎵' : '📄'); ?>
                            </span>
                        <?php endif; ?>
                        <div class="admin-media-overlay">
                            <button onclick="navigator.clipboard.writeText('/<?= htmlspecialchars($media['file_path']); ?>'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy URL', 1000);" class="btn btn-xs">Copy URL</button>
                            <a href="?edit=<?= $media['id']; ?>" class="btn btn-xs">Edit</a>
                            <a href="?delete=<?= $media['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                        </div>
                    </div>
                    <!-- Compact info -->
                    <div class="admin-media-info">
                        <span class="admin-media-name" title="<?= htmlspecialchars($media['original_filename']); ?>"><?= htmlspecialchars($media['original_filename']); ?></span>
                        <span class="admin-media-meta"><?= number_format($media['file_size'] / 1024, 0); ?>KB</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
