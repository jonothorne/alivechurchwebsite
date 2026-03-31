<?php
/**
 * Batch Image Rename Tool
 *
 * Uses AI to analyze images and rename them with SEO-friendly names.
 * Updates all database references to prevent 404s.
 *
 * Usage: Access via browser at /adminnew/tools/rename-images
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
$uploadsDir = __DIR__ . '/../../uploads/';

// Parse CLI arguments or form data
$dryRun = $isCli ? in_array('--dry-run', $argv) : ($_POST['dry_run'] ?? false);
$action = $_POST['action'] ?? 'preview';
$selectedIds = $_POST['selected_ids'] ?? [];
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

/**
 * Check if image needs renaming
 */
function getImageStatus($filename) {
    if (strpos($filename, 'alive-church') !== false) {
        // Check if it's a poor name like "image-alive-church-abc123"
        if (preg_match('/^image-alive-church-[a-f0-9]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            return ['status' => 'poor', 'reason' => 'Generic AI fallback name'];
        }
        return ['status' => 'good', 'reason' => 'Already has SEO name'];
    }

    // Check for non-descriptive patterns
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/^[0-9]{8}-wa[0-9]+$/i', $basename)) {
        return ['status' => 'needs_rename', 'reason' => 'WhatsApp filename'];
    }
    if (preg_match('/^(img|image|photo|pic|dsc|dcim|screenshot)-?/i', $basename)) {
        return ['status' => 'needs_rename', 'reason' => 'Camera/generic prefix'];
    }
    if (preg_match('/^[0-9-]+$/', $basename)) {
        return ['status' => 'needs_rename', 'reason' => 'Numeric filename'];
    }

    return ['status' => 'needs_rename', 'reason' => 'Not SEO-friendly'];
}

// Process form submission or CLI execution
$results = [];
$processed = 0;
$errors = [];
$allImages = [];

// Always fetch all images for display
$query = "SELECT * FROM media WHERE file_type = 'image' ORDER BY id DESC";
if ($limit > 0 && $action === 'preview') {
    $query .= " LIMIT " . $limit;
}
$allImages = $pdo->query($query)->fetchAll();

