<?php
$page_title = 'Media Library';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/ImageProcessor.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Fetch all tags for display
$all_tags = $pdo->query("SELECT * FROM media_tags ORDER BY name")->fetchAll();

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
        $tags = $_POST['tags'] ?? [];

        $stmt = $pdo->prepare("UPDATE media SET alt_text = ?, caption = ? WHERE id = ?");
        $stmt->execute([$alt_text, $caption, $id]);

        // Update tags
        $pdo->prepare("DELETE FROM media_tag_assignments WHERE media_id = ?")->execute([$id]);
        if (!empty($tags)) {
            $tagStmt = $pdo->prepare("INSERT INTO media_tag_assignments (media_id, tag_id) VALUES (?, ?)");
            foreach ($tags as $tagId) {
                $tagStmt->execute([$id, $tagId]);
            }
        }

        log_activity($_SESSION['admin_user_id'], 'update', 'media', $id, 'Updated media metadata');
        $success = 'Media information updated';
    }
}

// Handle Add New Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $tagName = trim($_POST['tag_name']);
        $tagColor = $_POST['tag_color'] ?? '#6b7280';
        if (!empty($tagName)) {
            $tagSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $tagName));
            $stmt = $pdo->prepare("INSERT IGNORE INTO media_tags (name, slug, color) VALUES (?, ?, ?)");
            $stmt->execute([$tagName, $tagSlug, $tagColor]);
            $success = 'Tag created successfully';
            // Refresh tags
            $all_tags = $pdo->query("SELECT * FROM media_tags ORDER BY name")->fetchAll();
        }
    }
}

// Handle Delete Tag
if (isset($_GET['delete_tag']) && is_numeric($_GET['delete_tag'])) {
    $tagId = (int)$_GET['delete_tag'];
    $pdo->prepare("DELETE FROM media_tags WHERE id = ?")->execute([$tagId]);
    $success = 'Tag deleted';
    $all_tags = $pdo->query("SELECT * FROM media_tags ORDER BY name")->fetchAll();
}

// Fetch all media with thumbnail variants
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

// Get media for editing
$edit_media = null;
$edit_media_tags = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_media = $stmt->fetch();

    // Get tags for this media
    $tagStmt = $pdo->prepare("SELECT tag_id FROM media_tag_assignments WHERE media_id = ?");
    $tagStmt->execute([$_GET['edit']]);
    $edit_media_tags = array_column($tagStmt->fetchAll(), 'tag_id');
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

/* Tag styles */
.tag-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.tag-checkbox {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border);
    transition: all 0.15s;
}
.tag-checkbox:has(input:checked) {
    background: var(--tag-color);
    color: white;
    border-color: var(--tag-color);
}
.tag-checkbox input {
    display: none;
}
.media-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    background: var(--tag-color);
    color: white;
}
.media-tag .tag-delete {
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    font-weight: bold;
    margin-left: 0.25rem;
}
.media-tag .tag-delete:hover {
    color: white;
}
.media-tag-filter {
    display: inline-block;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-size: 0.75rem;
    text-decoration: none;
    background: var(--color-bg-subtle);
    color: var(--color-text);
    border: 1px solid var(--color-border);
    transition: all 0.15s;
}
.media-tag-filter:hover {
    border-color: var(--tag-color);
}
.media-tag-filter.active {
    background: var(--tag-color);
    color: white;
    border-color: var(--tag-color);
}
.admin-media-tags {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}
.admin-media-tags .mini-tag {
    font-size: 0.65rem;
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
    background: var(--tag-color);
    color: white;
}

/* Quick tags on cards */
.quick-tags {
    display: flex;
    gap: 2px;
    padding: 4px;
    flex-wrap: wrap;
}
.quick-tag {
    width: 22px;
    height: 22px;
    border-radius: 4px;
    border: 1px solid var(--color-border);
    background: var(--color-bg-subtle);
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    color: var(--color-text-muted);
}
.quick-tag:hover {
    border-color: var(--tag-color);
    color: var(--tag-color);
}
.quick-tag.active {
    background: var(--tag-color);
    border-color: var(--tag-color);
    color: white;
}

