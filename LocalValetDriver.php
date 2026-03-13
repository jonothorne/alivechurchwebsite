<?php

/**
 * Custom Valet Driver for Alive Church Site
 * Handles URL rewriting that would normally be done by .htaccess
 */
class LocalValetDriver extends \Valet\Drivers\BasicValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return true;
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        // Check for static files first
        if ($this->isActualFile($staticFilePath = $sitePath.$uri)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        // Remove query string for matching
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        $path = rtrim($path, '/');

        // Debug route
        if ($path === '/debug-driver') {
            echo "Driver is active!<br>";
            echo "sitePath: $sitePath<br>";
            echo "siteName: $siteName<br>";
            echo "uri: $uri<br>";
            echo "path: $path<br>";
            exit;
        }

        // If empty path, serve index
        if ($path === '' || $path === '/') {
            return $sitePath . '/index.php';
        }

        // Define URL rewrites (mirrors .htaccess rules)
        $rewrites = [
            // Bible study questions (priority)
            '@^/bible-study/questions$@' => ['file' => '/bible-study-questions.php'],
            '@^/bible-study/questions/([a-zA-Z0-9-]+)$@' => ['file' => '/bible-study-question.php', 'params' => ['question' => 1]],

            // Bible study sub-pages
            '@^/bible-study/topics$@' => ['file' => '/bible-study-topics.php'],
            '@^/bible-study/topics/([a-zA-Z0-9-]+)$@' => ['file' => '/bible-study-topic.php', 'params' => ['topic' => 1]],
            '@^/bible-study/search$@' => ['file' => '/bible-study-search.php'],

            // Bible study chapter pages
            '@^/bible-study/([a-zA-Z0-9-]+)/(\d+)$@' => ['file' => '/bible-study-chapter.php', 'params' => ['book' => 1, 'chapter' => 2]],

            // Bible study book pages (must come after chapter and sub-pages)
            '@^/bible-study/([a-zA-Z0-9-]+)$@' => ['file' => '/bible-study-book.php', 'params' => ['book' => 1]],

            // Sermons
            '@^/sermons$@' => ['file' => '/sermons.php'],
            '@^/sermon/([a-zA-Z0-9-]+)$@' => ['file' => '/sermon.php', 'params' => ['slug' => 1]],
            '@^/sermons/series/([a-zA-Z0-9-]+)$@' => ['file' => '/sermon-series.php', 'params' => ['slug' => 1]],
            '@^/sermons/series$@' => ['file' => '/sermons/series.php'],
            '@^/sermons/speakers$@' => ['file' => '/sermons/speakers.php'],
            '@^/sermons/topics$@' => ['file' => '/sermons/topics.php'],

            // Events
            '@^/events$@' => ['file' => '/events.php'],
            '@^/events/([a-zA-Z0-9-]+)$@' => ['file' => '/events/detail.php', 'params' => ['slug' => 1]],

            // About pages
            '@^/about$@' => ['file' => '/about.php'],
            '@^/about/history$@' => ['file' => '/about/history.php'],
            '@^/about/what-we-believe$@' => ['file' => '/about/what-we-believe.php'],
            '@^/about/vision$@' => ['file' => '/about/vision.php'],
            '@^/about/dead-church$@' => ['file' => '/about/dead-church.php'],

            // My Studies
            '@^/my-studies$@' => ['file' => '/my-studies.php'],
            '@^/my-studies/saved$@' => ['file' => '/my-studies-saved.php'],
            '@^/my-studies/highlights$@' => ['file' => '/my-studies-highlights.php'],
            '@^/my-studies/history$@' => ['file' => '/my-studies-history.php'],

            // Reading plans
            '@^/reading-plans$@' => ['file' => '/reading-plans.php'],
            '@^/reading-plan/([a-zA-Z0-9-]+)/day/(\d+)$@' => ['file' => '/reading-plan-day.php', 'params' => ['plan' => 1, 'day' => 2]],
            '@^/reading-plan/([a-zA-Z0-9-]+)$@' => ['file' => '/reading-plan-view.php', 'params' => ['plan' => 1]],

            // Blog
            '@^/blog/([a-zA-Z0-9-]+)$@' => ['file' => '/blog-post.php', 'params' => ['slug' => 1]],

            // Author/User profiles
            '@^/author/([^/]+)$@' => ['file' => '/author.php', 'params' => ['username' => 1]],
            '@^/user/([^/]+)$@' => ['file' => '/author.php', 'params' => ['username' => 1]],

            // API routes
            '@^/api/question-search$@' => ['file' => '/api/question-search.php'],
            '@^/api/user-preferences$@' => ['file' => '/api/user-preferences.php'],
            '@^/api/cookie-consent$@' => ['file' => '/api/cookie-consent.php'],
            '@^/api/newsletter-signup$@' => ['file' => '/api/newsletter-signup.php'],
            '@^/api/user-studies$@' => ['file' => '/api/user-studies.php'],
            '@^/api/cms/save$@' => ['file' => '/api/cms/save.php'],
            '@^/api/cms/upload$@' => ['file' => '/api/cms/upload.php'],
            '@^/api/cms/media$@' => ['file' => '/api/cms/media.php'],
            '@^/api/cms/page$@' => ['file' => '/api/cms/page.php'],
            '@^/api/cms/blocks$@' => ['file' => '/api/cms/blocks.php'],
            '@^/api/bible-study/save$@' => ['file' => '/api/bible-study/save.php'],

            // Sitemap
            '@^/sitemap\.xml$@' => ['file' => '/sitemap.php'],
        ];

        // Check each rewrite rule
        foreach ($rewrites as $pattern => $config) {
            if (preg_match($pattern, $path, $matches)) {
                $targetFile = $sitePath . $config['file'];

                // Set query parameters if present
                if (isset($config['params'])) {
                    foreach ($config['params'] as $key => $matchIndex) {
                        $_GET[$key] = $matches[$matchIndex] ?? '';
                        $_REQUEST[$key] = $matches[$matchIndex] ?? '';
                    }
                }

                if (file_exists($targetFile)) {
                    $_SERVER['SCRIPT_FILENAME'] = $targetFile;
                    $_SERVER['SCRIPT_NAME'] = $config['file'];
                    $_SERVER['DOCUMENT_ROOT'] = $sitePath;
                    return $targetFile;
                }
            }
        }

        // Check for direct .php file match (e.g., /contact -> /contact.php)
        $phpFile = $sitePath . $path . '.php';
        if (file_exists($phpFile)) {
            $_SERVER['SCRIPT_FILENAME'] = $phpFile;
            $_SERVER['SCRIPT_NAME'] = $path . '.php';
            $_SERVER['DOCUMENT_ROOT'] = $sitePath;
            return $phpFile;
        }

        // Fall back to page.php for CMS dynamic pages
        $_GET['uri'] = ltrim($path, '/');
        $_REQUEST['uri'] = ltrim($path, '/');
        $_SERVER['SCRIPT_FILENAME'] = $sitePath . '/page.php';
        $_SERVER['SCRIPT_NAME'] = '/page.php';
        $_SERVER['DOCUMENT_ROOT'] = $sitePath;
        return $sitePath . '/page.php';
    }
}
