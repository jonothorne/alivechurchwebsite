#!/usr/bin/env php
<?php
/**
 * Image Processing Cron Job
 *
 * Processes queued images in the background for optimization.
 * Run every minute via cron:
 *   * * * * * php /path/to/cron/process-images.php >> /var/log/image-processor.log 2>&1
 *
 * Or for development, run manually:
 *   php cron/process-images.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// Change to project root
chdir(dirname(__DIR__));

// Load dependencies
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/ImageProcessor.php';

// Configuration
$maxExecutionTime = 55; // Leave buffer before next cron run
$batchSize = 5;         // Process 5 images per run
$lockFile = __DIR__ . '/../data/cache/image-processor.lock';

// Ensure cache directory exists
$cacheDir = dirname($lockFile);
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Check for existing lock (prevent overlapping runs)
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // If lock is older than 10 minutes, it's probably stale
    if (time() - $lockTime < 600) {
        echo "[" . date('Y-m-d H:i:s') . "] Another process is running. Exiting.\n";
        exit(0);
    }
    // Remove stale lock
    unlink($lockFile);
}

// Create lock file
file_put_contents($lockFile, getmypid());

// Register shutdown handler to clean up lock
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

$startTime = time();

echo "[" . date('Y-m-d H:i:s') . "] Starting image processing job\n";

try {
    $processor = new ImageProcessor();
    $totalProcessed = 0;
    $totalFailed = 0;
    $totalSaved = 0;

    // Process in batches until time limit or no more items
    while ((time() - $startTime) < $maxExecutionTime) {
        $result = $processor->processQueue($batchSize);

        if ($result['processed'] === 0 && $result['failed'] === 0) {
            // No more items to process
            echo "[" . date('Y-m-d H:i:s') . "] Queue empty. Nothing to process.\n";
            break;
        }

        $totalProcessed += $result['processed'];
        $totalFailed += $result['failed'];

        foreach ($result['details'] as $detail) {
            if ($detail['success']) {
                echo "[" . date('Y-m-d H:i:s') . "] Processed #{$detail['id']} - Saved: {$detail['saved']}\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Failed #{$detail['id']} - Error: {$detail['error']}\n";
            }
        }

        // Small delay between batches
        usleep(100000); // 100ms
    }

    // Summary
    $elapsed = time() - $startTime;
    echo "[" . date('Y-m-d H:i:s') . "] Job completed in {$elapsed}s. Processed: {$totalProcessed}, Failed: {$totalFailed}\n";

    // Cleanup old completed/failed entries (keep last 7 days)
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        DELETE FROM image_processing_queue
        WHERE status IN ('completed', 'failed')
        AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $cleaned = $stmt->rowCount();
    if ($cleaned > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$cleaned} old queue entries\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    error_log("Image processor cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