/* Selection */
.media-select-checkbox {
    position: absolute;
    top: 4px;
    left: 4px;
    z-index: 10;
    opacity: 0;
    transition: opacity 0.15s;
}
.admin-media-card:hover .media-select-checkbox,
.admin-media-card.selected .media-select-checkbox {
    opacity: 1;
}
.media-select-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.admin-media-card {
    position: relative;
}
.admin-media-card.selected {
    outline: 2px solid var(--color-purple);
    outline-offset: 2px;
}

/* Batch bar */
.batch-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: var(--color-purple);
    color: white;
    border-radius: var(--radius-md);
    margin: 0.5rem 1rem;
}
.batch-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}
.batch-info .btn {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.3);
    color: white;
}
.batch-tags {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}
.batch-tag-btn {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    border: none;
    background: rgba(255,255,255,0.2);
    color: white;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}
.batch-tag-btn:hover {
    background: var(--tag-color);
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
        <a href="/admin/media" class="btn btn-xs btn-outline">Cancel</a>
    </div>
    <form method="post">
        <?= csrf_field(); ?>
        <input type="hidden" name="edit_meta" value="1">
        <input type="hidden" name="id" value="<?= $edit_media['id']; ?>">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label>Alt Text</label>
                <input type="text" name="alt_text" value="<?= htmlspecialchars($edit_media['alt_text'] ?? ''); ?>" placeholder="Image description">
            </div>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label>Caption</label>
                <input type="text" name="caption" value="<?= htmlspecialchars($edit_media['caption'] ?? ''); ?>" placeholder="Optional caption">
            </div>
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label>Tags</label>
            <div class="tag-selector">
                <?php foreach ($all_tags as $tag): ?>
                    <label class="tag-checkbox" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id']; ?>" <?= in_array($tag['id'], $edit_media_tags) ? 'checked' : ''; ?>>
                        <span><?= htmlspecialchars($tag['name']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Save</button>
    </form>
</div>
<?php endif; ?>

<!-- Tag Management -->
<div class="admin-card">
    <details>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3>Manage Tags</h3>
        </summary>
        <div style="padding: 1rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <form method="post" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <?= csrf_field(); ?>
                <input type="hidden" name="add_tag" value="1">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.8rem;">New Tag</label>
                    <input type="text" name="tag_name" placeholder="Tag name" required style="width: 120px;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.8rem;">Color</label>
                    <input type="color" name="tag_color" value="#6b7280" style="width: 40px; height: 34px; padding: 2px;">
                </div>
                <button type="submit" class="btn btn-sm btn-outline">Add</button>
            </form>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php foreach ($all_tags as $tag): ?>
                    <span class="media-tag" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                        <?= htmlspecialchars($tag['name']); ?>
                        <a href="?delete_tag=<?= $tag['id']; ?>" class="tag-delete" title="Delete tag">&times;</a>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
</div>

<!-- Filter + Grid -->
<div class="admin-card">
    <div class="admin-card-header" style="flex-wrap: wrap; gap: 1rem;">
        <h3>Library</h3>
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <!-- Search -->
            <form method="get" style="display: flex; gap: 0.5rem;">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                <?php if ($tag_filter): ?><input type="hidden" name="tag" value="<?= htmlspecialchars($tag_filter); ?>"><?php endif; ?>
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search files..." style="width: 150px;">
                <button type="submit" class="btn btn-sm btn-outline">Search</button>
                <?php if ($search): ?><a href="?filter=<?= $filter; ?><?= $tag_filter ? '&tag=' . $tag_filter : ''; ?>" class="btn btn-sm btn-outline">Clear</a><?php endif; ?>
            </form>
        </div>
    </div>
    <!-- Type filters -->
    <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--color-border); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="?filter=all<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=image<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'image' ? 'active' : ''; ?>">Images</a>
            <a href="?filter=video<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'video' ? 'active' : ''; ?>">Video</a>
            <a href="?filter=audio<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'audio' ? 'active' : ''; ?>">Audio</a>
            <a href="?filter=document<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'document' ? 'active' : ''; ?>">Docs</a>
        </div>
        <!-- Tag filters -->
        <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
            <?php foreach ($all_tags as $tag): ?>
                <a href="?filter=<?= $filter; ?>&tag=<?= $tag['id']; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>"
                   class="media-tag-filter <?= $tag_filter == $tag['id'] ? 'active' : ''; ?>"
                   style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                    <?= htmlspecialchars($tag['name']); ?>
                </a>
            <?php endforeach; ?>
            <?php if ($tag_filter): ?>
                <a href="?filter=<?= $filter; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-xs btn-outline">Clear tag</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Batch tagging bar -->
    <div id="batch-bar" class="batch-bar" style="display: none;">
        <div class="batch-info">
            <span id="batch-count">0</span> selected
            <button onclick="clearSelection()" class="btn btn-xs btn-outline">Clear</button>
        </div>
        <div class="batch-tags">
            <?php foreach ($all_tags as $tag): ?>
                <button type="button" class="batch-tag-btn" data-tag-id="<?= $tag['id']; ?>" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>" onclick="batchTag(<?= $tag['id']; ?>)">
                    + <?= htmlspecialchars($tag['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($media_files)): ?>
        <p class="admin-muted-text">No files yet. Upload one above.</p>
    <?php else: ?>
        <div class="admin-media-grid">
            <?php foreach ($media_files as $media):
                $mediaTagIds = $media['tag_ids'] ? explode(',', $media['tag_ids']) : [];
            ?>
                <div class="admin-media-card" data-media-id="<?= $media['id']; ?>">
                    <!-- Selection checkbox -->
                    <label class="media-select-checkbox">
                        <input type="checkbox" onchange="toggleSelection(<?= $media['id']; ?>, this.checked)">
                    </label>
                    <!-- Preview with hover overlay -->
                    <div class="admin-media-preview" onclick="toggleSelection(<?= $media['id']; ?>)">
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
                            <span class="admin-media-icon">
                                <?= $media['file_type'] === 'video' ? '🎥' : ($media['file_type'] === 'audio' ? '🎵' : '📄'); ?>
                            </span>
                        <?php endif; ?>
                        <div class="admin-media-overlay" onclick="event.stopPropagation();">
                            <?php if ($media['file_type'] === 'image'): ?>
                            <button onclick="rotateImage(<?= $media['id']; ?>, 'left', this)" class="btn btn-xs" title="Rotate left">↺</button>
                            <button onclick="rotateImage(<?= $media['id']; ?>, 'right', this)" class="btn btn-xs" title="Rotate right">↻</button>
                            <?php endif; ?>
                            <button onclick="navigator.clipboard.writeText('https://alivechur.ch/<?= htmlspecialchars($media['file_path']); ?>'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 1000);" class="btn btn-xs">Copy</button>
                            <a href="?delete=<?= $media['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                        </div>
                    </div>
                    <!-- Quick tags -->
                    <div class="quick-tags" onclick="event.stopPropagation();">
                        <?php foreach ($all_tags as $tag):
                            $isActive = in_array($tag['id'], $mediaTagIds);
                        ?>
                            <button type="button"
                                    class="quick-tag <?= $isActive ? 'active' : ''; ?>"
                                    data-tag-id="<?= $tag['id']; ?>"
                                    data-media-id="<?= $media['id']; ?>"
                                    style="--tag-color: <?= htmlspecialchars($tag['color']); ?>"
                                    onclick="quickTag(<?= $media['id']; ?>, <?= $tag['id']; ?>, this)"
                                    title="<?= htmlspecialchars($tag['name']); ?>">
                                <?= htmlspecialchars(substr($tag['name'], 0, 1)); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <!-- Compact info -->
                    <div class="admin-media-info">
                        <span class="admin-media-name" title="<?= htmlspecialchars($media['original_filename']); ?>"><?= htmlspecialchars($media['original_filename']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Selection state
let selectedMedia = new Set();

function toggleSelection(mediaId, checked) {
    const card = document.querySelector(`[data-media-id="${mediaId}"]`);
    const checkbox = card.querySelector('.media-select-checkbox input');

    if (checked === undefined) {
        checked = !checkbox.checked;
        checkbox.checked = checked;
    }

    if (checked) {
        selectedMedia.add(mediaId);
        card.classList.add('selected');
    } else {
        selectedMedia.delete(mediaId);
        card.classList.remove('selected');
    }

    updateBatchBar();
}

function clearSelection() {
    selectedMedia.forEach(id => {
        const card = document.querySelector(`[data-media-id="${id}"]`);
        if (card) {
            card.classList.remove('selected');
            card.querySelector('.media-select-checkbox input').checked = false;
        }
    });
    selectedMedia.clear();
    updateBatchBar();
}

function updateBatchBar() {
    const bar = document.getElementById('batch-bar');
    const count = document.getElementById('batch-count');

    if (selectedMedia.size > 0) {
        bar.style.display = 'flex';
        count.textContent = selectedMedia.size;
    } else {
        bar.style.display = 'none';
    }
}

// Quick tag single image
async function quickTag(mediaId, tagId, btn) {
    const isActive = btn.classList.contains('active');
    const action = isActive ? 'remove' : 'add';

    btn.style.opacity = '0.5';

    try {
        const response = await fetch('/admin/api/tag-media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                media_ids: [mediaId],
                tag_id: tagId,
                action: action
            })
        });

        const result = await response.json();

        if (result.success) {
            btn.classList.toggle('active');
        }
    } catch (error) {
        console.error('Tag error:', error);
    }

    btn.style.opacity = '1';
}

// Batch tag selected images
async function batchTag(tagId) {
    if (selectedMedia.size === 0) return;

    const mediaIds = Array.from(selectedMedia);

    try {
        const response = await fetch('/admin/api/tag-media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                media_ids: mediaIds,
                tag_id: tagId,
                action: 'add'
            })
        });

        const result = await response.json();

        if (result.success) {
            // Update UI for all selected cards
            mediaIds.forEach(id => {
                const card = document.querySelector(`[data-media-id="${id}"]`);
                const tagBtn = card.querySelector(`[data-tag-id="${tagId}"]`);
                if (tagBtn) tagBtn.classList.add('active');
            });

            // Flash success
            const bar = document.getElementById('batch-bar');
            bar.style.background = '#10b981';
            setTimeout(() => bar.style.background = '', 300);
        }
    } catch (error) {
        console.error('Batch tag error:', error);
    }
}

