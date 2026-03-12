<?php
/**
 * Dynamic Sitemap Generator
 * Generates XML sitemap(s) for all pages on the site
 *
 * Supports sitemap index for large sites (50,000+ URLs)
 * Google limit is 50,000 URLs per sitemap file
 */

require_once __DIR__ . '/includes/db-config.php';

// Configuration
$baseUrl = 'https://alivechur.ch';
$maxUrlsPerSitemap = 45000; // Leave buffer below Google's 50k limit

// Determine if we're requesting a specific sitemap or the index
$requestedSitemap = $_GET['sitemap'] ?? null;

// Get database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    exit('Database connection failed');
}

/**
 * Collect all URLs from the site
 */
function getAllUrls($pdo, $baseUrl) {
    $urls = [];

    // =========================================
    // STATIC PAGES (high priority)
    // =========================================
    $staticPages = [
        ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => '/visit', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => '/about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => '/watch', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['url' => '/events', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['url' => '/connect', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['url' => '/blog', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['url' => '/give', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['url' => '/ministries', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['url' => '/next-steps', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['url' => '/prayer', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['url' => '/groups/join', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['url' => '/serve/apply', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['url' => '/next-steps/baptism', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['url' => '/bible-study', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => '/bible-study/search', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ['url' => '/bible-study/topics', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['url' => '/reading-plans', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['url' => '/login', 'priority' => '0.3', 'changefreq' => 'monthly'],
        ['url' => '/register', 'priority' => '0.3', 'changefreq' => 'monthly'],
    ];

    foreach ($staticPages as $page) {
        $urls[] = [
            'loc' => $baseUrl . $page['url'],
            'priority' => $page['priority'],
            'changefreq' => $page['changefreq']
        ];
    }

    // =========================================
    // CMS PAGES (from pages table)
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug, updated_at FROM pages WHERE published = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/' . $row['slug'],
                'lastmod' => date('Y-m-d', strtotime($row['updated_at'])),
                'priority' => '0.6',
                'changefreq' => 'weekly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // BLOG POSTS
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/blog/' . $row['slug'],
                'lastmod' => date('Y-m-d', strtotime($row['updated_at'])),
                'priority' => '0.7',
                'changefreq' => 'monthly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // EVENTS
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug, updated_at FROM events WHERE published = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/events/' . $row['slug'],
                'lastmod' => date('Y-m-d', strtotime($row['updated_at'])),
                'priority' => '0.6',
                'changefreq' => 'weekly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // BIBLE BOOKS (main book pages)
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug FROM bible_books ORDER BY id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/bible-study/' . $row['slug'],
                'priority' => '0.7',
                'changefreq' => 'monthly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // BIBLE STUDIES (individual chapters)
    // =========================================
    try {
        $stmt = $pdo->query("
            SELECT b.slug as book_slug, s.chapter, s.updated_at
            FROM bible_studies s
            JOIN bible_books b ON s.book_id = b.id
            ORDER BY b.id, s.chapter
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/bible-study/' . $row['book_slug'] . '/' . $row['chapter'],
                'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
                'priority' => '0.6',
                'changefreq' => 'monthly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // BIBLE STUDY TOPICS
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug FROM bible_study_topics WHERE is_active = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/bible-study/topics/' . $row['slug'],
                'priority' => '0.6',
                'changefreq' => 'weekly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // BIBLE STUDY QUESTIONS (SEO pages)
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug, updated_at FROM bible_study_questions");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/bible-study/questions/' . $row['slug'],
                'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
                'priority' => '0.5',
                'changefreq' => 'monthly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // READING PLANS
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug, duration_days, updated_at FROM reading_plans WHERE is_active = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Plan overview page
            $urls[] = [
                'loc' => $baseUrl . '/reading-plan/' . $row['slug'],
                'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
                'priority' => '0.6',
                'changefreq' => 'weekly'
            ];

            // Individual day pages
            for ($day = 1; $day <= $row['duration_days']; $day++) {
                $urls[] = [
                    'loc' => $baseUrl . '/reading-plan/' . $row['slug'] . '/day/' . $day,
                    'priority' => '0.5',
                    'changefreq' => 'monthly'
                ];
            }
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // AUTHORS (blog posts and bible studies)
    // =========================================
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT u.username
            FROM users u
            WHERE u.id IN (
                SELECT DISTINCT author_id FROM blog_posts WHERE status = 'published' AND author_id IS NOT NULL
                UNION
                SELECT DISTINCT author_id FROM bible_studies WHERE author_id IS NOT NULL
            )
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/author/' . $row['username'],
                'priority' => '0.5',
                'changefreq' => 'weekly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // USER PROFILES (active users)
    // =========================================
    try {
        $stmt = $pdo->query("
            SELECT username, updated_at
            FROM users
            WHERE active = 1
            ORDER BY username
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/user/' . $row['username'],
                'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
                'priority' => '0.4',
                'changefreq' => 'weekly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    // =========================================
    // SERMON SERIES (if exists)
    // =========================================
    try {
        $stmt = $pdo->query("SELECT slug, updated_at FROM sermon_series");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = [
                'loc' => $baseUrl . '/watch/' . $row['slug'],
                'lastmod' => $row['updated_at'] ? date('Y-m-d', strtotime($row['updated_at'])) : null,
                'priority' => '0.6',
                'changefreq' => 'weekly'
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist
    }

    return $urls;
}

/**
 * Generate XML sitemap
 */
function generateSitemap($urls) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";

        if (!empty($url['lastmod'])) {
            $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
        }

        if (!empty($url['changefreq'])) {
            $xml .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
        }

        if (!empty($url['priority'])) {
            $xml .= "    <priority>" . $url['priority'] . "</priority>\n";
        }

        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Generate sitemap index for large sites
 */
function generateSitemapIndex($sitemapCount, $baseUrl) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    for ($i = 1; $i <= $sitemapCount; $i++) {
        $xml .= "  <sitemap>\n";
        $xml .= "    <loc>" . $baseUrl . "/sitemap.xml?sitemap=" . $i . "</loc>\n";
        $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $xml .= "  </sitemap>\n";
    }

    $xml .= '</sitemapindex>';

    return $xml;
}

// Collect all URLs
$allUrls = getAllUrls($pdo, $baseUrl);
$totalUrls = count($allUrls);

// Set content type
header('Content-Type: application/xml; charset=utf-8');

// If we have more URLs than the limit, use sitemap index
if ($totalUrls > $maxUrlsPerSitemap) {
    $sitemapCount = ceil($totalUrls / $maxUrlsPerSitemap);

    if ($requestedSitemap === null) {
        // Return sitemap index
        echo generateSitemapIndex($sitemapCount, $baseUrl);
    } else {
        // Return specific sitemap
        $sitemapNum = intval($requestedSitemap);
        $start = ($sitemapNum - 1) * $maxUrlsPerSitemap;
        $urls = array_slice($allUrls, $start, $maxUrlsPerSitemap);
        echo generateSitemap($urls);
    }
} else {
    // Single sitemap is sufficient
    echo generateSitemap($allUrls);
}
