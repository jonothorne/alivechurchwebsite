<?php
/**
 * Dynamic Page Renderer
 *
 * Renders CMS-managed pages using templates.
 * This is the fallback for pages that don't have a .php file.
 */

require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/cms/ContentManager.php';
require_once __DIR__ . '/includes/cms/TemplateEngine.php';

// Get the slug from the URL
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($uri, '/');

// Default to 'home' for root URL
if (empty($slug)) {
    $slug = 'home';
}

// Fetch the page from database
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$page = $stmt->fetch();

// Check if page exists and is published (or user is admin)
session_start();
$isAdmin = isset($_SESSION['admin_user_id']);
$isPreview = isset($_GET['preview']) && $_GET['preview'] === 'true';

if (!$page) {
    // Page not in database - show 404
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        require __DIR__ . '/404.php';
    } else {
        echo '404 - Page Not Found';
    }
    exit;
}

// If page is not published and user is not admin, show 404
if (!$page['published'] && !$isAdmin) {
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        require __DIR__ . '/404.php';
    } else {
        echo '404 - Page Not Found';
    }
    exit;
}

// Initialize the CMS with this page's slug
$cms = new ContentManager($slug);

// Initialize the template engine
$template = new TemplateEngine($cms);

// Render the page using its template
echo $template->render($slug, [
    'title' => $page['title'],
    'meta_description' => $page['meta_description'],
    'template' => $page['template'] ?? 'default',
    'hero_style' => $page['hero_style'] ?? 'standard'
]);
