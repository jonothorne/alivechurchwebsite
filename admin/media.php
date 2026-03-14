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

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $upload_dir = __DIR__ . '/../uploads/';

        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['file'];
        $original_filename = basename($file['name']);
        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

        // Allowed file types
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'mp3', 'mp4', 'mov', 'avi', 'doc', 'docx', 'xls', 'xlsx'];

        if (!in_array($file_ext, $allowed)) {
            $error = 'File type not allowed';
        } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
            $error = 'File size exceeds 50MB limit';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error occurred';
        } else {
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

                        $success = 'File uploaded and optimized! Saved ' . ($processingResult['savings_formatted'] ?? '0 B');
                    } elseif ($processingResult['queued'] ?? false) {
                        $success = 'File uploaded! Optimization queued for background processing.';
                    } else {
                        $success = 'File uploaded successfully';
                    }
                } else {
                    $success = 'File uploaded successfully';
                }

                log_activity($_SESSION['admin_user_id'], 'upload', 'media', $mediaId, 'Uploaded: ' . $original_filename);
            } else {
                $error = 'Failed to save file';
            }
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

// Fetch all media
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'all') {
    $media_files = $pdo->query("SELECT m.*, u.username FROM media m LEFT JOIN users u ON m.uploaded_by = u.id ORDER BY m.created_at DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT m.*, u.username FROM media m LEFT JOIN users u ON m.uploaded_by = u.id WHERE m.file_type = ? ORDER BY m.created_at DESC");
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

<!-- Compact Upload -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Upload</h3>
    </div>
    <form method="post" enctype="multipart/form-data" style="display: flex; gap: 0.5rem; align-items: center;">
        <?= csrf_field(); ?>
        <input type="file" name="file" required accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx" style="flex: 1;">
        <button type="submit" class="btn btn-sm btn-primary">Upload</button>
    </form>
</div>

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
                            <img src="/<?= htmlspecialchars($media['file_path']); ?>" alt="">
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
