<?php
/**
 * Repair Image References
 *
 * Finds broken image references in the database and allows manual
 * mapping to renamed files.
 */

$page_title = 'Repair Image References';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$uploadsDir = __DIR__ . '/../../uploads/';

// All tables/columns that contain image URLs
$imageColumns = [
    'blog_posts' => ['featured_image', 'thumbnail'],
    'event_details' => ['image'],
    'groups_list' => ['image_url'],
    'ministries' => ['image_url'],
    'reading_plans' => ['cover_image'],
    'sermon_series' => ['image_url'],
    'sermons' => ['thumbnail_url'],
    'serve_opportunities' => ['image_url'],
    'users' => ['avatar'],
];

// Content columns that may have embedded images
$contentColumns = [
    'blog_posts' => ['content'],
    'content_blocks' => ['content'],
    'global_content' => ['content'],
    'pages' => ['content'],
    'bible_studies' => ['content'],
];

$success = '';
$error = '';

// Get all files in uploads directory
$uploadedFiles = [];
if (is_dir($uploadsDir)) {
    $files = scandir($uploadsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($uploadsDir . $file)) {
            // Skip variant files (thumbnails, etc) - only show main files
            if (!preg_match('/-(thumbnail|small|medium|large)\.[^.]+$/', $file) && !preg_match('/\.[^.]+\.webp$/', $file)) {
                $uploadedFiles[] = $file;
            }
        }
    }
    sort($uploadedFiles);
}

/**
 * Check if a URL points to a missing file
 */
function isImageMissing($url, $uploadsDir) {
    if (empty($url)) return false;
    if (strpos($url, '/uploads/') === false) return false;
    $filename = basename($url);
    return !file_exists($uploadsDir . $filename);
}

// Handle fix submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fix') {
    $fixes = $_POST['fixes'] ?? [];
    $fixCount = 0;

    foreach ($fixes as $fix) {
        if (empty($fix['new_file']) || $fix['new_file'] === '') continue;

        $table = $fix['table'];
        $column = $fix['column'];
        $id = $fix['id'];
        $oldFile = $fix['old_file'];
        $newFile = $fix['new_file'];
        $inContent = !empty($fix['in_content']);

        try {
            if ($inContent) {
                // Replace in HTML content - replace old filename with new
                $oldBaseName = pathinfo($oldFile, PATHINFO_FILENAME);
                $newBaseName = pathinfo($newFile, PATHINFO_FILENAME);

                $stmt = $pdo->prepare("UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE id = ?");
                $stmt->execute([$oldBaseName, $newBaseName, $id]);
            } else {
                // Direct URL column - replace full path
                $newUrl = '/uploads/' . $newFile;
                $stmt = $pdo->prepare("UPDATE `$table` SET `$column` = ? WHERE id = ?");
                $stmt->execute([$newUrl, $id]);
            }
            $fixCount++;
        } catch (PDOException $e) {
            $error = "Error updating $table.$column: " . $e->getMessage();
        }
    }

    if ($fixCount > 0) {
        $success = "Fixed $fixCount image reference(s)!";
    }
}

