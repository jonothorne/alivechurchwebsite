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
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        // Remove query string for matching
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        $path = rtrim($path, '/');

        // Define URL rewrites (mirrors .htaccess rules)
        $rewrites = [
            // Bible study questions (priority)
            '#^/bible-study/questions$#' => '/bible-study-questions.php',
            '#^/bible-study/questions/([a-zA-Z0-9-]+)$#' => '/bible-study-question.php?question=$1',

            // Bible study sub-pages
            '#^/bible-study/topics$#' => '/bible-study-topics.php',
            '#^/bible-study/topics/([a-zA-Z0-9-]+)$#' => '/bible-study-topic.php?topic=$1',
            '#^/bible-study/search$#' => '/bible-study-search.php',

            // Bible study chapter pages
            '#^/bible-study/([a-zA-Z0-9-]+)/(\d+)$#' => '/bible-study-chapter.php?book=$1&chapter=$2',

            // Bible study book pages (must come after chapter and sub-pages)
            '#^/bible-study/([a-zA-Z0-9-]+)$#' => '/bible-study-book.php?book=$1',

            // Sermons
            '#^/sermons$#' => '/sermons.php',
            '#^/sermon/([a-zA-Z0-9-]+)$#' => '/sermon.php?slug=$1',
            '#^/sermons/series/([a-zA-Z0-9-]+)$#' => '/sermon-series.php?slug=$1',
            '#^/sermons/series$#' => '/sermons/series.php',
            '#^/sermons/speakers$#' => '/sermons/speakers.php',
            '#^/sermons/topics$#' => '/sermons/topics.php',

            // Events
            '#^/events$#' => '/events.php',
            '#^/events/([a-zA-Z0-9-]+)$#' => '/events/detail.php?slug=$1',

            // About pages
            '#^/about$#' => '/about.php',
            '#^/about/history$#' => '/about/history.php',
            '#^/about/what-we-believe$#' => '/about/what-we-believe.php',
            '#^/about/vision$#' => '/about/vision.php',
            '#^/about/dead-church$#' => '/about/dead-church.php',

            // My Studies
            '#^/my-studies$#' => '/my-studies.php',
            '#^/my-studies/saved$#' => '/my-studies-saved.php',
            '#^/my-studies/highlights$#' => '/my-studies-highlights.php',
            '#^/my-studies/history$#' => '/my-studies-history.php',

            // Reading plans
            '#^/reading-plans$#' => '/reading-plans.php',
            '#^/reading-plan/([a-zA-Z0-9-]+)/day/(\d+)$#' => '/reading-plan-day.php?plan=$1&day=$2',
            '#^/reading-plan/([a-zA-Z0-9-]+)$#' => '/reading-plan-view.php?plan=$1',

            // Blog
            '#^/blog/([a-zA-Z0-9-]+)$#' => '/blog-post.php?slug=$1',

            // Author/User profiles
            '#^/author/([^/]+)$#' => '/author.php?username=$1',
            '#^/user/([^/]+)$#' => '/author.php?username=$1',

            // API routes
            '#^/api/question-search$#' => '/api/question-search.php',
            '#^/api/user-preferences$#' => '/api/user-preferences.php',
            '#^/api/cookie-consent$#' => '/api/cookie-consent.php',
            '#^/api/newsletter-signup$#' => '/api/newsletter-signup.php',
            '#^/api/user-studies$#' => '/api/user-studies.php',
            '#^/api/cms/save$#' => '/api/cms/save.php',
            '#^/api/cms/upload$#' => '/api/cms/upload.php',
            '#^/api/cms/media$#' => '/api/cms/media.php',
            '#^/api/cms/page$#' => '/api/cms/page.php',
            '#^/api/cms/blocks$#' => '/api/cms/blocks.php',
            '#^/api/bible-study/save$#' => '/api/bible-study/save.php',

            // Sitemap
            '#^/sitemap\.xml$#' => '/sitemap.php',
        ];

        // Check each rewrite rule
        foreach ($rewrites as $pattern => $replacement) {
            if (preg_match($pattern, $path, $matches)) {
                // Build the target file path
                $target = preg_replace($pattern, $replacement, $path);

                // Parse the target for query string parameters
                $targetParts = explode('?', $target, 2);
                $targetFile = $sitePath . $targetParts[0];

                // Set query parameters if present
                if (isset($targetParts[1])) {
                    parse_str($targetParts[1], $params);
                    foreach ($params as $key => $value) {
                        // Replace capture group references ($1, $2, etc.)
                        if (preg_match('/^\$(\d+)$/', $value, $m)) {
                            $value = $matches[(int)$m[1]] ?? '';
                        }
                        $_GET[$key] = $value;
                        $_REQUEST[$key] = $value;
                    }
                }

                if (file_exists($targetFile)) {
                    return $targetFile;
                }
            }
        }

        // Check for direct .php file match (e.g., /contact -> /contact.php)
        $phpFile = $sitePath . $path . '.php';
        if (file_exists($phpFile)) {
            return $phpFile;
        }

        // Check for exact file match
        $exactFile = $sitePath . $path;
        if (file_exists($exactFile) && !is_dir($exactFile)) {
            return $exactFile;
        }

        // Check for index.php in directory
        if (is_dir($exactFile) && file_exists($exactFile . '/index.php')) {
            return $exactFile . '/index.php';
        }

        // Fall back to page.php for CMS dynamic pages
        $_GET['uri'] = ltrim($path, '/');
        $_REQUEST['uri'] = ltrim($path, '/');
        return $sitePath . '/page.php';
    }
}
