<?php
/**
 * Google Search Console Sync Cron Job
 * Run daily to pull latest search performance data.
 * Example: Run daily at 6am:
 *   0 6 * * * php /path/to/alivechurchsite/cron/sync-gsc.php
 *
 * Also cleans up old 404 hit records (>90 days)
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/SeoAnalytics.php';
require_once __DIR__ . '/../includes/services/GoogleSearchConsoleAPI.php';

$pdo = getDbConnection();

echo "[" . date('Y-m-d H:i:s') . "] Starting GSC sync and SEO maintenance...\n";

// 1. Sync Google Search Console data
$gsc = new GoogleSearchConsoleAPI($pdo);
if ($gsc->isConnected()) {
    echo "Syncing GSC data (last 28 days)... ";
    try {
        $result = $gsc->sync(28);
        echo "Done. Fetched {$result['fetched']} rows, stored {$result['stored']}.\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "GSC not connected, skipping sync.\n";
}

// 2. Clean up old 404 hit records (keep 90 days)
echo "Cleaning up old 404 records... ";
try {
    $stmt = $pdo->exec("DELETE FROM seo_404_hits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    echo "Removed {$stmt} old records.\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

// 3. Clean up old GSC data (keep 90 days)
echo "Cleaning up old GSC data... ";
try {
    $stmt = $pdo->exec("DELETE FROM seo_gsc_data WHERE data_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
    echo "Removed {$stmt} old records.\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
