<?php
/**
 * Repair Image References
 *
 * Finds broken image references in the database and attempts to match
 * them with renamed files in the uploads folder.
 */

$page_title = 'Repair Image References';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$uploadsDir = __DIR__ . '/../../uploads/';

// All tables/columns that contain image URLs
$imageColumns = [
    'media' => ['filename', 'file_path', 'file_url'],
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

$dryRun = !isset($_POST['apply']);
$results = [];
$errors = [];

// Get all files in uploads directory
$uploadedFiles = [];
if (is_dir($uploadsDir)) {
    $files = scandir($uploadsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($uploadsDir . $file)) {
            $uploadedFiles[strtolower($file)] = $file;
            // Also index by base name (without extension variant suffixes)
            $baseName = preg_replace('/-(thumbnail|small|medium|large)\./', '.', $file);
            $baseName = preg_replace('/\.webp$/', '', $baseName);
        }
    }
}

/**
 * Try to find a matching file for a broken reference
 */
function findMatchingFile($brokenRef, $uploadedFiles, $uploadsDir) {
    $filename = basename($brokenRef);
    $filenameLower = strtolower($filename);

    // Direct match
    if (isset($uploadedFiles[$filenameLower])) {
        return null; // File exists, no fix needed
    }

    // Check if it's a path issue (file exists but path is wrong)
    if (file_exists($uploadsDir . $filename)) {
        return null; // File exists
    }

    // Try to find a renamed version by looking for similar names
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);

    // Look for files that might be the renamed version
    // Pattern: old numeric/timestamp name -> new SEO name with alive-church
    foreach ($uploadedFiles as $lowerName => $actualName) {
        // Check if this could be a renamed version
        if (strpos($actualName, 'alive-church') !== false) {
            // This is a renamed file - check if it might match
            $actualExt = pathinfo($actualName, PATHINFO_EXTENSION);
            if (strtolower($ext) === strtolower($actualExt)) {
                // Same extension - could be a match
                // We can't automatically determine matches, return as candidate
            }
        }
    }

    return false; // No match found
}

/**
 * Check if a URL points to a missing file
 */
function isImageMissing($url, $uploadsDir) {
    if (empty($url)) return false;

    // Only check /uploads/ paths
    if (strpos($url, '/uploads/') === false) return false;

    $filename = basename($url);
    return !file_exists($uploadsDir . $filename);
}

// Scan for broken references
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['scan'])) {
    $broken = [];

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
                            'id' => $row['id'] ?? $row[array_key_first($row)],
                            'url' => $url,
                            'filename' => basename($url)
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
                    // Find all image references in content
                    if (preg_match_all('/\/uploads\/([^"\'>\s]+)/i', $content, $matches)) {
                        foreach ($matches[1] as $filename) {
                            if (!file_exists($uploadsDir . $filename)) {
                                $broken[] = [
                                    'table' => $table,
                                    'column' => $column,
                                    'id' => $row['id'] ?? $row[array_key_first($row)],
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

    $results = $broken;
}

// Build a map of old -> new filenames from media table history
// This checks if media table has original_filename that can help us map
$filenameMap = [];
try {
    $stmt = $pdo->query("SELECT filename, original_filename FROM media WHERE original_filename IS NOT NULL AND original_filename != filename");
    while ($row = $stmt->fetch()) {
        // Map original to current
        $filenameMap[basename($row['original_filename'])] = $row['filename'];
    }
} catch (PDOException $e) {}

?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Repair Image References</h3>
    </div>

    <div style="padding: 1rem;">
        <?php if (empty($results) && !isset($_GET['scan'])): ?>
            <p>This tool scans the database for broken image references and helps fix them.</p>

            <form method="GET">
                <input type="hidden" name="scan" value="1">
                <button type="submit" class="btn btn-primary">Scan for Broken References</button>
            </form>

            <h4 style="margin-top: 2rem;">Files in Uploads Folder</h4>
            <p>Total files: <?= count($uploadedFiles); ?></p>

            <div style="max-height: 300px; overflow-y: auto; font-size: 0.85rem; background: #f9fafb; padding: 1rem; border-radius: 4px;">
                <?php foreach (array_slice($uploadedFiles, 0, 50) as $file): ?>
                    <div><?= htmlspecialchars($file); ?></div>
                <?php endforeach; ?>
                <?php if (count($uploadedFiles) > 50): ?>
                    <div style="color: #6b7280;">... and <?= count($uploadedFiles) - 50; ?> more</div>
                <?php endif; ?>
            </div>

        <?php elseif (!empty($results)): ?>
            <div class="admin-alert admin-alert-warning">
                Found <?= count($results); ?> broken image references
            </div>

            <table class="admin-table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Column</th>
                        <th>ID</th>
                        <th>Missing File</th>
                        <th>Possible Match</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['table']); ?></td>
                            <td><?= htmlspecialchars($item['column']); ?><?= !empty($item['in_content']) ? ' (HTML)' : ''; ?></td>
                            <td><?= htmlspecialchars($item['id']); ?></td>
                            <td><small><?= htmlspecialchars($item['filename']); ?></small></td>
                            <td>
                                <?php
                                $oldName = $item['filename'];
                                if (isset($filenameMap[$oldName])) {
                                    echo '<span style="color: green;">' . htmlspecialchars($filenameMap[$oldName]) . '</span>';
                                } else {
                                    echo '<span style="color: #6b7280;">No mapping found</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h4 style="margin-top: 2rem;">Available SEO-Named Files</h4>
            <p>These files have been renamed with SEO-friendly names:</p>
            <div style="max-height: 200px; overflow-y: auto; font-size: 0.85rem; background: #f9fafb; padding: 1rem; border-radius: 4px;">
                <?php
                $seoFiles = array_filter($uploadedFiles, fn($f) => strpos($f, 'alive-church') !== false);
                foreach ($seoFiles as $file): ?>
                    <div><?= htmlspecialchars($file); ?></div>
                <?php endforeach; ?>
                <?php if (empty($seoFiles)): ?>
                    <div style="color: #6b7280;">No SEO-named files found</div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 1rem;">
                <a href="" class="btn btn-outline">Scan Again</a>
            </div>

        <?php else: ?>
            <div class="admin-alert admin-alert-success">
                No broken image references found!
            </div>
            <a href="" class="btn btn-outline">Scan Again</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
