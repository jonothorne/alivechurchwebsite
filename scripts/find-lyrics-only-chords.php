<?php
/**
 * Find chord charts that are actually just lyrics (no chord notation)
 *
 * This script checks all entries in song_chord_charts with chart_type='chords'
 * and identifies which ones don't actually contain ChordPro chord notation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get all "chord" charts
$stmt = $pdo->query("
    SELECT scc.id, scc.song_id, s.title, s.ccli_number, scc.content
    FROM song_chord_charts scc
    JOIN songs s ON s.id = scc.song_id
    WHERE scc.chart_type = 'chords'
    ORDER BY s.title
");

$lyricsOnly = [];
$hasChords = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Count ChordPro-style chords: [A], [G], [Em7], [C#m], etc.
    $chordPattern = '/\[[A-G][#b]?[a-z0-9\/]*\]/i';
    preg_match_all($chordPattern, $row['content'], $matches);
    $chordCount = count($matches[0]);

    if ($chordCount < 5) {
        // Less than 5 chords - probably just lyrics or very minimal chords
        $lyricsOnly[] = [
            'id' => $row['id'],
            'song_id' => $row['song_id'],
            'title' => $row['title'],
            'ccli_number' => $row['ccli_number'],
            'chord_count' => $chordCount,
            'preview' => substr($row['content'], 0, 200)
        ];
    } else {
        $hasChords++;
    }
}

echo "=== Chord Charts Analysis ===\n\n";
echo "Charts with real chords (5+): $hasChords\n";
echo "Charts that are lyrics-only (< 5 chords): " . count($lyricsOnly) . "\n\n";

if (!empty($lyricsOnly)) {
    echo "=== LYRICS-ONLY CHARTS (need reclassification) ===\n\n";
    foreach ($lyricsOnly as $song) {
        echo "ID: {$song['id']} | Song ID: {$song['song_id']} | {$song['title']}";
        if ($song['ccli_number']) {
            echo " (CCLI: {$song['ccli_number']})";
        }
        echo " | Chords found: {$song['chord_count']}\n";
        echo "  Preview: " . str_replace("\n", " ", substr($song['preview'], 0, 100)) . "...\n\n";
    }
}
