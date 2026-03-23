<?php
/**
 * Welcome Journey Email Processor
 *
 * Cron job to process and send queued welcome journey emails.
 *
 * Recommended cron schedule:
 *   Run every 5 minutes: /usr/bin/php /path/to/cron/welcome-journey.php
 *
 * This runs every 5 minutes to check for emails that need to be sent.
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI access only');
}

// Set timezone
date_default_timezone_set('Europe/London');

// Load dependencies
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/WelcomeJourney.php';

// Configuration
define('MAX_EXECUTION_TIME', 55); // seconds - leave buffer before next cron run
define('BATCH_SIZE', 20); // Max emails to process per run
define('LOCK_FILE', __DIR__ . '/../storage/welcome-journey.lock');
define('LOCK_TIMEOUT', 300); // 5 minutes - if lock older than this, assume crashed

// Logging function
function logMessage(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

// Check/create lock file to prevent overlapping runs
function acquireLock(): bool
{
    if (file_exists(LOCK_FILE)) {
        $lockTime = filemtime(LOCK_FILE);
        if (time() - $lockTime < LOCK_TIMEOUT) {
            return false; // Another process is running
        }
        // Lock is stale, remove it
        unlink(LOCK_FILE);
        logMessage("Removed stale lock file");
    }

    file_put_contents(LOCK_FILE, getmypid());
    return true;
}

function releaseLock(): void
{
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}

// Start processing
logMessage("=== Welcome Journey Processor Started ===");
$startTime = microtime(true);

// Acquire lock
if (!acquireLock()) {
    logMessage("Another process is running. Exiting.");
    exit(0);
}

// Register shutdown handler to release lock
register_shutdown_function('releaseLock');

try {
    // Connect to database
    $pdo = getDbConnection();
    logMessage("Database connected");

    // Initialize WelcomeJourney
    $welcomeJourney = new WelcomeJourney($pdo);

    // Process pending emails
    logMessage("Processing email queue...");
    $results = $welcomeJourney->processQueue(BATCH_SIZE);

    logMessage("Results: Sent={$results['sent']}, Failed={$results['failed']}, Skipped={$results['skipped']}");

    // Check and complete finished journeys
    $completed = $welcomeJourney->checkAndCompleteJourneys();
    if ($completed > 0) {
        logMessage("Marked {$completed} journeys as completed");
    }

    // Log execution time
    $executionTime = round(microtime(true) - $startTime, 2);
    logMessage("Execution time: {$executionTime}s");

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    error_log("Welcome Journey Cron Error: " . $e->getMessage());
}

logMessage("=== Welcome Journey Processor Finished ===\n");