// Track rotation state per image
const imageRotations = {};

// Rotate image
async function rotateImage(mediaId, direction, btn) {
    const card = document.querySelector(`[data-media-id="${mediaId}"]`);
    const img = card.querySelector('img');

    if (!img) return;

    // Initialize rotation tracking
    if (!imageRotations[mediaId]) {
        imageRotations[mediaId] = 0;
    }

    // Calculate new rotation
    const rotateAmount = direction === 'left' ? -90 : 90;
    imageRotations[mediaId] += rotateAmount;

    // Apply immediate visual rotation with smooth transition
    img.style.transition = 'transform 0.3s ease';
    img.style.transform = `rotate(${imageRotations[mediaId]}deg)`;

    // Disable buttons during request
    const buttons = card.querySelectorAll('.admin-media-overlay button');
    buttons.forEach(b => b.disabled = true);

    try {
        const response = await fetch('/admin/api/rotate-media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                media_id: mediaId,
                direction: direction
            })
        });

        const result = await response.json();

        if (result.success) {
            // Reset visual rotation and load the actual rotated image
            setTimeout(async () => {
                const currentSrc = img.src.split('?')[0];

                try {
                    // Fetch the image with cache bypass
                    const imgResponse = await fetch(currentSrc, {
                        cache: 'reload',
                        headers: { 'Cache-Control': 'no-cache' }
                    });
                    const blob = await imgResponse.blob();
                    const blobUrl = URL.createObjectURL(blob);

                    // Update the image with blob URL (guaranteed fresh)
                    img.style.transition = 'none';
                    img.style.transform = 'rotate(0deg)';
                    imageRotations[mediaId] = 0;
                    img.src = blobUrl;

                    // Clean up old blob URL after a delay
                    setTimeout(() => URL.revokeObjectURL(blobUrl), 5000);
                } catch (e) {
                    // Fallback: use cache buster URL
                    const newSrc = currentSrc + '?v=' + Date.now();
                    img.style.transition = 'none';
                    img.style.transform = 'rotate(0deg)';
                    imageRotations[mediaId] = 0;
                    img.src = newSrc;
                }
            }, 300);
        } else {
            // Revert visual rotation on error
            imageRotations[mediaId] -= rotateAmount;
            img.style.transform = `rotate(${imageRotations[mediaId]}deg)`;
            console.error('Rotate error:', result.error);
            alert('Failed to rotate: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        // Revert visual rotation on error
        imageRotations[mediaId] -= rotateAmount;
        img.style.transform = `rotate(${imageRotations[mediaId]}deg)`;
        console.error('Rotate error:', error);
        alert('Failed to rotate image: ' + error.message);
    }

    // Re-enable buttons
    buttons.forEach(b => b.disabled = false);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
