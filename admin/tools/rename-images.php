<?php
/**
 * Batch Image Rename Tool
 *
 * Uses AI to analyze images and rename them with SEO-friendly names.
 * Updates all database references to prevent 404s.
 *
 * Usage: Access via browser at /admin/tools/rename-images.php
 * Or CLI: php rename-images.php [--dry-run] [--limit=10]
 */

// Increase limits for batch processing
set_time_limit(0);
ini_set('memory_limit', '1G');

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    $page_title = 'Rename Images (SEO Tool)';
    require_once __DIR__ . '/../includes/header.php';
}

require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/ImageNameGenerator.php';

$pdo = getDbConnection();

// Parse CLI arguments or form data
$dryRun = $isCli ? in_array('--dry-run', $argv) : ($_POST['dry_run'] ?? false);
$limit = 0;
if ($isCli) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $limit = (int)substr($arg, 8);
        }
    }
} else {
    $limit = (int)($_POST['limit'] ?? 0);
}

// Tables and columns that contain image URLs (direct references)
$directUrlColumns = [
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

// Tables and columns that may contain image URLs in HTML/text content
$contentColumns = [
    'blog_posts' => ['content'],
    'content_blocks' => ['content'],
    'global_content' => ['content'],
    'pages' => ['content'],
    'bible_studies' => ['content'],
    'sermon_comments' => ['content'],
    'blog_comments' => ['content'],
];

/**
 * Get all image variants for a given base path
 */
function getImageVariants($basePath, $uploadsDir) {
    $variants = [];
    $pathInfo = pathinfo($basePath);
    $baseName = $pathInfo['filename'];
    $ext = $pathInfo['extension'] ?? '';
    $dir = $uploadsDir;

    // Main file
    if (file_exists($dir . $baseName . '.' . $ext)) {
        $variants[] = $baseName . '.' . $ext;
    }

    // Size variants
    $sizes = ['small', 'medium', 'large', 'thumb', 'thumbnail'];
    foreach ($sizes as $size) {
        $variantName = $baseName . '-' . $size . '.' . $ext;
        if (file_exists($dir . $variantName)) {
            $variants[] = $variantName;
        }
    }

    // WebP variants
    $webpName = $baseName . '.' . $ext . '.webp';
    if (file_exists($dir . $webpName)) {
        $variants[] = $webpName;
    }
    $webpName2 = $baseName . '.webp';
    if (file_exists($dir . $webpName2)) {
        $variants[] = $webpName2;
    }

    // WebP size variants
    foreach ($sizes as $size) {
        $webpVariant = $baseName . '-' . $size . '.webp';
        if (file_exists($dir . $webpVariant)) {
            $variants[] = $webpVariant;
        }
        $webpVariant2 = $baseName . '-' . $size . '.' . $ext . '.webp';
        if (file_exists($dir . $webpVariant2)) {
            $variants[] = $webpVariant2;
        }
    }

    return $variants;
}

/**
 * Rename all variants of an image
 */
function renameImageVariants($oldBaseName, $newBaseName, $ext, $uploadsDir, $dryRun = false) {
    $renamed = [];
    $oldPath = pathinfo($oldBaseName, PATHINFO_FILENAME);
    $newPath = pathinfo($newBaseName, PATHINFO_FILENAME);

    // Find all files that start with the old base name
    $files = glob($uploadsDir . $oldPath . '*');

    foreach ($files as $file) {
        $fileName = basename($file);
        $newFileName = str_replace($oldPath, $newPath, $fileName);

        if (!$dryRun) {
            if (rename($file, $uploadsDir . $newFileName)) {
                $renamed[$fileName] = $newFileName;
            }
        } else {
            $renamed[$fileName] = $newFileName;
        }
    }

    return $renamed;
}

/**
 * Update direct URL columns in database
 */
function updateDirectUrls($pdo, $oldUrl, $newUrl, $directUrlColumns, $dryRun = false) {
    $updates = [];

    foreach ($directUrlColumns as $table => $columns) {
        foreach ($columns as $column) {
            // Check if table and column exist
            try {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` LIKE ?");
                $checkStmt->execute(['%' . basename($oldUrl) . '%']);
                $count = $checkStmt->fetchColumn();

                if ($count > 0) {
                    if (!$dryRun) {
                        $stmt = $pdo->prepare("UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE `$column` LIKE ?");
                        $stmt->execute([$oldUrl, $newUrl, '%' . basename($oldUrl) . '%']);
                        $affected = $stmt->rowCount();
                    } else {
                        $affected = $count;
                    }
                    $updates["$table.$column"] = $affected;
                }
            } catch (PDOException $e) {
                // Table or column might not exist, skip silently
            }
        }
    }

    return $updates;
}

/**
 * Update image_variants table
 */
function updateImageVariants($pdo, $mediaId, $oldBaseName, $newBaseName, $dryRun = false) {
    $updates = [];

    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM image_variants WHERE media_id = ?");
        $checkStmt->execute([$mediaId]);
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            if (!$dryRun) {
                // Update original_path
                $stmt = $pdo->prepare("UPDATE image_variants SET original_path = REPLACE(original_path, ?, ?) WHERE media_id = ?");
                $stmt->execute([$oldBaseName, $newBaseName, $mediaId]);

                // Update variant_path
                $stmt = $pdo->prepare("UPDATE image_variants SET variant_path = REPLACE(variant_path, ?, ?) WHERE media_id = ?");
                $stmt->execute([$oldBaseName, $newBaseName, $mediaId]);
            }
            $updates['image_variants'] = $count;
        }
    } catch (PDOException $e) {
        // Table might not exist, skip
    }

    return $updates;
}

/**
 * Update content columns (HTML content) in database
 */
function updateContentUrls($pdo, $oldUrl, $newUrl, $contentColumns, $dryRun = false) {
    $updates = [];

    // Also update variant URLs (different sizes)
    $oldBaseName = pathinfo($oldUrl, PATHINFO_FILENAME);
    $newBaseName = pathinfo($newUrl, PATHINFO_FILENAME);

    foreach ($contentColumns as $table => $columns) {
        foreach ($columns as $column) {
            try {
                // Check for any reference to the old filename
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` LIKE ?");
                $checkStmt->execute(['%' . $oldBaseName . '%']);
                $count = $checkStmt->fetchColumn();

                if ($count > 0) {
                    if (!$dryRun) {
                        // Replace all occurrences of the old base filename with new one
                        $stmt = $pdo->prepare("UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE `$column` LIKE ?");
                        $stmt->execute([$oldBaseName, $newBaseName, '%' . $oldBaseName . '%']);
                        $affected = $stmt->rowCount();
                    } else {
                        $affected = $count;
                    }
                    $updates["$table.$column"] = $affected;
                }
            } catch (PDOException $e) {
                // Table or column might not exist, skip silently
            }
        }
    }

    return $updates;
}

/**
 * Update media table record
 */
function updateMediaRecord($pdo, $mediaId, $newFilename, $newFilePath, $newFileUrl, $dryRun = false) {
    if ($dryRun) {
        return true;
    }

    $stmt = $pdo->prepare("UPDATE media SET filename = ?, file_path = ?, file_url = ? WHERE id = ?");
    return $stmt->execute([$newFilename, $newFilePath, $newFileUrl, $mediaId]);
}

// Process form submission or CLI execution
$results = [];
$processed = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $isCli) {
    $nameGenerator = new ImageNameGenerator();

    if (!$nameGenerator->isConfigured()) {
        $errors[] = 'Anthropic API key not configured. Add ANTHROPIC_API_KEY to includes/db-config.php';
    } else {
        $uploadsDir = __DIR__ . '/../../uploads/';

        // Fetch all images from media table
        $query = "SELECT * FROM media WHERE file_type = 'image' ORDER BY id";
        if ($limit > 0) {
            $query .= " LIMIT " . $limit;
        }
        $images = $pdo->query($query)->fetchAll();

        foreach ($images as $image) {
            $oldFilename = $image['filename'];
            $oldFilePath = $image['file_path'];
            $oldFileUrl = $image['file_url'];
            $fullPath = $uploadsDir . $oldFilename;

            // Skip if file doesn't exist
            if (!file_exists($fullPath)) {
                $errors[] = "File not found: {$oldFilename}";
                continue;
            }

            // Skip if already has SEO-friendly name (contains 'alive-church')
            if (strpos($oldFilename, 'alive-church') !== false) {
                $results[] = [
                    'id' => $image['id'],
                    'old' => $oldFilename,
                    'new' => $oldFilename,
                    'status' => 'skipped',
                    'reason' => 'Already has SEO name'
                ];
                continue;
            }

            // Generate new name using AI
            $ext = pathinfo($oldFilename, PATHINFO_EXTENSION);
            $newBaseName = $nameGenerator->generateName($fullPath, $image['original_filename']);
            $newBaseName = $nameGenerator->ensureUnique($newBaseName, $ext, $uploadsDir);
            $newFilename = $newBaseName . '.' . $ext;
            $newFilePath = 'uploads/' . $newFilename;
            $newFileUrl = '/uploads/' . $newFilename;

            // Track result
            $result = [
                'id' => $image['id'],
                'old' => $oldFilename,
                'new' => $newFilename,
                'status' => 'pending',
                'variants_renamed' => [],
                'db_updates' => []
            ];

            // Rename file variants
            $renamedVariants = renameImageVariants($oldFilename, $newFilename, $ext, $uploadsDir, $dryRun);
            $result['variants_renamed'] = $renamedVariants;

            // Update media table
            updateMediaRecord($pdo, $image['id'], $newFilename, $newFilePath, $newFileUrl, $dryRun);

            // Update direct URL references
            $directUpdates = updateDirectUrls($pdo, $oldFileUrl, $newFileUrl, $directUrlColumns, $dryRun);
            $result['db_updates'] = array_merge($result['db_updates'], $directUpdates);

            // Update content/HTML references
            $contentUpdates = updateContentUrls($pdo, $oldFileUrl, $newFileUrl, $contentColumns, $dryRun);
            $result['db_updates'] = array_merge($result['db_updates'], $contentUpdates);

            // Update image_variants table
            $oldBaseName = pathinfo($oldFilename, PATHINFO_FILENAME);
            $variantUpdates = updateImageVariants($pdo, $image['id'], $oldBaseName, $newBaseName, $dryRun);
            $result['db_updates'] = array_merge($result['db_updates'], $variantUpdates);

            $result['status'] = $dryRun ? 'dry-run' : 'renamed';
            $results[] = $result;
            $processed++;

            // Output progress for CLI
            if ($isCli) {
                echo "Processed: {$oldFilename} -> {$newFilename}\n";
            }
        }
    }
}

