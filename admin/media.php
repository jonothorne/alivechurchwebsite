<?php
$page_title = 'Media Library';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

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

                // Insert into database
                $stmt = $pdo->prepare("INSERT INTO media (filename, original_filename, file_type, file_size, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $unique_filename,
                    $original_filename,
                    $file_type,
                    $file['size'],
                    'uploads/' . $unique_filename,
                    $_SESSION['admin_user_id']
                ]);

                log_activity($_SESSION['admin_user_id'], 'upload', 'media', $pdo->lastInsertId(), 'Uploaded: ' . $original_filename);
                $success = 'File uploaded successfully';
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
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Upload Card -->
<div class="card">
    <div class="card-header">
        <h2>Upload New Media</h2>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field(); ?>

        <div class="form-group">
            <label>Choose File</label>
            <input type="file" name="file" required accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx">
            <div class="form-help">
                Allowed: Images (JPG, PNG, GIF), Videos (MP4, MOV, AVI), Audio (MP3), Documents (PDF, DOC, XLS) - Max 50MB
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Upload File</button>
    </form>
</div>

<!-- Edit Metadata Modal (only if editing) -->
<?php if ($edit_media): ?>
<div class="card">
    <div class="card-header">
        <h2>Edit Media Information</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <input type="hidden" name="edit_meta" value="1">
        <input type="hidden" name="id" value="<?= $edit_media['id']; ?>">

        <div class="form-group">
            <label>File</label>
            <div style="padding: 0.75rem; background: #f1f5f9; border-radius: 0.5rem;">
                <strong><?= htmlspecialchars($edit_media['original_filename']); ?></strong>
                <br><small style="color: #64748b;"><?= strtoupper($edit_media['file_type']); ?> • <?= number_format($edit_media['file_size'] / 1024, 1); ?> KB</small>
            </div>
        </div>

        <div class="form-group">
            <label>Alt Text (for images)</label>
            <input type="text" name="alt_text" value="<?= htmlspecialchars($edit_media['alt_text'] ?? ''); ?>" placeholder="Describe the image for accessibility">
        </div>

        <div class="form-group">
            <label>Caption</label>
            <textarea name="caption" rows="2" placeholder="Optional caption or description"><?= htmlspecialchars($edit_media['caption'] ?? ''); ?></textarea>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/admin/media.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Filter Tabs -->
<div style="display: flex; gap: 1rem; margin: 2rem 0 1rem 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
    <a href="?filter=all" class="<?= $filter === 'all' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'all' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">All Files</a>
    <a href="?filter=image" class="<?= $filter === 'image' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'image' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">Images</a>
    <a href="?filter=video" class="<?= $filter === 'video' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'video' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">Videos</a>
    <a href="?filter=audio" class="<?= $filter === 'audio' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'audio' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">Audio</a>
    <a href="?filter=document" class="<?= $filter === 'document' ? 'btn btn-primary' : ''; ?>" style="<?= $filter === 'document' ? '' : 'text-decoration: none; color: #64748b; font-weight: 500;'; ?>">Documents</a>
</div>

<!-- Media Grid -->
<div class="card">
    <div class="card-header">
        <h2>Media Library</h2>
    </div>

    <?php if (empty($media_files)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🖼️</div>
            <h3>No media files yet</h3>
            <p>Upload your first file above</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
            <?php foreach ($media_files as $media): ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: white;">
                    <!-- Preview -->
                    <div style="height: 150px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if ($media['file_type'] === 'image'): ?>
                            <img src="/<?= htmlspecialchars($media['file_path']); ?>"
                                 alt="<?= htmlspecialchars($media['alt_text'] ?: $media['original_filename']); ?>"
                                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php elseif ($media['file_type'] === 'video'): ?>
                            <span style="font-size: 3rem;">🎥</span>
                        <?php elseif ($media['file_type'] === 'audio'): ?>
                            <span style="font-size: 3rem;">🎵</span>
                        <?php else: ?>
                            <span style="font-size: 3rem;">📄</span>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div style="padding: 1rem;">
                        <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($media['original_filename']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem;">
                            <?= strtoupper($media['file_type']); ?> • <?= number_format($media['file_size'] / 1024, 1); ?> KB
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 1rem;">
                            By <?= htmlspecialchars($media['username'] ?? 'Unknown'); ?><br>
                            <?= date('M j, Y', strtotime($media['created_at'])); ?>
                        </div>

                        <!-- URL Copy -->
                        <div style="margin-bottom: 0.75rem;">
                            <input type="text"
                                   value="/<?= htmlspecialchars($media['file_path']); ?>"
                                   readonly
                                   onclick="this.select(); document.execCommand('copy'); alert('URL copied!');"
                                   style="width: 100%; padding: 0.5rem; font-size: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.25rem; cursor: pointer; font-family: monospace;">
                        </div>

                        <!-- Actions -->
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?edit=<?= $media['id']; ?>" class="btn btn-sm btn-outline" style="flex: 1; text-align: center; padding: 0.5rem;">Edit</a>
                            <a href="?delete=<?= $media['id']; ?>" class="btn btn-sm btn-danger" style="flex: 1; text-align: center; padding: 0.5rem;" data-confirm-delete>Delete</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
