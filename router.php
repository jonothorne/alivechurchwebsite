<?php
/**
 * Router for PHP Built-in Server
 * Handles clean URL routing without .htaccess
 *
 * To use: php -S localhost:8999 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// Remove trailing slash (except for root)
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = substr($uri, 0, -1);
}

// Serve static files directly (but not directories)
if ($uri !== '/' && file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false; // Serve the file as-is
}

// Sitemap
if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

// Map clean URLs to PHP files
$routes = [
    '/' => '/index.php',
    '/index' => '/index.php',
    '/visit' => '/visit.php',
    '/watch' => '/watch.php',
    '/events' => '/events.php',
    '/connect' => '/connect.php',
    '/about' => '/about.php',
    '/give' => '/give.php',
    '/ministries' => '/ministries.php',
    '/next-steps' => '/next-steps.php',
    '/prayer' => '/prayer.php',
    '/contact-us' => '/contact-us.php',
    '/groups/join' => '/groups/join.php',
    '/events/register' => '/events/register.php',
    '/serve/apply' => '/serve/apply.php',
    '/next-steps/baptism' => '/next-steps/baptism.php',
    '/404' => '/404.php',
    '/offline' => '/offline.php',

    // User account routes
    '/login' => '/login.php',
    '/register' => '/register.php',
    '/logout' => '/logout.php',
    '/my-studies' => '/my-studies.php',
    '/my-studies/saved' => '/my-studies-saved.php',
    '/my-studies/highlights' => '/my-studies-highlights.php',
    '/my-studies/history' => '/my-studies-history.php',
    '/my-studies/settings' => '/my-studies-settings.php',
    '/reading-plans' => '/reading-plans.php',

    // Admin routes
    '/admin' => '/admin/index.php',
    '/admin/login' => '/admin/login.php',
    '/admin/logout' => '/admin/logout.php',
    '/admin/events' => '/admin/events.php',
    '/admin/events/edit' => '/admin/events/edit.php',
    '/admin/blog' => '/admin/blog.php',
    '/admin/blog/edit' => '/admin/blog/edit.php',
    '/admin/blog/categories' => '/admin/blog/categories.php',
    '/admin/blog/comments' => '/admin/blog/comments.php',
    '/admin/bible-study' => '/admin/bible-study.php',
    '/admin/bible-study/edit' => '/admin/bible-study/edit.php',
    '/admin/profanity-filter' => '/admin/profanity-filter.php',
];

// Check if route exists
if (isset($routes[$uri])) {
    $file = __DIR__ . $routes[$uri];
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// Try adding .php extension
$phpFile = __DIR__ . $uri . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    return true;
}

// Event detail pages: /events/slug -> events/detail.php?slug=slug
if (preg_match('#^/events/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/events/detail.php';
    return true;
}

// Blog post pages: /blog/slug -> blog-post.php?slug=slug
if (preg_match('#^/blog/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/blog-post.php';
    return true;
}

// User profile pages: /user/username -> user-profile.php?username=username
if (preg_match('#^/user/([a-zA-Z0-9_-]+)$#', $uri, $matches)) {
    $_GET['username'] = $matches[1];
    require __DIR__ . '/user-profile.php';
    return true;
}

// Bible Study routes
// /bible-study -> Main library
if ($uri === '/bible-study') {
    require __DIR__ . '/bible-study.php';
    return true;
}

// /bible-study/search -> Search page
if ($uri === '/bible-study/search') {
    require __DIR__ . '/bible-study-search.php';
    return true;
}

// /bible-study/topics -> Topics listing page
if ($uri === '/bible-study/topics') {
    require __DIR__ . '/bible-study-topics.php';
    return true;
}

// /bible-study/topics/topic-slug -> Single topic page
if (preg_match('#^/bible-study/topics/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['topic'] = $matches[1];
    require __DIR__ . '/bible-study-topic.php';
    return true;
}

// /bible-study/questions/question-slug -> Question page (SEO)
if (preg_match('#^/bible-study/questions/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['question'] = $matches[1];
    require __DIR__ . '/bible-study-question.php';
    return true;
}

// /bible-study/book-slug -> Book overview
if (preg_match('#^/bible-study/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['book'] = $matches[1];
    require __DIR__ . '/bible-study-book.php';
    return true;
}

// /bible-study/book-slug/chapter -> Chapter study
if (preg_match('#^/bible-study/([a-zA-Z0-9-]+)/(\d+)$#', $uri, $matches)) {
    $_GET['book'] = $matches[1];
    $_GET['chapter'] = $matches[2];
    require __DIR__ . '/bible-study-chapter.php';
    return true;
}

// Reading plan day: /reading-plan/slug/day/1
if (preg_match('#^/reading-plan/([a-zA-Z0-9-]+)/day/(\d+)$#', $uri, $matches)) {
    $_GET['plan'] = $matches[1];
    $_GET['day'] = $matches[2];
    require __DIR__ . '/reading-plan-day.php';
    return true;
}

// Reading plan overview: /reading-plan/slug
if (preg_match('#^/reading-plan/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['plan'] = $matches[1];
    require __DIR__ . '/reading-plan-view.php';
    return true;
}

// Author pages: /author/username
if (preg_match('#^/author/([a-zA-Z0-9_-]+)$#', $uri, $matches)) {
    $_GET['username'] = $matches[1];
    require __DIR__ . '/author.php';
    return true;
}

// Try subdirectory with .php
if (strpos($uri, '/') !== false) {
    $parts = explode('/', trim($uri, '/'));
    if (count($parts) === 2) {
        $subFile = __DIR__ . '/' . $parts[0] . '/' . $parts[1] . '.php';
        if (file_exists($subFile)) {
            require $subFile;
            return true;
        }
    }
}

// Check database for dynamic pages
$slug = trim($uri, '/');
if (!empty($slug)) {
    require_once __DIR__ . '/includes/db-config.php';
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND published = 1 LIMIT 1");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            require __DIR__ . '/page.php';
            return true;
        }
    } catch (Exception $e) {
        // Database not set up yet, continue to 404
    }
}

// 404 - Not found
http_response_code(404);
if (file_exists(__DIR__ . '/404.php')) {
    require __DIR__ . '/404.php';
} else {
    echo '404 - Page Not Found';
}
return true;