// Output for CLI
if ($isCli) {
    echo "\n=== Summary ===\n";
    echo "Processed: {$processed} images\n";
    echo "Errors: " . count($errors) . "\n";
    if ($dryRun) {
        echo "(Dry run - no changes made)\n";
    }
    exit;
}

// HTML output for browser
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>AI Image Rename Tool</h3>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="admin-alert admin-alert-error">
            <?php foreach ($errors as $err): ?>
                <p><?= htmlspecialchars($err); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($results)): ?>
        <div style="padding: 1rem;">
            <p>This tool will analyze your images using AI and rename them with SEO-friendly names that describe the image content.</p>

            <div class="admin-alert admin-alert-warning" style="margin: 1rem 0;">
                <strong>Important:</strong> This will rename image files and update all database references. Make a backup first!
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="dry_run" value="1" checked>
                        Dry run (preview changes without applying)
                    </label>
                </div>
                <div class="form-group">
                    <label>Limit (0 = all images)</label>
                    <input type="number" name="limit" value="10" min="0" style="width: 100px;">
                </div>
                <button type="submit" class="btn btn-primary">Analyze Images</button>
            </form>
        </div>
    <?php else: ?>
        <div style="padding: 1rem;">
            <div class="admin-alert <?= $dryRun ? 'admin-alert-warning' : 'admin-alert-success'; ?>">
                <?= $dryRun ? 'Dry run completed' : 'Rename completed'; ?>: <?= $processed; ?> images processed
            </div>

            <?php if ($dryRun): ?>
                <form method="POST" style="margin-bottom: 1rem;">
                    <input type="hidden" name="limit" value="<?= $limit; ?>">
                    <button type="submit" class="btn btn-primary">Apply Changes</button>
                    <a href="" class="btn btn-outline">Start Over</a>
                </form>
            <?php else: ?>
                <a href="" class="btn btn-outline">Rename More</a>
            <?php endif; ?>

            <table class="admin-table" style="margin-top: 1rem;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Old Name</th>
                        <th>New Name</th>
                        <th>Status</th>
                        <th>DB Updates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?= $result['id']; ?></td>
                            <td><small><?= htmlspecialchars($result['old']); ?></small></td>
                            <td><small><?= htmlspecialchars($result['new']); ?></small></td>
                            <td>
                                <?php if ($result['status'] === 'skipped'): ?>
                                    <span style="color: #6b7280;">Skipped</span>
                                <?php elseif ($result['status'] === 'dry-run'): ?>
                                    <span style="color: #f59e0b;">Preview</span>
                                <?php else: ?>
                                    <span style="color: #10b981;">Renamed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($result['db_updates'])): ?>
                                    <small>
                                        <?php foreach ($result['db_updates'] as $col => $count): ?>
                                            <?= $col; ?>: <?= $count; ?><br>
                                        <?php endforeach; ?>
                                    </small>
                                <?php else: ?>
                                    <small style="color: #6b7280;">None</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
