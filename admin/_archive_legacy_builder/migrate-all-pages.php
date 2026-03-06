<?php
/**
 * Migrate All Legacy Pages to GrapesJS
 * One-time migration script
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/includes/grapes-helpers.php';

// Require authentication
require_auth();

$pdo = getDbConnection();

// Get all legacy pages
$stmt = $pdo->query("SELECT id, slug, title FROM pages WHERE builder_mode = 'legacy'");
$legacy_pages = $stmt->fetchAll();

$migrated = [];
$errors = [];

foreach ($legacy_pages as $page) {
    try {
        // Convert sections to HTML
        $html = convert_legacy_sections_to_html($page['id']);

        // Update page to GrapesJS mode
        $update = $pdo->prepare("
            UPDATE pages
            SET builder_mode = 'grapes',
                grapes_html = ?,
                grapes_css = '',
                grapes_components = NULL,
                grapes_styles = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");

        $update->execute([$html, $page['id']]);

        // Log the migration
        log_activity(
            $_SESSION['admin_user_id'],
            'update',
            'page',
            $page['id'],
            'Migrated page to GrapesJS: ' . $page['title']
        );

        $migrated[] = $page['title'];

    } catch (Exception $e) {
        $errors[] = $page['title'] . ': ' . $e->getMessage();
    }
}

// Output results
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration Complete</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f9fafb;
        }
        .success {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        a {
            display: inline-block;
            background: #2D1B4E;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        a:hover {
            background: #4B2679;
        }
    </style>
</head>
<body>
    <h1>🎉 Migration Complete</h1>

    <?php if (!empty($migrated)): ?>
        <div class="success">
            <h2>✅ Successfully Migrated:</h2>
            <ul>
                <?php foreach ($migrated as $title): ?>
                    <li><?= htmlspecialchars($title); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <h2>⚠️ Errors:</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p>All pages are now using the GrapesJS visual editor!</p>

    <a href="/admin/pages.php">← Back to Pages</a>
</body>
</html>
