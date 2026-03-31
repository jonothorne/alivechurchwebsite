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
        }
    }
}

// Handle Delete Tag
if (isset($_GET['delete_tag']) && is_numeric($_GET['delete_tag'])) {
    $tagId = (int)$_GET['delete_tag'];
    $pdo->prepare("DELETE FROM media_tags WHERE id = ?")->execute([$tagId]);
    $success = 'Tag deleted';
}

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
                    $processingResult = $processor->process($upload_path, $imageType, false);

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

// Get media for editing
$edit_media = null;
$edit_media_tags = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_media = $stmt->fetch();

    if ($edit_media) {
        $tagStmt = $pdo->prepare("SELECT tag_id FROM media_tag_assignments WHERE media_id = ?");
        $tagStmt->execute([$_GET['edit']]);
        $edit_media_tags = array_column($tagStmt->fetchAll(), 'tag_id');
    }
}
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

<!-- Edit Metadata Form -->
<?php if ($edit_media): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Edit: <?= htmlspecialchars($edit_media['original_filename']); ?></h3>
        <a href="/adminnew/media?filter=<?= htmlspecialchars($filter); ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Cancel</a>
    </div>
    <div class="admin-card-body">
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="edit_meta" value="1">
            <input type="hidden" name="id" value="<?= $edit_media['id']; ?>">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
                <div style="flex: 1; min-width: 200px;">
                    <label class="admin-form-label">Alt Text</label>
                    <input type="text" name="alt_text" value="<?= htmlspecialchars($edit_media['alt_text'] ?? ''); ?>" placeholder="Image description" class="admin-form-input">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label class="admin-form-label">Caption</label>
                    <input type="text" name="caption" value="<?= htmlspecialchars($edit_media['caption'] ?? ''); ?>" placeholder="Optional caption" class="admin-form-input">
                </div>
            </div>
            <div style="margin-bottom: 1rem;">
                <label class="admin-form-label">Tags</label>
                <div class="tag-selector">
                    <?php foreach ($all_tags as $tag): ?>
                        <label class="tag-checkbox" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                            <input type="checkbox" name="tags[]" value="<?= $tag['id']; ?>" <?= in_array($tag['id'], $edit_media_tags) ? 'checked' : ''; ?>>
                            <span><?= htmlspecialchars($tag['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Save</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Tag Management -->
<div class="admin-card">
    <details>
        <summary class="admin-card-header" style="cursor: pointer; list-style: none;">
            <h3 class="admin-card-title">Manage Tags</h3>
        </summary>
        <div class="admin-card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <form method="post" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <?= csrf_field(); ?>
                <input type="hidden" name="add_tag" value="1">
                <div>
                    <label class="admin-form-label">New Tag</label>
                    <input type="text" name="tag_name" placeholder="Tag name" required class="admin-form-input" style="width: 140px;">
                </div>
                <div>
                    <label class="admin-form-label">Color</label>
                    <input type="color" name="tag_color" value="#6b7280" style="width: 40px; height: 36px; padding: 2px; border-radius: var(--admin-radius-sm); border: 1px solid var(--admin-border);">
                </div>
                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Add</button>
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
            <a href="/adminnew/media?filter=all<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="/adminnew/media?filter=image<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'image' ? 'active' : ''; ?>">Images</a>
            <a href="/adminnew/media?filter=video<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'video' ? 'active' : ''; ?>">Video</a>
            <a href="/adminnew/media?filter=audio<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'audio' ? 'active' : ''; ?>">Audio</a>
            <a href="/adminnew/media?filter=document<?= $tag_filter ? '&tag=' . $tag_filter : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-filter-tab <?= $filter === 'document' ? 'active' : ''; ?>">Docs</a>
        </div>
        <form method="get" style="display: flex; gap: 0.5rem;">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
            <?php if ($tag_filter): ?><input type="hidden" name="tag" value="<?= htmlspecialchars($tag_filter); ?>"><?php endif; ?>
            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search files..." class="admin-form-input" style="width: 200px;">
            <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Search</button>
            <?php if ($search): ?>
                <a href="/adminnew/media?filter=<?= $filter; ?><?= $tag_filter ? '&tag=' . $tag_filter : ''; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tag filters -->
    <?php if (!empty($all_tags)): ?>
    <div style="padding: 0.5rem var(--admin-spacing-lg); border-bottom: 1px solid var(--admin-border); display: flex; gap: 0.25rem; flex-wrap: wrap; align-items: center;">
        <?php foreach ($all_tags as $tag): ?>
            <a href="/adminnew/media?filter=<?= $filter; ?>&tag=<?= $tag['id']; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>"
               class="media-tag-filter <?= $tag_filter == $tag['id'] ? 'active' : ''; ?>"
               style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                <?= htmlspecialchars($tag['name']); ?>
            </a>
        <?php endforeach; ?>
        <?php if ($tag_filter): ?>
            <a href="/adminnew/media?filter=<?= $filter; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="admin-btn admin-btn-sm admin-btn-secondary" style="font-size: 0.75rem; padding: 0.15rem 0.5rem;">Clear tag</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Batch tagging bar -->
    <div id="batch-bar" class="batch-bar" style="display: none;">
        <div class="batch-info">
            <span id="batch-count">0</span> selected
            <button id="clear-selection-btn" class="admin-btn admin-btn-sm" style="background: rgba(255,255,255,0.2); color: white; border-color: rgba(255,255,255,0.3);">Clear</button>
        </div>
        <div class="batch-tags">
            <?php foreach ($all_tags as $tag): ?>
                <button type="button" class="batch-tag-btn" data-tag-id="<?= $tag['id']; ?>" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                    + <?= htmlspecialchars($tag['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
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
            <?php foreach ($media_files as $media):
                $mediaTagIds = $media['tag_ids'] ? explode(',', $media['tag_ids']) : [];
            ?>
                <div class="media-card" data-media-id="<?= $media['id']; ?>">
                    <!-- Selection checkbox -->
                    <label class="media-select-checkbox">
                        <input type="checkbox" class="media-checkbox" data-media-id="<?= $media['id']; ?>">
                    </label>
                    <div class="media-preview" data-media-id="<?= $media['id']; ?>">
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
                            <?php if ($media['file_type'] === 'image'): ?>
                            <button class="admin-btn admin-btn-sm rotate-btn" data-media-id="<?= $media['id']; ?>" data-direction="left" title="Rotate left">&#8634;</button>
                            <button class="admin-btn admin-btn-sm rotate-btn" data-media-id="<?= $media['id']; ?>" data-direction="right" title="Rotate right">&#8635;</button>
                            <?php endif; ?>
                            <button class="admin-btn admin-btn-sm copy-url-btn" data-url="https://alivechur.ch/<?= htmlspecialchars($media['file_path']); ?>">Copy</button>
                            <a href="/adminnew/media?filter=<?= $filter; ?>&edit=<?= $media['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                            <a href="/adminnew/media?filter=<?= $filter; ?>&delete=<?= $media['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this file?')">&#215;</a>
                        </div>
                    </div>
                    <!-- Quick tags -->
                    <?php if (!empty($all_tags)): ?>
                    <div class="quick-tags">
                        <?php foreach ($all_tags as $tag):
                            $isActive = in_array($tag['id'], $mediaTagIds);
                        ?>
                            <button type="button"
                                    class="quick-tag <?= $isActive ? 'active' : ''; ?>"
                                    data-tag-id="<?= $tag['id']; ?>"
                                    data-media-id="<?= $media['id']; ?>"
                                    style="--tag-color: <?= htmlspecialchars($tag['color']); ?>"
                                    title="<?= htmlspecialchars($tag['name']); ?>">
                                <?= htmlspecialchars(mb_substr($tag['name'], 0, 1)); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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
    border-radius: var(--admin-radius-sm);
    cursor: pointer;
    font-size: 0.85rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
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
    border-radius: var(--admin-radius-sm);
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
    padding: 0.2rem 0.5rem;
    border-radius: var(--admin-radius-sm);
    font-size: 0.75rem;
    text-decoration: none;
    background: var(--admin-bg);
    color: var(--admin-text);
    border: 1px solid var(--admin-border);
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
    border-radius: var(--admin-radius-sm);
    border: 1px solid var(--admin-border);
    background: var(--admin-bg);
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    color: var(--admin-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
.media-card:hover .media-select-checkbox,
.media-card.selected .media-select-checkbox {
    opacity: 1;
}
.media-select-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.media-card {
    position: relative;
}
.media-card.selected {
    outline: 2px solid var(--current-app-color);
    outline-offset: 2px;
}

/* Batch bar */
.batch-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: var(--current-app-color);
    color: white;
    border-radius: var(--admin-radius);
    margin: 0.5rem var(--admin-spacing-lg);
    transition: background 0.3s;
}
.batch-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}
.batch-tags {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}
.batch-tag-btn {
    padding: 0.25rem 0.5rem;
    border-radius: var(--admin-radius-sm);
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
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    opacity: 0;
    transition: opacity var(--admin-transition);
    padding: 0.5rem;
}
.media-card:hover .media-overlay {
    opacity: 1;
}
.media-overlay .admin-btn {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    background: rgba(255,255,255,0.15);
    color: white;
    border-color: rgba(255,255,255,0.3);
}
.media-overlay .admin-btn:hover {
    background: rgba(255,255,255,0.3);
}
.media-overlay .admin-btn-danger {
    background: var(--admin-danger);
    border-color: var(--admin-danger);
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

/* Toast notifications */
.admin-toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    padding: 0.75rem 1rem;
    border-radius: var(--admin-radius);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 9999;
    box-shadow: var(--admin-shadow-lg);
    animation: toastIn 0.3s ease;
}
.admin-toast-error {
    background: var(--admin-danger);
    color: white;
}
.admin-toast-success {
    background: var(--admin-success);
    color: white;
}
.admin-toast button {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0 0.25rem;
}
@keyframes toastIn {
    from { transform: translateY(1rem); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
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

<script <?= csp_nonce(); ?>>
// Toast notification
function showToast(message, type) {
    document.querySelectorAll('.admin-toast').forEach(function(t) { t.remove(); });
    var toast = document.createElement('div');
    toast.className = 'admin-toast admin-toast-' + (type || 'error');
    toast.innerHTML = '<span>' + message + '</span><button type="button" onclick="this.parentElement.remove()">&times;</button>';
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 5000);
}

// Selection state
var selectedMedia = new Set();

function toggleSelection(mediaId, checked) {
    var card = document.querySelector('[data-media-id="' + mediaId + '"]');
    var checkbox = card.querySelector('.media-select-checkbox input');

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
    selectedMedia.forEach(function(id) {
        var card = document.querySelector('[data-media-id="' + id + '"]');
        if (card) {
            card.classList.remove('selected');
            card.querySelector('.media-select-checkbox input').checked = false;
        }
    });
    selectedMedia.clear();
    updateBatchBar();
}

function updateBatchBar() {
    var bar = document.getElementById('batch-bar');
    var count = document.getElementById('batch-count');

    if (selectedMedia.size > 0) {
        bar.style.display = 'flex';
        count.textContent = selectedMedia.size;
    } else {
        bar.style.display = 'none';
    }
}

// Quick tag single image
async function quickTag(mediaId, tagId, btn) {
    var isActive = btn.classList.contains('active');
    var action = isActive ? 'remove' : 'add';

    btn.style.opacity = '0.5';

    try {
        var response = await fetch('/admin/api/tag-media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ media_ids: [mediaId], tag_id: tagId, action: action })
        });

        var result = await response.json();
        if (result.success) {
            btn.classList.toggle('active');
        }
    } catch (error) {
        showToast('Failed to update tag', 'error');
    }

    btn.style.opacity = '1';
}

// Batch tag selected images
async function batchTag(tagId) {
    if (selectedMedia.size === 0) return;

    var mediaIds = Array.from(selectedMedia);

    try {
        var response = await fetch('/admin/api/tag-media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ media_ids: mediaIds, tag_id: tagId, action: 'add' })
        });

        var result = await response.json();

        if (result.success) {
            mediaIds.forEach(function(id) {
                var card = document.querySelector('[data-media-id="' + id + '"]');
                var tagBtn = card.querySelector('[data-tag-id="' + tagId + '"]');
                if (tagBtn) tagBtn.classList.add('active');
            });

            var bar = document.getElementById('batch-bar');
            bar.style.background = 'var(--admin-success)';
            setTimeout(function() { bar.style.background = ''; }, 300);
        }
    } catch (error) {
        showToast('Batch tag failed', 'error');
    }
}

// Rotation
var imageRotations = {};

async function rotateImage(mediaId, direction, btn) {
    var card = document.querySelector('[data-media-id="' + mediaId + '"]');
    var img = card.querySelector('img');
    if (!img) return;

    if (!imageRotations[mediaId]) imageRotations[mediaId] = 0;

    var rotateAmount = direction === 'left' ? -90 : 90;
    imageRotations[mediaId] += rotateAmount;

    img.style.transition = 'transform 0.3s ease';
    img.style.transform = 'rotate(' + imageRotations[mediaId] + 'deg)';

    var buttons = card.querySelectorAll('.media-overlay button, .media-overlay a');
    buttons.forEach(function(b) { b.style.pointerEvents = 'none'; });

    try {
        var response = await fetch('/admin/api/rotate-media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ media_id: mediaId, direction: direction })
        });

        var result = await response.json();

        if (result.success) {
            setTimeout(async function() {
                var currentSrc = img.src.split('?')[0];
                try {
                    var imgResponse = await fetch(currentSrc, { cache: 'reload', headers: { 'Cache-Control': 'no-cache' } });
                    var blob = await imgResponse.blob();
                    var blobUrl = URL.createObjectURL(blob);
                    img.style.transition = 'none';
                    img.style.transform = 'rotate(0deg)';
                    imageRotations[mediaId] = 0;
                    img.src = blobUrl;
                    setTimeout(function() { URL.revokeObjectURL(blobUrl); }, 5000);
                } catch (e) {
                    img.style.transition = 'none';
                    img.style.transform = 'rotate(0deg)';
                    imageRotations[mediaId] = 0;
                    img.src = currentSrc + '?v=' + Date.now();
                }
            }, 300);
        } else {
            imageRotations[mediaId] -= rotateAmount;
            img.style.transform = 'rotate(' + imageRotations[mediaId] + 'deg)';
            showToast('Failed to rotate: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        imageRotations[mediaId] -= rotateAmount;
        img.style.transform = 'rotate(' + imageRotations[mediaId] + 'deg)';
        showToast('Failed to rotate image', 'error');
    }

    buttons.forEach(function(b) { b.style.pointerEvents = ''; });
}

document.addEventListener('DOMContentLoaded', function() {
    var dropzone = document.getElementById('upload-dropzone');
    var fileInput = document.getElementById('file-input');
    var progressDiv = document.getElementById('upload-progress');
    var progressBar = document.getElementById('upload-progress-bar');
    var progressText = document.getElementById('upload-progress-text');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(function(eventName) {
        dropzone.addEventListener(eventName, function() { dropzone.classList.add('dragover'); }, false);
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
        dropzone.addEventListener(eventName, function() { dropzone.classList.remove('dragover'); }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
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
        var formData = new FormData();
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        for (var i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        progressDiv.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.style.background = '';

        var totalSize = Array.from(files).reduce(function(sum, f) { return sum + f.size; }, 0);
        var totalSizeMB = (totalSize / (1024 * 1024)).toFixed(1);
        progressText.textContent = 'Uploading ' + files.length + ' file' + (files.length > 1 ? 's' : '') + ' (' + totalSizeMB + ' MB)...';

        var xhr = new XMLHttpRequest();
        xhr.timeout = 600000;

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                var uploadedMB = (e.loaded / (1024 * 1024)).toFixed(1);
                progressText.textContent = percent < 100
                    ? 'Uploading... ' + percent + '% (' + uploadedMB + ' / ' + totalSizeMB + ' MB)'
                    : 'Processing images... This may take a while for large files.';
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                progressText.textContent = 'Upload complete! Refreshing...';
                setTimeout(function() { window.location.reload(); }, 500);
            } else {
                var errorMsg = 'Upload failed';
                if (xhr.status === 413) errorMsg = 'Files too large. Try uploading fewer files at once.';
                else if (xhr.status === 504 || xhr.status === 408) errorMsg = 'Server timeout. Try uploading fewer files.';
                progressText.textContent = errorMsg;
                progressBar.style.background = '#ef4444';
            }
        });

        xhr.addEventListener('error', function() {
            progressText.textContent = 'Network error. Check your connection.';
            progressBar.style.background = '#ef4444';
        });

        xhr.addEventListener('timeout', function() {
            progressText.textContent = 'Upload timed out. Try uploading fewer/smaller files.';
            progressBar.style.background = '#ef4444';
        });

        xhr.open('POST', window.location.href);
        xhr.send(formData);
    }

    // Event delegation for media grid
    var mediaGrid = document.querySelector('.media-grid');
    if (mediaGrid) {
        mediaGrid.addEventListener('click', function(e) {
            // Copy URL button
            var copyBtn = e.target.closest('.copy-url-btn');
            if (copyBtn) {
                e.stopPropagation();
                navigator.clipboard.writeText(copyBtn.dataset.url).then(function() {
                    copyBtn.textContent = 'Copied!';
                    setTimeout(function() { copyBtn.textContent = 'Copy'; }, 1000);
                });
                return;
            }

            // Rotate button
            var rotateBtn = e.target.closest('.rotate-btn');
            if (rotateBtn) {
                e.stopPropagation();
                e.preventDefault();
                rotateImage(parseInt(rotateBtn.dataset.mediaId), rotateBtn.dataset.direction, rotateBtn);
                return;
            }

            // Quick tag button
            var quickTagBtn = e.target.closest('.quick-tag');
            if (quickTagBtn) {
                e.stopPropagation();
                quickTag(parseInt(quickTagBtn.dataset.mediaId), parseInt(quickTagBtn.dataset.tagId), quickTagBtn);
                return;
            }

            // Stop propagation on overlay and quick-tags
            if (e.target.closest('.media-overlay') || e.target.closest('.quick-tags')) {
                return;
            }
        });

        // Handle checkbox changes
        mediaGrid.addEventListener('change', function(e) {
            var checkbox = e.target.closest('.media-checkbox');
            if (checkbox) {
                toggleSelection(parseInt(checkbox.dataset.mediaId), checkbox.checked);
            }
        });
    }

    // Clear selection button
    var clearBtn = document.getElementById('clear-selection-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearSelection);
    }

    // Batch tag buttons
    var batchTags = document.querySelector('.batch-tags');
    if (batchTags) {
        batchTags.addEventListener('click', function(e) {
            var btn = e.target.closest('.batch-tag-btn');
            if (btn) {
                batchTag(parseInt(btn.dataset.tagId));
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
