<?php
/**
 * Maintenance Cron Job
 * Run this periodically (every 5-15 minutes) via cron.
 * Example: Run every 5 minutes:
 *   php /path/to/alivechurchsite/cron/maintenance.php
 *
 * Tasks:
 * - Flush batched analytics data to database
 * - Clean up expired user sessions
 * - Clear expired cache files
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/Analytics.php';
require_once __DIR__ . '/../includes/SiteCache.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDbConnection();

echo "[" . date('Y-m-d H:i:s') . "] Starting maintenance tasks...\n";

// 1. Flush batched analytics
echo "Flushing analytics batch... ";
try {
    $analytics = new Analytics($pdo);
    $analytics->flushBatch();
    echo "done\n";
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . "\n";
}

// 2. Clean up expired user sessions
echo "Cleaning expired sessions... ";
try {
    $auth = new Auth($pdo);
    $auth->cleanupExpiredSessions();
    echo "done\n";
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . "\n";
}

// 3. Clean up expired cache files
echo "Cleaning expired cache... ";
try {
    $cacheDir = __DIR__ . '/../data/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        $cleaned = 0;
        foreach ($files as $file) {
            $data = @unserialize(file_get_contents($file));
            if ($data && isset($data['expires']) && $data['expires'] !== 0 && $data['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }
        echo "cleaned $cleaned files\n";
    } else {
        echo "cache dir not found\n";
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . "\n";
}

// 4. Clean up old activity log entries (older than 90 days)
echo "Cleaning old activity logs... ";
try {
    $stmt = $pdo->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "deleted $deleted entries\n";
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . "\n";
}

// 5. Clean up old page visits (older than 365 days) - optional, for large sites
echo "Cleaning old page visits... ";
try {
    $stmt = $pdo->prepare("DELETE FROM page_visits WHERE visited_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "deleted $deleted entries\n";
} catch (Exception $e) {
    echo "error: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Maintenance complete.\n";
