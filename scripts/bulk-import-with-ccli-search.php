<?php
/**
 * Bulk Import Chord Charts from WorshipTogether using CCLI Search
 *
 * This script searches WorshipTogether by CCLI number using Puppeteer,
 * then scrapes the chord chart from the found song page.
 *
 * Usage: php scripts/bulk-import-with-ccli-search.php [--dry-run] [--limit=N]
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/services/WorshipTogetherScraper.php';

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$limit = null;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int)$m[1];
    }
}

echo "=== Bulk Chord Chart Import with CCLI Search ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();
$scraper = new WorshipTogetherScraper();

// Find songs that need chord charts and have CCLI numbers
$sql = "
    SELECT s.id, s.title, s.artist, s.ccli_number
    FROM songs s
    WHERE NOT EXISTS (
        SELECT 1 FROM song_chord_charts scc
        WHERE scc.song_id = s.id AND scc.chart_type = 'chords'
    )
    AND s.ccli_number IS NOT NULL
    AND s.ccli_number <> ''
    ORDER BY s.title
";

if ($limit) {
    $sql .= " LIMIT $limit";
}

$stmt = $pdo->query($sql);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($songs) . " songs without chord charts (with CCLI numbers)\n\n";

$stats = [
    'total' => count($songs),
    'found' => 0,
    'not_found' => 0,
    'error' => 0,
];

$notFound = [];
$errors = [];

/**
 * Search WorshipTogether by CCLI number using the Node.js script
 */
function searchByCCLI(string $ccli): ?string {
    $scriptPath = __DIR__ . '/search-by-ccli.js';
    $output = [];
    $returnCode = 0;

    exec("node " . escapeshellarg($scriptPath) . " " . escapeshellarg($ccli) . " 2>/dev/null", $output, $returnCode);

    if ($returnCode === 0 && !empty($output[0])) {
        return trim($output[0]);
    }

    return null;
}

foreach ($songs as $index => $song) {
    $progress = ($index + 1) . "/" . count($songs);
    echo "[$progress] {$song['title']} (CCLI: {$song['ccli_number']})... ";

    try {
        // Search by CCLI number
        $url = searchByCCLI($song['ccli_number']);

        if (!$url) {
            echo "NOT FOUND\n";
            $stats['not_found']++;
            $notFound[] = $song;
            continue;
        }

        echo "FOUND\n  URL: $url\n  ";

        // Fetch chord chart from the URL
        $details = $scraper->getSongDetails($url);

        if (empty($details['chord_chart'])) {
            echo "ERROR: No chord chart on page\n";
            $stats['error']++;
            $errors[] = ['song' => $song, 'error' => 'No chord chart on page'];
            continue;
        }

        // Count chords to verify it's a real chord chart
        preg_match_all('/\[[A-G][#b]?[^\]]*\]/', $details['chord_chart'], $matches);
        $chordCount = count($matches[0]);

        if ($chordCount < 5) {
            echo "ERROR: Only $chordCount chords found\n";
            $stats['error']++;
            $errors[] = ['song' => $song, 'error' => "Only $chordCount chords"];
            continue;
        }

        echo "SCRAPED ($chordCount chords)";

        if (!$dryRun) {
            $key = $details['default_key'] ?: 'C';
            $insertStmt = $pdo->prepare("
                INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                VALUES (?, ?, 'chords', ?, 'worshiptogether', 1)
            ");
            $insertStmt->execute([$song['id'], $key, $details['chord_chart']]);
            echo " - SAVED";
        }

        echo "\n";
        $stats['found']++;

        // Small delay between requests
        usleep(500000);

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $stats['error']++;
        $errors[] = ['song' => $song, 'error' => $e->getMessage()];
    }
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Total songs processed: {$stats['total']}\n";
echo "Found & imported: {$stats['found']}\n";
echo "Not found: {$stats['not_found']}\n";
echo "Errors: {$stats['error']}\n";

if (!empty($notFound)) {
    echo "\n=== SONGS NOT FOUND (not on WorshipTogether) ===\n";
    foreach ($notFound as $song) {
        echo "- {$song['title']} (CCLI: {$song['ccli_number']})\n";
    }
}

if (!empty($errors)) {
    echo "\n=== ERRORS ===\n";
    foreach ($errors as $err) {
        echo "- {$err['song']['title']}: {$err['error']}\n";
    }
}

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to actually import. **\n";
}
