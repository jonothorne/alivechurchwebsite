<?php
/**
 * Delete lyrics entries for songs that already have chord charts
 * The chords contain the lyrics, so storing both is redundant
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$dryRun = in_array('--dry-run', $argv);

echo "=== Cleanup Redundant Lyrics Entries ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();

// Find songs that have BOTH a chords entry AND a lyrics entry
$stmt = $pdo->query("
    SELECT s.id, s.title, s.ccli_number,
           chord.id as chord_id, chord.source as chord_source,
           lyrics.id as lyrics_id, lyrics.source as lyrics_source
    FROM songs s
    JOIN song_chord_charts chord ON chord.song_id = s.id AND chord.chart_type = 'chords'
    JOIN song_chord_charts lyrics ON lyrics.song_id = s.id AND lyrics.chart_type = 'lyrics'
    ORDER BY s.title
");

$redundant = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($redundant) . " songs with both chords AND lyrics entries\n\n";

$deleted = 0;

foreach ($redundant as $song) {
    echo "{$song['title']}";
    if ($song['ccli_number']) {
        echo " (CCLI: {$song['ccli_number']})";
    }
    echo "\n";
    echo "  Chord entry: ID {$song['chord_id']} ({$song['chord_source']})\n";
    echo "  Lyrics entry: ID {$song['lyrics_id']} ({$song['lyrics_source']}) -> DELETE\n";

    if (!$dryRun) {
        $deleteStmt = $pdo->prepare("DELETE FROM song_chord_charts WHERE id = ?");
        $deleteStmt->execute([$song['lyrics_id']]);
    }
    $deleted++;
}

echo "\n=== SUMMARY ===\n";
echo "Redundant lyrics entries deleted: $deleted\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to actually delete. **\n";
}
