<?php
/**
 * Upload Diagnostics - Check files vs database
 */
$page_title = 'Upload Diagnostics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$uploadsDir = __DIR__ . '/../../uploads/';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Upload Diagnostics</h3>
    </div>
    <div style="padding: 1rem;">

        <h4>Server Path</h4>
        <p><code><?= realpath($uploadsDir) ?: $uploadsDir . ' (does not exist!)'; ?></code></p>

        <h4>Files on Disk (first 30)</h4>
        <?php
        if (is_dir($uploadsDir)) {
            $files = array_diff(scandir($uploadsDir), ['.', '..']);
            $files = array_slice($files, 0, 30);
            if (empty($files)) {
                echo '<p style="color: red;">No files found in uploads folder!</p>';
            } else {
                echo '<ul style="font-size: 0.85rem; max-height: 300px; overflow-y: auto;">';
                foreach ($files as $file) {
                    echo '<li>' . htmlspecialchars($file) . '</li>';
                }
                echo '</ul>';
                echo '<p>Total files: ' . count(array_diff(scandir($uploadsDir), ['.', '..'])) . '</p>';
            }
        } else {
            echo '<p style="color: red;">Uploads directory does not exist!</p>';
        }
        ?>

        <h4>Images in Database</h4>
        <?php
        $images = $pdo->query("SELECT id, filename, file_path FROM media WHERE file_type = 'image' LIMIT 30")->fetchAll();
        if (empty($images)) {
            echo '<p>No images in database.</p>';
        } else {
            echo '<table class="admin-table" style="font-size: 0.85rem;">';
            echo '<tr><th>ID</th><th>Filename</th><th>Exists on Disk?</th></tr>';
            foreach ($images as $img) {
                $exists = file_exists($uploadsDir . $img['filename']);
                echo '<tr>';
                echo '<td>' . $img['id'] . '</td>';
                echo '<td>' . htmlspecialchars($img['filename']) . '</td>';
                echo '<td style="color: ' . ($exists ? 'green' : 'red') . ';">' . ($exists ? 'Yes' : 'No') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
