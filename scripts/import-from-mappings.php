<?php
/**
 * Import chord charts from CCLI-to-URL mappings
 *
 * This script reads the ccli-url-mappings.json file and imports chord charts
 * for any songs that match by CCLI number and don't have chords yet.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/services/WorshipTogetherScraper.php';

$dryRun = in_array('--dry-run', $argv);

echo "=== Import Chord Charts from URL Mappings ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

// Load mappings
$mappingsFile = __DIR__ . '/ccli-url-mappings.json';
if (!file_exists($mappingsFile)) {
    echo "Error: ccli-url-mappings.json not found\n";
    exit(1);
}

$mappings = json_decode(file_get_contents($mappingsFile), true);
if (!$mappings) {
    echo "Error: Could not parse ccli-url-mappings.json\n";
    exit(1);
}

echo "Loaded " . count($mappings) . " URL mappings\n\n";

$pdo = getDbConnection();
$scraper = new WorshipTogetherScraper();

// Find songs that match mappings and need chord charts
$stats = [
    'found' => 0,
    'imported' => 0,
    'already_has_chords' => 0,
    'not_in_db' => 0,
    'error' => 0,
];

foreach ($mappings as $ccli => $url) {
    echo "CCLI $ccli: ";

    // Find song by CCLI
    $stmt = $pdo->prepare("SELECT id, title FROM songs WHERE ccli_number = ?");
    $stmt->execute([$ccli]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$song) {
        echo "No song in database with this CCLI\n";
        $stats['not_in_db']++;
        continue;
    }

    echo "{$song['title']} ... ";

    // Check if already has chords
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM song_chord_charts WHERE song_id = ? AND chart_type = 'chords'");
    $stmt->execute([$song['id']]);
    if ($stmt->fetchColumn() > 0) {
        echo "SKIPPED (already has chords)\n";
        $stats['already_has_chords']++;
        continue;
    }

    $stats['found']++;

    // Fetch chord chart
    try {
        $details = $scraper->getSongDetails($url);

        if (empty($details['chord_chart'])) {
            echo "ERROR (no chord chart on page)\n";
            $stats['error']++;
            continue;
        }

        // Count chords
        preg_match_all('/\[[A-G][#b]?[^\]]*\]/', $details['chord_chart'], $matches);
        $chordCount = count($matches[0]);

        if ($chordCount < 5) {
            echo "ERROR (only $chordCount chords found)\n";
            $stats['error']++;
            continue;
        }

        echo "FOUND ($chordCount chords)";

        if (!$dryRun) {
            $key = $details['default_key'] ?: 'C';
            $insertStmt = $pdo->prepare("
                INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                VALUES (?, ?, 'chords', ?, 'worshiptogether', 1)
            ");
            $insertStmt->execute([$song['id'], $key, $details['chord_chart']]);
            echo " - SAVED";
            $stats['imported']++;
        }

        echo "\n";

        usleep(500000); // 0.5 second delay between requests

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $stats['error']++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total mappings: " . count($mappings) . "\n";
echo "Found in database: {$stats['found']}\n";
echo "Imported: {$stats['imported']}\n";
echo "Already has chords: {$stats['already_has_chords']}\n";
echo "Not in database: {$stats['not_in_db']}\n";
echo "Errors: {$stats['error']}\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to actually import. **\n";
}
