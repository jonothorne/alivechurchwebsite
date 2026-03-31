<?php
/**
 * Find chord charts that might be misclassified (have section tags but few actual chords)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Get all "chord" charts
$stmt = $pdo->query("
    SELECT scc.id, scc.song_id, s.title, s.ccli_number, scc.content, scc.source
    FROM song_chord_charts scc
    JOIN songs s ON s.id = scc.song_id
    WHERE scc.chart_type = 'chords'
    ORDER BY s.title
");

$misclassified = [];
$hasChords = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Count ChordPro-style chords: [A], [G], [Em7], [C#m], etc.
    $chordPattern = '/\[[A-G][#b]?[a-z0-9\/]*\]/i';
    preg_match_all($chordPattern, $row['content'], $matches);
    $chordCount = count($matches[0]);

    // Check for section markers (verse, chorus, bridge, etc.)
    $sectionPattern = '/(\{verse|\{chorus|\{bridge|\{pre-chorus|\{tag|\{intro|\{outro|^verse\s|^chorus\s|^bridge\s)/im';
    preg_match_all($sectionPattern, $row['content'], $sectionMatches);
    $sectionCount = count($sectionMatches[0]);

    // Suspect if has sections but fewer than 10 chords
    if ($chordCount < 10) {
        $misclassified[] = [
            'id' => $row['id'],
            'song_id' => $row['song_id'],
            'title' => $row['title'],
            'ccli_number' => $row['ccli_number'],
            'chord_count' => $chordCount,
            'section_count' => $sectionCount,
            'source' => $row['source'],
            'preview' => substr($row['content'], 0, 400)
        ];
    } else {
        $hasChords++;
    }
}

echo "=== Chord Charts Analysis ===\n\n";
echo "Charts with sufficient chords (10+): $hasChords\n";
echo "Charts with fewer than 10 chords: " . count($misclassified) . "\n\n";

if (!empty($misclassified)) {
    echo "=== POTENTIAL MISCLASSIFICATIONS (fewer than 10 chords) ===\n\n";
    foreach ($misclassified as $song) {
        echo "ID: {$song['id']} | {$song['title']}";
        if ($song['ccli_number']) {
            echo " (CCLI: {$song['ccli_number']})";
        }
        echo "\n";
        echo "  Chords: {$song['chord_count']} | Sections: {$song['section_count']} | Source: {$song['source']}\n";
        echo "  Preview: " . str_replace("\n", "\\n", substr($song['preview'], 0, 200)) . "...\n\n";
    }
}