// Process selected images
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'rename' && !empty($selectedIds)) {
    $nameGenerator = new ImageNameGenerator();

    if (!$nameGenerator->isConfigured()) {
        $errors[] = 'Anthropic API key not configured. Add ANTHROPIC_API_KEY to includes/db-config.php';
    } else {
        foreach ($allImages as $image) {
            // Only process selected images
            if (!in_array($image['id'], $selectedIds)) {
                continue;
            }

            $oldFilename = $image['filename'];
            $oldFilePath = $image['file_path'];
            $oldFileUrl = $image['file_url'];
            $fullPath = $uploadsDir . $oldFilename;

            // Skip if file doesn't exist
            if (!file_exists($fullPath)) {
                $errors[] = "File not found: {$oldFilename}";
                continue;
            }

            // Generate new name using AI
            $ext = pathinfo($oldFilename, PATHINFO_EXTENSION);
            $newBaseName = $nameGenerator->generateName($fullPath, $image['original_filename']);

            // Skip if AI couldn't generate a meaningful name
            if ($newBaseName === null) {
                $results[] = [
                    'id' => $image['id'],
                    'old' => $oldFilename,
                    'new' => $oldFilename,
                    'status' => 'skipped',
                    'reason' => $nameGenerator->lastError ?: 'Could not generate SEO name'
                ];
                continue;
            }

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

<style <?= csp_nonce(); ?>>
.image-select-table { width: 100%; border-collapse: collapse; }
.image-select-table th, .image-select-table td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
.image-select-table tr:hover { background: #f9fafb; }
.image-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; display: block; }
.status-good { color: #10b981; }
.status-poor { color: #f59e0b; }
.status-needs_rename { color: #6b7280; }
.select-actions { margin-bottom: 1rem; display: flex; gap: 0.5rem; align-items: center; }
.select-actions button { padding: 0.25rem 0.75rem; font-size: 0.875rem; }
.filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.filter-tab { padding: 0.5rem 1rem; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; border-radius: 4px; }
.filter-tab.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 6px; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

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

    <?php if (!empty($results)): ?>
        <!-- Show results after processing -->
        <div style="padding: 1rem;">
            <div class="admin-alert <?= $dryRun ? 'admin-alert-warning' : 'admin-alert-success'; ?>">
                <?= $dryRun ? 'Dry run completed' : 'Rename completed'; ?>: <?= $processed; ?> images processed
            </div>

            <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem;">
                <?php if ($dryRun && $processed > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="rename">
                        <?php foreach ($selectedIds as $id): ?>
                            <input type="hidden" name="selected_ids[]" value="<?= (int)$id; ?>">
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary">Apply These Changes</button>
                    </form>
                <?php endif; ?>
                <a href="" class="btn btn-outline">Back to Selection</a>
            </div>

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
                                    <?php if (!empty($result['reason'])): ?>
                                        <br><small style="color: #9ca3af;"><?= htmlspecialchars($result['reason']); ?></small>
                                    <?php endif; ?>
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
    <?php else: ?>
        <!-- Show image selection -->
        <div style="padding: 1rem;">
            <p>Select which images to rename with AI-generated SEO-friendly names.</p>

            <div class="admin-alert admin-alert-warning" style="margin: 1rem 0;">
                <strong>Important:</strong> This will rename image files and update all database references. Make a backup first!
            </div>

            <form method="POST" id="rename-form">
                <input type="hidden" name="action" value="rename">

                <div class="filter-tabs">
                    <button type="button" class="filter-tab active" data-filter="all">All (<?= count($allImages); ?>)</button>
                    <button type="button" class="filter-tab" data-filter="needs_rename">Needs Rename</button>
                    <button type="button" class="filter-tab" data-filter="poor">Poor Names</button>
                    <button type="button" class="filter-tab" data-filter="good">Good Names</button>
                </div>

                <div class="select-actions">
                    <button type="button" id="select-all" class="btn btn-outline">Select All Visible</button>
                    <button type="button" id="select-none" class="btn btn-outline">Deselect All</button>
                    <button type="button" id="select-needs-rename" class="btn btn-outline">Select Needs Rename</button>
                    <button type="button" id="select-poor" class="btn btn-outline">Select Poor Names</button>
                    <span id="selected-count" style="margin-left: 1rem; color: #6b7280;">0 selected</span>
                </div>

                <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px;">
                    <table class="image-select-table">
                        <thead style="position: sticky; top: 0; background: #fff;">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="check-all"></th>
                                <th style="width: 60px;">Image</th>
                                <th>Current Filename</th>
                                <th style="width: 120px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allImages as $image):
                                $status = getImageStatus($image['filename']);
                                $exists = file_exists($uploadsDir . $image['filename']);
                            ?>
                                <tr data-status="<?= $status['status']; ?>" <?= !$exists ? 'style="opacity: 0.5;"' : ''; ?>>
                                    <td>
                                        <?php if ($exists): ?>
                                            <input type="checkbox" name="selected_ids[]" value="<?= $image['id']; ?>" class="image-checkbox" data-status="<?= $status['status']; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($exists):
                                            // Use thumbnail variant if available
                                            $thumbName = pathinfo($image['filename'], PATHINFO_FILENAME) . '-thumbnail.' . pathinfo($image['filename'], PATHINFO_EXTENSION);
                                            $thumbSrc = file_exists($uploadsDir . $thumbName) ? $thumbName : $image['filename'];
                                        ?>
                                            <img src="/uploads/<?= htmlspecialchars($thumbSrc); ?>" class="image-thumb" width="50" height="50" loading="lazy" alt="">
                                        <?php else: ?>
                                            <span style="color: red;">Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($image['filename']); ?></small>
                                        <?php if ($image['original_filename'] && $image['original_filename'] !== $image['filename']): ?>
                                            <br><small style="color: #9ca3af;">Original: <?= htmlspecialchars($image['original_filename']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-<?= $status['status']; ?>">
                                            <?php if ($status['status'] === 'good'): ?>
                                                &#10003; Good
                                            <?php elseif ($status['status'] === 'poor'): ?>
                                                &#9888; Poor
                                            <?php else: ?>
                                                &#8226; Needs Rename
                                            <?php endif; ?>
                                        </span>
                                        <br><small style="color: #9ca3af;"><?= htmlspecialchars($status['reason']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                    <label>
                        <input type="checkbox" name="dry_run" value="1" checked>
                        Dry run (preview changes without applying)
                    </label>
                    <button type="submit" class="btn btn-primary">Rename Selected Images</button>
                </div>
            </form>
        </div>

        <script <?= csp_nonce(); ?>>
        // Toast notification
        function showAdminToast(message, type = 'error') {
            document.querySelectorAll('.admin-toast').forEach(t => t.remove());
            const toast = document.createElement('div');
            toast.className = `admin-toast admin-toast-${type}`;
            toast.innerHTML = `<span>${type === 'error' ? '⚠️' : '✅'} ${message}</span><button type="button" onclick="this.parentElement.remove()">&times;</button>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        (function() {
            const checkboxes = document.querySelectorAll('.image-checkbox');
            const checkAll = document.getElementById('check-all');
            const selectedCount = document.getElementById('selected-count');
            const filterTabs = document.querySelectorAll('.filter-tab');
            const rows = document.querySelectorAll('.image-select-table tbody tr');

            function updateCount() {
                const count = document.querySelectorAll('.image-checkbox:checked').length;
                selectedCount.textContent = count + ' selected';
            }

            // Individual checkbox change
            checkboxes.forEach(cb => cb.addEventListener('change', updateCount));

            // Check all header checkbox
            checkAll.addEventListener('change', function() {
                const visibleCheckboxes = document.querySelectorAll('.image-select-table tbody tr:not([style*="display: none"]) .image-checkbox');
                visibleCheckboxes.forEach(cb => cb.checked = this.checked);
                updateCount();
            });

            // Select all visible button
            document.getElementById('select-all').addEventListener('click', function() {
                const visibleCheckboxes = document.querySelectorAll('.image-select-table tbody tr:not([style*="display: none"]) .image-checkbox');
                visibleCheckboxes.forEach(cb => cb.checked = true);
                updateCount();
            });

            // Deselect all button
            document.getElementById('select-none').addEventListener('click', function() {
                checkboxes.forEach(cb => cb.checked = false);
                checkAll.checked = false;
                updateCount();
            });

            // Select needs rename button (additive)
            document.getElementById('select-needs-rename').addEventListener('click', function() {
                checkboxes.forEach(cb => {
                    if (cb.dataset.status === 'needs_rename') {
                        cb.checked = true;
                    }
                });
                updateCount();
            });

            // Select poor names button (additive)
            document.getElementById('select-poor').addEventListener('click', function() {
                checkboxes.forEach(cb => {
                    if (cb.dataset.status === 'poor') {
                        cb.checked = true;
                    }
                });
                updateCount();
            });

            // Form submission - show loading state
            document.getElementById('rename-form').addEventListener('submit', function(e) {
                const btn = this.querySelector('button[type="submit"]');
                const count = document.querySelectorAll('.image-checkbox:checked').length;

                if (count === 0) {
                    e.preventDefault();
                    showAdminToast('Please select at least one image to rename.');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner"></span> Processing ' + count + ' images... Please wait';
                btn.style.opacity = '0.7';

                // Disable all checkboxes to prevent changes
                checkboxes.forEach(cb => cb.disabled = true);
            });

            // Filter tabs
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    filterTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    rows.forEach(row => {
                        if (filter === 'all' || row.dataset.status === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
