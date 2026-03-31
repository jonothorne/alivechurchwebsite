<?php
/**
 * Remove duplicate chord chart entries for the same song
 * Keeps the entry with the most chords
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$dryRun = in_array('--dry-run', $argv);

echo "=== Cleanup Duplicate Chord Entries ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();

// Find songs with multiple chord entries
$stmt = $pdo->query("
    SELECT s.id as song_id, s.title
    FROM songs s
    JOIN song_chord_charts scc ON scc.song_id = s.id AND scc.chart_type = 'chords'
    GROUP BY s.id, s.title
    HAVING COUNT(*) > 1
    ORDER BY s.title
");

$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($duplicates) . " songs with duplicate chord entries\n\n";

$deleted = 0;

foreach ($duplicates as $song) {
    // Get all chord entries for this song
    $entryStmt = $pdo->prepare("
        SELECT id, source, content,
               (LENGTH(content) - LENGTH(REPLACE(content, '[', ''))) as chord_count
        FROM song_chord_charts
        WHERE song_id = ? AND chart_type = 'chords'
        ORDER BY
            (LENGTH(content) - LENGTH(REPLACE(content, '[', ''))) DESC,
            id ASC
    ");
    $entryStmt->execute([$song['song_id']]);
    $entries = $entryStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "{$song['title']}\n";

    // Keep the first one (most chords), delete the rest
    $keep = array_shift($entries);
    echo "  KEEP: ID {$keep['id']} ({$keep['source']}) - {$keep['chord_count']} chords\n";

    foreach ($entries as $entry) {
        echo "  DELETE: ID {$entry['id']} ({$entry['source']}) - {$entry['chord_count']} chords\n";
        if (!$dryRun) {
            $deleteStmt = $pdo->prepare("DELETE FROM song_chord_charts WHERE id = ?");
            $deleteStmt->execute([$entry['id']]);
        }
        $deleted++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Duplicate entries deleted: $deleted\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to actually delete. **\n";
}