// Scan for broken references
$broken = [];
if (isset($_GET['scan']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check direct URL columns
    foreach ($imageColumns as $table => $columns) {
        foreach ($columns as $column) {
            try {
                $stmt = $pdo->query("SELECT * FROM `$table` WHERE `$column` LIKE '%/uploads/%'");
                while ($row = $stmt->fetch()) {
                    $url = $row[$column];
                    if (isImageMissing($url, $uploadsDir)) {
                        $broken[] = [
                            'table' => $table,
                            'column' => $column,
                            'id' => $row['id'],
                            'url' => $url,
                            'filename' => basename($url),
                            'in_content' => false
                        ];
                    }
                }
            } catch (PDOException $e) {
                // Table might not exist
            }
        }
    }

    // Check content columns for embedded images
    foreach ($contentColumns as $table => $columns) {
        foreach ($columns as $column) {
            try {
                $stmt = $pdo->query("SELECT * FROM `$table` WHERE `$column` LIKE '%/uploads/%'");
                while ($row = $stmt->fetch()) {
                    $content = $row[$column];
                    if (preg_match_all('/\/uploads\/([^"\'>\s]+)/i', $content, $matches)) {
                        foreach ($matches[1] as $filename) {
                            // Skip variant files in content check
                            $baseFilename = preg_replace('/-(thumbnail|small|medium|large)(\.[^.]+)$/', '$2', $filename);
                            if (!file_exists($uploadsDir . $baseFilename) && !file_exists($uploadsDir . $filename)) {
                                $broken[] = [
                                    'table' => $table,
                                    'column' => $column,
                                    'id' => $row['id'],
                                    'url' => '/uploads/' . $filename,
                                    'filename' => $filename,
                                    'in_content' => true
                                ];
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                // Table might not exist
            }
        }
    }

    // Deduplicate by unique combination
    $seen = [];
    $broken = array_filter($broken, function($item) use (&$seen) {
        $key = $item['table'] . '|' . $item['column'] . '|' . $item['id'] . '|' . $item['filename'];
        if (isset($seen[$key])) return false;
        $seen[$key] = true;
        return true;
    });
}
?>

<style <?= csp_nonce(); ?>>
.repair-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.repair-table th, .repair-table td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
.repair-table select { width: 100%; padding: 0.25rem; font-size: 0.8rem; }
.repair-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 0.5rem; vertical-align: middle; }
.file-preview { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; }
.select-with-preview { display: flex; align-items: center; gap: 0.5rem; }
.select-with-preview select { flex: 1; }
.select-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb; }
.available-files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.5rem; max-height: 400px; overflow-y: auto; padding: 1rem; background: #f9fafb; border-radius: 4px; margin-top: 1rem; }
.file-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 4px; padding: 0.5rem; text-align: center; cursor: pointer; }
.file-card:hover { border-color: #3b82f6; }
.file-card img { width: 50px; height: 50px; object-fit: cover; border-radius: 2px; display: block; margin: 0 auto; }
.file-card small { display: block; margin-top: 0.25rem; font-size: 0.7rem; color: #6b7280; word-break: break-all; }
</style>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Repair Image References</h3>
    </div>

    <div style="padding: 1rem;">
        <?php if ($success): ?>
            <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!isset($_GET['scan']) && empty($broken)): ?>
            <p>This tool scans the database for broken image references and lets you select the correct replacement file.</p>

            <form method="GET">
                <input type="hidden" name="scan" value="1">
                <button type="submit" class="btn btn-primary">Scan for Broken References</button>
            </form>

            <h4 style="margin-top: 2rem;">Available Files in Uploads (<?= count($uploadedFiles); ?>)</h4>
            <div style="max-height: 300px; overflow-y: auto; font-size: 0.85rem; background: #f9fafb; padding: 1rem; border-radius: 4px;">
                <?php foreach (array_slice($uploadedFiles, 0, 100) as $file): ?>
                    <div class="file-preview">
                        <img src="/uploads/<?= htmlspecialchars($file); ?>" class="repair-thumb" loading="lazy" data-hide-on-error>
                        <?= htmlspecialchars($file); ?>
                    </div>
                <?php endforeach; ?>
                <?php if (count($uploadedFiles) > 100): ?>
                    <div style="color: #6b7280; margin-top: 0.5rem;">... and <?= count($uploadedFiles) - 100; ?> more</div>
                <?php endif; ?>
            </div>

        <?php elseif (!empty($broken)): ?>
            <div class="admin-alert admin-alert-warning">
                Found <?= count($broken); ?> broken image reference(s)
            </div>

            <p>Select the correct replacement file for each broken reference, then click "Apply Fixes".</p>

            <form method="POST">
                <input type="hidden" name="action" value="fix">

                <table class="repair-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Table</th>
                            <th style="width: 100px;">Column</th>
                            <th style="width: 50px;">ID</th>
                            <th>Missing File</th>
                            <th style="width: 300px;">Replace With</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($broken as $i => $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['table']); ?></td>
                                <td><?= htmlspecialchars($item['column']); ?><?= $item['in_content'] ? ' <small>(HTML)</small>' : ''; ?></td>
                                <td><?= htmlspecialchars($item['id']); ?></td>
                                <td><small style="color: #dc2626;"><?= htmlspecialchars($item['filename']); ?></small></td>
                                <td>
                                    <input type="hidden" name="fixes[<?= $i; ?>][table]" value="<?= htmlspecialchars($item['table']); ?>">
                                    <input type="hidden" name="fixes[<?= $i; ?>][column]" value="<?= htmlspecialchars($item['column']); ?>">
                                    <input type="hidden" name="fixes[<?= $i; ?>][id]" value="<?= htmlspecialchars($item['id']); ?>">
                                    <input type="hidden" name="fixes[<?= $i; ?>][old_file]" value="<?= htmlspecialchars($item['filename']); ?>">
                                    <input type="hidden" name="fixes[<?= $i; ?>][in_content]" value="<?= $item['in_content'] ? '1' : '0'; ?>">
                                    <div class="select-with-preview">
                                        <img src="" class="select-preview" id="preview-<?= $i; ?>" width="50" height="50" style="display: none;">
                                        <select name="fixes[<?= $i; ?>][new_file]" data-action="update-preview" data-preview-id="preview-<?= $i; ?>">
                                            <option value="">-- Select replacement --</option>
                                            <?php
                                            // Try to find likely matches (same extension)
                                            $ext = strtolower(pathinfo($item['filename'], PATHINFO_EXTENSION));
                                            $matches = [];
                                            $others = [];
                                            foreach ($uploadedFiles as $file) {
                                                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === $ext) {
                                                    $matches[] = $file;
                                                } else {
                                                    $others[] = $file;
                                                }
                                            }
                                            ?>
                                            <?php if (!empty($matches)): ?>
                                                <optgroup label="Same extension (<?= strtoupper($ext); ?>)">
                                                    <?php foreach ($matches as $file): ?>
                                                        <option value="<?= htmlspecialchars($file); ?>"><?= htmlspecialchars($file); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                            <?php if (!empty($others)): ?>
                                                <optgroup label="Other files">
                                                    <?php foreach ($others as $file): ?>
                                                        <option value="<?= htmlspecialchars($file); ?>"><?= htmlspecialchars($file); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">Apply Fixes</button>
                    <a href="?scan=1" class="btn btn-outline">Rescan</a>
                    <a href="" class="btn btn-outline">Start Over</a>
                </div>
            </form>

            <h4 style="margin-top: 2rem;">Available Files Reference</h4>
            <p style="font-size: 0.875rem; color: #6b7280;">Click a thumbnail to copy the filename, then paste it into the search/select above.</p>
            <div class="available-files-grid">
                <?php foreach ($uploadedFiles as $file):
                    // Use thumbnail variant if available
                    $thumbName = pathinfo($file, PATHINFO_FILENAME) . '-thumbnail.' . pathinfo($file, PATHINFO_EXTENSION);
                    $thumbSrc = file_exists($uploadsDir . $thumbName) ? $thumbName : $file;
                ?>
                    <div class="file-card" data-action="copy-filename" data-filename="<?= htmlspecialchars($file, ENT_QUOTES); ?>">
                        <img src="/uploads/<?= htmlspecialchars($thumbSrc); ?>" width="50" height="50" loading="lazy" data-fallback-on-error="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22><rect fill=%22%23f3f4f6%22 width=%2250%22 height=%2250%22/><text x=%2225%22 y=%2225%22 text-anchor=%22middle%22 fill=%22%239ca3af%22 font-size=%228%22>?</text></svg>">
                        <small><?= htmlspecialchars($file); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <script <?= csp_nonce(); ?>>
            // Toast notification
            function showAdminToast(message, type = 'success') {
                document.querySelectorAll('.admin-toast').forEach(t => t.remove());
                const toast = document.createElement('div');
                toast.className = `admin-toast admin-toast-${type}`;
                toast.innerHTML = `<span>${type === 'error' ? '⚠️' : '✅'} ${message}</span><button type="button" onclick="this.parentElement.remove()">&times;</button>`;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }

            function updatePreview(select, previewId) {
                const preview = document.getElementById(previewId);
                if (select.value) {
                    // Try thumbnail variant first
                    const parts = select.value.split('.');
                    const ext = parts.pop();
                    const base = parts.join('.');
                    const thumbSrc = '/uploads/' + base + '-thumbnail.' + ext;
                    const origSrc = '/uploads/' + select.value;

                    // Try thumbnail, fall back to original
                    preview.onerror = function() { this.src = origSrc; this.onerror = null; };
                    preview.src = thumbSrc;
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            }

            function copyFilename(filename) {
                navigator.clipboard.writeText(filename).then(() => {
                    showAdminToast('Copied: ' + filename);
                });
            }

            // Event delegation for CSP compliance
            document.addEventListener('change', function(e) {
                if (e.target.matches('[data-action="update-preview"]')) {
                    updatePreview(e.target, e.target.dataset.previewId);
                }
            });

            document.addEventListener('click', function(e) {
                const card = e.target.closest('[data-action="copy-filename"]');
                if (card) {
                    copyFilename(card.dataset.filename);
                }
            });
            </script>

        <?php else: ?>
            <div class="admin-alert admin-alert-success">
                No broken image references found!
            </div>
            <a href="?scan=1" class="btn btn-outline">Scan Again</a>
        <?php endif; ?>
    </div>
</div>

<script <?= csp_nonce(); ?>>
// Handle image errors (CSP-compliant replacement for onerror)
document.querySelectorAll('[data-hide-on-error]').forEach(function(img) {
    img.addEventListener('error', function() {
        this.style.display = 'none';
    });
});
document.querySelectorAll('[data-fallback-on-error]').forEach(function(img) {
    img.addEventListener('error', function() {
        this.src = this.dataset.fallbackOnError;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
