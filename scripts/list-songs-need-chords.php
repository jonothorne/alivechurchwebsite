<?php
/**
 * List songs that need chord charts and have CCLI numbers
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

$sql = "
SELECT s.id, s.title, s.ccli_number
FROM songs s
WHERE NOT EXISTS (
    SELECT 1 FROM song_chord_charts scc
    WHERE scc.song_id = s.id AND scc.chart_type = 'chords'
)
AND s.ccli_number IS NOT NULL
AND s.ccli_number <> ''
ORDER BY s.title
";

$stmt = $pdo->query($sql);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Songs without chord charts that have CCLI numbers:\n";
echo "==================================================\n\n";

foreach ($songs as $song) {
    echo "{$song['ccli_number']} | {$song['title']}\n";
}

echo "\n";
echo "Total: " . count($songs) . " songs\n";
