<?php
/**
 * Search Indexing Bulk Submit Cron Job
 * Submits up to 200 pages/day to IndexNow and Google Indexing API.
 * On first run, populates the queue from all published pages, blog posts, sermons, etc.
 *
 * Run daily: 0 7 * * * php /path/to/alivechurchsite/cron/submit-indexing.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/services/IndexNowService.php';
require_once __DIR__ . '/../includes/services/GoogleIndexingAPI.php';

$pdo = getDbConnection();
$dailyLimit = 200;

echo "[" . date('Y-m-d H:i:s') . "] Starting bulk indexing submission...\n";

// Get site URL
$stmt = $pdo->prepare("SELECT config_value FROM seo_indexing_config WHERE config_key = 'indexing_site_url'");
$stmt->execute();
$siteUrl = $stmt->fetchColumn();

if (!$siteUrl) {
    echo "ERROR: No site URL configured. Set it in admin/analytics/indexing.\n";
    exit(1);
}
$siteUrl = rtrim($siteUrl, '/');

// Initialize services
$indexNow = new IndexNowService($pdo);
$google = new GoogleIndexingAPI($pdo);

$indexNowEnabled = $indexNow->isEnabled();
$googleEnabled = $google->isEnabled();

if (!$indexNowEnabled && !$googleEnabled) {
    echo "No indexing services enabled. Exiting.\n";
    exit;
}

echo "Services: IndexNow=" . ($indexNowEnabled ? 'ON' : 'OFF') . ", Google=" . ($googleEnabled ? 'ON' : 'OFF') . "\n";

// Populate queue if empty
$queueCount = $pdo->query("SELECT COUNT(*) FROM seo_indexing_queue WHERE status = 'pending'")->fetchColumn();

if ($queueCount == 0) {
    echo "Queue empty, populating from site content...\n";
    $urls = [];

    // Homepage
    $urls[] = '/';

    // Published pages
    $pages = $pdo->query("SELECT slug FROM pages WHERE published = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pages as $slug) {
        $urls[] = '/' . $slug;
    }

    // Published blog posts
    $posts = $pdo->query("SELECT slug FROM blog_posts WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($posts as $slug) {
        $urls[] = '/blog/' . $slug;
    }

    // Sermons
    $sermons = $pdo->query("SELECT slug FROM sermons WHERE published = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sermons as $slug) {
        $urls[] = '/sermon/' . $slug;
    }

    // Sermon series
    try {
        $series = $pdo->query("SELECT slug FROM sermon_series")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($series as $slug) {
            $urls[] = '/sermons/series/' . $slug;
        }
    } catch (PDOException $e) {}

    // Events
    try {
        $events = $pdo->query("SELECT slug FROM events WHERE published = 1")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($events as $slug) {
            $urls[] = '/events/' . $slug;
        }
    } catch (PDOException $e) {}

    // Reading plans
    try {
        $plans = $pdo->query("SELECT slug FROM reading_plans WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($plans as $slug) {
            $urls[] = '/reading-plan/' . $slug;
        }
    } catch (PDOException $e) {}

    // Static pages that exist but aren't in the pages table
    $staticPages = [
        '/bible-study', '/bible-study/topics', '/bible-study/questions',
        '/sermons', '/sermons/speakers', '/sermons/topics',
        '/reading-plans', '/events', '/blog', '/connect', '/visit',
        '/about', '/about/history', '/about/what-we-believe', '/about/vision',
    ];
    $urls = array_merge($urls, $staticPages);

    // Deduplicate
    $urls = array_unique($urls);

    // Insert into queue for each enabled service
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO seo_indexing_queue (url, service) VALUES (?, ?)
    ");

    $count = 0;
    foreach ($urls as $path) {
        $fullUrl = $siteUrl . $path;
        if ($indexNowEnabled) {
            $insertStmt->execute([$fullUrl, 'indexnow']);
            $count++;
        }
        if ($googleEnabled) {
            $insertStmt->execute([$fullUrl, 'google']);
            $count++;
        }
    }

    echo "Queued {$count} URL submissions (" . count($urls) . " unique URLs).\n";
}

// Process IndexNow batch (up to 200, submitted in one batch request)
if ($indexNowEnabled) {
    $stmt = $pdo->prepare("
        SELECT id, url FROM seo_indexing_queue
        WHERE service = 'indexnow' AND status = 'pending'
        ORDER BY id ASC LIMIT ?
    ");
    $stmt->execute([$dailyLimit]);
    $pending = $stmt->fetchAll();

    if (!empty($pending)) {
        $urls = array_column($pending, 'url');
        $ids = array_column($pending, 'id');

        echo "Submitting " . count($urls) . " URLs to IndexNow... ";
        $result = $indexNow->submitUrls($urls);

        $status = $result['success'] ? 'submitted' : 'error';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE seo_indexing_queue SET status = ?, submitted_at = NOW() WHERE id IN ({$placeholders})")
            ->execute(array_merge([$status], $ids));

        echo ($result['success'] ? 'OK' : 'FAILED (HTTP ' . ($result['http_code'] ?? '?') . ')') . "\n";
    } else {
        echo "IndexNow: no pending URLs.\n";
    }
}

// Process Google Indexing API (one at a time, up to 200/day quota)
if ($googleEnabled) {
    $stmt = $pdo->prepare("
        SELECT id, url FROM seo_indexing_queue
        WHERE service = 'google' AND status = 'pending'
        ORDER BY id ASC LIMIT ?
    ");
    $stmt->execute([$dailyLimit]);
    $pending = $stmt->fetchAll();

    if (!empty($pending)) {
        echo "Submitting " . count($pending) . " URLs to Google Indexing API...\n";
        $success = 0;
        $errors = 0;

        foreach ($pending as $item) {
            $result = $google->notifyUrlUpdated($item['url']);
            $status = $result['success'] ? 'submitted' : 'error';

            $pdo->prepare("UPDATE seo_indexing_queue SET status = ?, submitted_at = NOW() WHERE id = ?")
                ->execute([$status, $item['id']]);

            if ($result['success']) {
                $success++;
            } else {
                $errors++;
                // If we get a 429 (rate limit), stop
                if (($result['http_code'] ?? 0) === 429) {
                    echo "  Rate limited at {$success} submissions. Will continue tomorrow.\n";
                    break;
                }
            }

            // Small delay to be respectful
            usleep(100000); // 100ms
        }

        echo "  Google: {$success} success, {$errors} errors.\n";
    } else {
        echo "Google: no pending URLs.\n";
    }
}

// Clean up completed queue entries older than 30 days
$cleaned = $pdo->exec("DELETE FROM seo_indexing_queue WHERE status = 'submitted' AND submitted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($cleaned > 0) {
    echo "Cleaned {$cleaned} old queue entries.\n";
}

// Clean up old log entries (keep 90 days)
$cleaned = $pdo->exec("DELETE FROM seo_indexing_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
if ($cleaned > 0) {
    echo "Cleaned {$cleaned} old log entries.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
