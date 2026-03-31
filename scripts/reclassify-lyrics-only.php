<?php
/**
 * Reclassify lyrics-only content from legacy chord_chart column
 *
 * This script:
 * 1. Finds songs where chord_chart column has lyrics-only content (no ChordPro chords)
 * 2. If they don't already have a lyrics entry in song_chord_charts, creates one
 * 3. Clears the legacy chord_chart column
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$dryRun = in_array('--dry-run', $argv);

echo "=== Reclassify Lyrics-Only Content ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();

// Get all songs with chord_chart content
$stmt = $pdo->query("
    SELECT id, title, ccli_number, chord_chart, chord_chart_key
    FROM songs
    WHERE chord_chart IS NOT NULL AND chord_chart != ''
    ORDER BY title
");

$lyricsOnly = [];
$hasChords = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $content = $row['chord_chart'];

    // Count ChordPro-style chords: [A], [G], [Em7], [C#m], etc.
    $chordPattern = '/\[[A-G][#b]?[a-z0-9\/]*\]/i';
    preg_match_all($chordPattern, $content, $matches);
    $chordCount = count($matches[0]);

    if ($chordCount < 5) {
        $lyricsOnly[] = $row;
    } else {
        $hasChords++;
    }
}

echo "Songs with real chords in legacy column: $hasChords\n";
echo "Songs with lyrics-only in legacy column: " . count($lyricsOnly) . "\n\n";

$stats = [
    'moved_to_lyrics' => 0,
    'already_has_lyrics' => 0,
    'cleared' => 0,
];

foreach ($lyricsOnly as $song) {
    echo "{$song['title']}";
    if ($song['ccli_number']) {
        echo " (CCLI: {$song['ccli_number']})";
    }
    echo " ... ";

    // Check if already has a lyrics entry
    $checkStmt = $pdo->prepare("
        SELECT id FROM song_chord_charts
        WHERE song_id = ? AND chart_type = 'lyrics'
    ");
    $checkStmt->execute([$song['id']]);
    $existingLyrics = $checkStmt->fetchColumn();

    if ($existingLyrics) {
        echo "SKIP (already has lyrics entry)\n";
        $stats['already_has_lyrics']++;
    } else {
        // Create lyrics entry
        if (!$dryRun) {
            $insertStmt = $pdo->prepare("
                INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                VALUES (?, ?, 'lyrics', ?, 'legacy', 1)
            ");
            $insertStmt->execute([
                $song['id'],
                $song['chord_chart_key'] ?: 'C',  // Default to C if no key specified
                $song['chord_chart']
            ]);
        }
        echo "MOVED to lyrics";
        $stats['moved_to_lyrics']++;
    }

    // Clear the legacy chord_chart column
    if (!$dryRun) {
        $clearStmt = $pdo->prepare("UPDATE songs SET chord_chart = NULL WHERE id = ?");
        $clearStmt->execute([$song['id']]);
    }
    echo " - CLEARED legacy column\n";
    $stats['cleared']++;
}

echo "\n=== SUMMARY ===\n";
echo "Moved to lyrics table: {$stats['moved_to_lyrics']}\n";
echo "Already had lyrics: {$stats['already_has_lyrics']}\n";
echo "Legacy column cleared: {$stats['cleared']}\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to actually make changes. **\n";
}
