<?php
/**
 * Reclassify Bot Visits
 *
 * Scans page_visits for bot user agents that may have slipped through,
 * moves them to bot_visits table, and removes them from page_visits.
 *
 * Usage:
 *   php migrations/reclassify-bots.php
 */

// Change to project root
chdir(dirname(__DIR__));

require_once 'includes/db-config.php';
require_once 'includes/BotDetector.php';

$pdo = getDbConnection();
$botDetector = new BotDetector($pdo);

echo "Scanning page_visits for bot user agents...\n";

// Get all page_visits
$stmt = $pdo->query('SELECT id, user_agent, page_url, ip_address, visited_at FROM page_visits');
$visits = $stmt->fetchAll();

echo "Found " . count($visits) . " total visits to check.\n";

$moved = 0;
$ids_to_delete = [];

foreach ($visits as $visit) {
    $botInfo = $botDetector->detect($visit['user_agent'] ?? '');

    if ($botInfo['is_bot']) {
        // Insert into bot_visits
        $insert = $pdo->prepare('
            INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([
            $botInfo['name'],
            $botInfo['category'],
            $botInfo['owner'],
            $botInfo['classification'],
            $visit['user_agent'],
            $visit['ip_address'],
            $visit['page_url'],
            $botInfo['pattern_matched'],
            $visit['visited_at']
        ]);

        $ids_to_delete[] = $visit['id'];
        $moved++;

        // Progress indicator
        if ($moved % 100 === 0) {
            echo "  Processed $moved bots...\n";
        }
    }
}

// Delete moved records from page_visits
if (!empty($ids_to_delete)) {
    // Delete in batches to avoid query size limits
    $batches = array_chunk($ids_to_delete, 500);
    foreach ($batches as $batch) {
        $placeholders = implode(',', array_fill(0, count($batch), '?'));
        $delete = $pdo->prepare("DELETE FROM page_visits WHERE id IN ($placeholders)");
        $delete->execute($batch);
    }
}

echo "\n✓ Done! Moved $moved bot visits from page_visits to bot_visits.\n";

if ($moved === 0) {
    echo "  (No bots found - your analytics are already clean!)\n";
}
