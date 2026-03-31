<?php
/**
 * Import a single chord chart from a WorshipTogether URL
 *
 * Usage: php scripts/import-from-url.php "https://www.worshiptogether.com/songs/song-name/"
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/services/WorshipTogetherScraper.php';

if ($argc < 2) {
    echo "Usage: php scripts/import-from-url.php <worshiptogether-url>\n";
    echo "Example: php scripts/import-from-url.php https://www.worshiptogether.com/songs/break-every-chain-tasha-cobbs/\n";
    exit(1);
}

$url = $argv[1];

if (!preg_match('/^https?:\/\/(www\.)?worshiptogether\.com\/songs\//', $url)) {
    echo "Error: Invalid WorshipTogether URL. Must be like: https://www.worshiptogether.com/songs/song-name/\n";
    exit(1);
}

echo "Fetching: $url\n";

$pdo = getDbConnection();
$scraper = new WorshipTogetherScraper();

try {
    $details = $scraper->getSongDetails($url);

    if (empty($details['chord_chart'])) {
        echo "Error: No chord chart found on page\n";
        exit(1);
    }

    // Count chords
    preg_match_all('/\[[A-G][#b]?[^\]]*\]/', $details['chord_chart'], $matches);
    $chordCount = count($matches[0]);

    echo "Title: {$details['title']}\n";
    echo "Artist: {$details['artist']}\n";
    echo "Key: {$details['default_key']}\n";
    echo "CCLI: {$details['ccli_number']}\n";
    echo "Chord count: $chordCount\n\n";

    // Find matching song in database
    $songId = null;

    // First try by CCLI number
    if (!empty($details['ccli_number'])) {
        $stmt = $pdo->prepare("SELECT id, title FROM songs WHERE ccli_number = ?");
        $stmt->execute([$details['ccli_number']]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($song) {
            $songId = $song['id'];
            echo "Matched by CCLI to: {$song['title']} (ID: $songId)\n";
        }
    }

    // Then try by title
    if (!$songId) {
        $stmt = $pdo->prepare("SELECT id, title FROM songs WHERE LOWER(title) = LOWER(?)");
        $stmt->execute([$details['title']]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($song) {
            $songId = $song['id'];
            echo "Matched by title to: {$song['title']} (ID: $songId)\n";
        }
    }

    if (!$songId) {
        echo "Error: No matching song found in database for '{$details['title']}' (CCLI: {$details['ccli_number']})\n";
        echo "You may need to create the song first or check the title.\n";
        exit(1);
    }

    // Check if song already has chord chart
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM song_chord_charts WHERE song_id = ? AND chart_type = 'chords'");
    $stmt->execute([$songId]);
    if ($stmt->fetchColumn() > 0) {
        echo "Warning: Song already has a chord chart. Replacing...\n";
        $pdo->prepare("DELETE FROM song_chord_charts WHERE song_id = ? AND chart_type = 'chords'")->execute([$songId]);
    }

    // Save chord chart
    $key = $details['default_key'] ?: 'C';
    $insertStmt = $pdo->prepare("
        INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
        VALUES (?, ?, 'chords', ?, 'worshiptogether', 1)
    ");
    $insertStmt->execute([$songId, $key, $details['chord_chart']]);

    echo "\nSUCCESS: Chord chart saved!\n";
    echo "Preview:\n";
    echo substr($details['chord_chart'], 0, 500) . "...\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
