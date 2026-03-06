<?php
/**
 * Legacy to GrapesJS Migration Tool
 * Converts existing page sections to GrapesJS format
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/includes/grapes-helpers.php';

// Require authentication
require_auth();

$pdo = getDbConnection();
$success = '';
$error = '';

// Get page ID
$page_id = $_GET['page_id'] ?? null;

if (!$page_id || !is_numeric($page_id)) {
    die('Invalid page ID');
}

// Get page data
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$page_id]);
$page = $stmt->fetch();

if (!$page) {
    die('Page not found');
}

// Handle migration BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_migration'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            // Convert legacy sections to HTML
            $converted_html = convert_legacy_sections_to_html($page_id);

            // If no sections, create a blank starter template
            if (empty($converted_html)) {
                $converted_html = <<<HTML
<section style="padding: 100px 20px; text-align: center; background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); color: white;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1rem;">Welcome to Your New Page</h1>
        <p style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;">Start building by dragging blocks from the sidebar</p>
    </div>
</section>
HTML;
            }

            // Update page to GrapesJS mode
            $stmt = $pdo->prepare("
                UPDATE pages
                SET
                    builder_mode = 'grapes',
                    grapes_html = ?,
                    grapes_css = '',
                    grapes_components = NULL,
                    grapes_styles = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$converted_html, $page_id]);

            // Log activity
            log_activity(
                $_SESSION['admin_user_id'],
                'update',
                'page',
                $page_id,
                'Migrated page to GrapesJS visual editor'
            );

            // Redirect to editor (before any output)
            header('Location: /admin/grapes-editor.php?page_id=' . $page_id);
            exit;

        } catch (Exception $e) {
            $error = 'Migration failed: ' . $e->getMessage();
            error_log('Migration error: ' . $e->getMessage());
        }
    }
}

// NOW include header (after processing POST)
$page_title = 'Upgrade to Visual Editor';
require_once __DIR__ . '/includes/header.php';

// Generate preview HTML
$preview_html = convert_legacy_sections_to_html($page_id);

// Get section count for display
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM page_sections WHERE page_id = ?");
$stmt->execute([$page_id]);
$section_count = $stmt->fetch()['count'];
?>

<style>
    .migration-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .migration-header {
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }

    .migration-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }

    .migration-header p {
        margin: 0;
        opacity: 0.9;
    }

    .info-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .info-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .info-card-title {
        color: #64748B;
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
    }

    .info-card-value {
        color: #2D1B4E;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .preview-section {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .preview-header {
        background: #F9FAFB;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #E2E8F0;
        font-weight: 600;
        color: #2D1B4E;
    }

    .preview-content {
        padding: 2rem;
        max-height: 500px;
        overflow-y: auto;
        background: #F9FAFB;
    }

    .preview-iframe {
        width: 100%;
        min-height: 400px;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        background: white;
    }

    .warning-box {
        background: #FFF7ED;
        border: 2px solid #FB923C;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .warning-box h3 {
        color: #EA580C;
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        padding: 2rem 0;
    }
</style>

<div class="migration-container">
    <div class="migration-header">
        <h1>⚡ Upgrade to Visual Editor</h1>
        <p>Convert "<?= htmlspecialchars($page['title']); ?>" to the new drag-and-drop page builder</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="info-cards">
        <div class="info-card">
            <div class="info-card-title">Current Mode</div>
            <div class="info-card-value">Legacy Sections</div>
        </div>
        <div class="info-card">
            <div class="info-card-title">Sections to Convert</div>
            <div class="info-card-value"><?= $section_count; ?></div>
        </div>
        <div class="info-card">
            <div class="info-card-title">New Mode</div>
            <div class="info-card-value">Visual Editor</div>
        </div>
    </div>

    <div class="warning-box">
        <h3>
            <span style="font-size: 1.5rem;">⚠️</span>
            Important Information
        </h3>
        <ul style="margin: 1rem 0 0 1.5rem; line-height: 1.8;">
            <li><strong>One-way conversion:</strong> After upgrading, you cannot revert back to the legacy section editor</li>
            <li><strong>Content preserved:</strong> All your existing content will be converted to the new format</li>
            <li><strong>Original data kept:</strong> Your legacy sections remain in the database (not deleted)</li>
            <li><strong>Full control:</strong> You'll be able to edit everything with drag-and-drop in the visual editor</li>
        </ul>
    </div>

    <div class="preview-section">
        <div class="preview-header">
            📋 Preview of Converted Content
        </div>
        <div class="preview-content">
            <?php if (empty($preview_html)): ?>
                <div style="text-align: center; padding: 3rem; color: #64748B;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                    <p>This page has no sections yet. A blank starter template will be created.</p>
                </div>
            <?php else: ?>
                <div style="background: white; border-radius: 8px; padding: 2rem;">
                    <link rel="stylesheet" href="/assets/css/style.css">
                    <?= $preview_html; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" onsubmit="return confirm('Are you sure you want to upgrade this page? This cannot be undone.');">
        <?= csrf_field(); ?>
        <input type="hidden" name="confirm_migration" value="1">

        <div class="action-buttons">
            <a href="/admin/pages.php" class="btn btn-outline btn-lg">
                ← Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #10B981, #059669);">
                ⚡ Upgrade to Visual Editor
            </button>
        </div>
    </form>

    <div style="background: #F0F9FF; border: 1px solid #7DD3FC; border-radius: 8px; padding: 1.5rem; margin-top: 2rem;">
        <h3 style="color: #0369A1; margin-top: 0; font-size: 1rem;">💡 What happens after upgrade?</h3>
        <ol style="margin: 0.5rem 0 0 1.5rem; color: #0369A1; line-height: 1.8;">
            <li>You'll be redirected to the visual editor</li>
            <li>Your converted content will load automatically</li>
            <li>You can drag-and-drop blocks to add new sections</li>
            <li>Click any element to edit text, styles, and settings</li>
            <li>Use the device switcher to preview on mobile/tablet</li>
            <li>Click "Save & Publish" when you're done</li>
        </ol>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
