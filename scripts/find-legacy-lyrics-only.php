<?php
/**
 * Find songs with legacy chord_chart column that are actually just lyrics
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get all songs with chord_chart content
$stmt = $pdo->query("
    SELECT id, title, ccli_number, chord_chart, chord_chart_original
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

    // Also check for chords-above-lyrics format (A  G  Em  D on separate lines)
    $lineChordPattern = '/^[A-G][#b]?[a-z0-9]*(\s+[A-G][#b]?[a-z0-9]*)+\s*$/m';
    preg_match_all($lineChordPattern, $content, $lineMatches);
    $lineChordCount = count($lineMatches[0]);

    $totalChords = $chordCount + ($lineChordCount * 3); // Estimate 3 chords per line

    if ($totalChords < 5) {
        $lyricsOnly[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'ccli_number' => $row['ccli_number'],
            'chord_count' => $chordCount,
            'line_chord_count' => $lineChordCount,
            'preview' => substr($content, 0, 300)
        ];
    } else {
        $hasChords++;
    }
}

echo "=== Legacy chord_chart Column Analysis ===\n\n";
echo "Charts with real chords: $hasChords\n";
echo "Charts that are lyrics-only: " . count($lyricsOnly) . "\n\n";

if (!empty($lyricsOnly)) {
    echo "=== LYRICS-ONLY (need reclassification or chord import) ===\n\n";
    foreach ($lyricsOnly as $song) {
        echo "Song ID: {$song['id']} | {$song['title']}";
        if ($song['ccli_number']) {
            echo " (CCLI: {$song['ccli_number']})";
        }
        echo "\n";
        echo "  ChordPro chords: {$song['chord_count']}, Line chords: {$song['line_chord_count']}\n";
        echo "  Preview: " . str_replace("\n", "\\n", substr($song['preview'], 0, 150)) . "...\n\n";
    }
}
