<?php
/**
 * Bulk Import Chord Charts from WorshipTogether
 *
 * This script finds all songs without chord charts and attempts to
 * download them from WorshipTogether by constructing direct URLs.
 *
 * Usage: php scripts/bulk-import-chords.php [--dry-run] [--limit=N]
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

echo "=== Bulk Chord Chart Import from WorshipTogether ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

$pdo = getDbConnection();
$scraper = new WorshipTogetherScraper();

// Find songs that need chord charts
// Either: no chart at all, or only lyrics (no chords)
$sql = "
    SELECT s.id, s.title, s.artist, s.ccli_number,
           (SELECT COUNT(*) FROM song_chord_charts scc WHERE scc.song_id = s.id AND scc.chart_type = 'chords') as has_chords
    FROM songs s
    WHERE NOT EXISTS (
        SELECT 1 FROM song_chord_charts scc
        WHERE scc.song_id = s.id AND scc.chart_type = 'chords'
    )
    ORDER BY s.title
";

if ($limit) {
    $sql .= " LIMIT $limit";
}

$stmt = $pdo->query($sql);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($songs) . " songs without chord charts\n\n";

$stats = [
    'total' => count($songs),
    'found' => 0,
    'not_found' => 0,
    'error' => 0,
    'skipped' => 0,
];

$notFound = [];
$errors = [];

/**
 * Generate possible URL slugs for a song
 * WorshipTogether URLs follow the pattern: /songs/{title-slug}-{artist-slug}/
 * where artist-slug is the PERFORMING artist, not the songwriters
 */
function generateUrlSlugs(string $title, ?string $artist): array {
    $slugs = [];

    // Clean title for URL
    $titleSlug = strtolower(trim($title));
    $titleSlug = preg_replace('/[^a-z0-9\s-]/', '', $titleSlug);
    $titleSlug = preg_replace('/\s+/', '-', $titleSlug);
    $titleSlug = preg_replace('/-+/', '-', $titleSlug);
    $titleSlug = trim($titleSlug, '-');

    // Common worship band/artist suffixes to try for EVERY song
    // These are the main performing artists on WorshipTogether
    $commonArtists = [
        'hillsong-worship',
        'hillsong-united',
        'elevation-worship',
        'bethel-music',
        'bethel',
        'chris-tomlin',
        'phil-wickham',
        'passion',
        'matt-redman',
        'jesus-culture',
        'kari-jobe',
        'cody-carnes',
        'crowder',
        'brandon-lake',
        'pat-barrett',
        'one-sonic-society',
        'all-sons-daughters',
        'maverick-city-music',
        'upperroom',
        'vertical-worship',
        'the-belonging-co',
        'mosaic-msc',
        'planetshakers',
        'gateway-worship',
        'north-point-worship',
        'leeland',
        'paul-baloche',
        'brian-johnson',
        'jenn-johnson',
        'jeremy-riddle',
        'steffany-gretzinger',
        'kalley-heiligenthal',
        'sean-curran',
        'kristian-stanfill',
        'david-crowder',
        'lauren-daigle',
        'tauren-wells',
        'zach-williams',
        'we-the-kingdom',
        'housefires',
        'for-king-country',
        'casting-crowns',
        'mercy-me',
        'michael-w-smith',
        'amy-grant',
        'natalie-grant',
        'cece-winans',
        'tasha-cobbs-leonard',
        'tye-tribbett',
        'william-mcdowell',
        'israel-houghton',
        'sinach',
        'darlene-zschech',
        'hillsong-young-free',
    ];

    // First, try just the title (some songs have unique titles)
    $slugs[] = $titleSlug;

    // Try title with each common artist
    foreach ($commonArtists as $artistSlug) {
        $slugs[] = $titleSlug . '-' . $artistSlug;
    }

    // If we have an artist string, also try extracting names from it
    if ($artist) {
        $artistLower = strtolower($artist);

        // Extract first name from artist string (e.g., "Brian Johnson and Phil Wickham" -> "brian-johnson")
        $firstName = preg_replace('/\s+(and|&|,|feat\.?|featuring|with).*$/i', '', $artist);
        $firstSlug = preg_replace('/[^a-z0-9\s-]/', '', strtolower($firstName));
        $firstSlug = preg_replace('/\s+/', '-', $firstSlug);
        $firstSlug = trim($firstSlug, '-');
        if ($firstSlug) {
            $slugs[] = $titleSlug . '-' . $firstSlug;
        }

        // Try to detect band names in the artist string
        $bandMappings = [
            'hillsong' => 'hillsong-worship',
            'elevation' => 'elevation-worship',
            'bethel' => 'bethel-music',
            'passion' => 'passion',
            'maverick' => 'maverick-city-music',
            'jesus culture' => 'jesus-culture',
            'vertical' => 'vertical-worship',
            'gateway' => 'gateway-worship',
            'north point' => 'north-point-worship',
            'upperroom' => 'upperroom',
            'housefires' => 'housefires',
        ];

        foreach ($bandMappings as $keyword => $slug) {
            if (stripos($artistLower, $keyword) !== false) {
                $slugs[] = $titleSlug . '-' . $slug;
            }
        }
    }

    return array_unique($slugs);
}

foreach ($songs as $index => $song) {
    $progress = ($index + 1) . "/" . count($songs);
    echo "[$progress] {$song['title']}";
    if ($song['artist']) {
        echo " - {$song['artist']}";
    }
    echo "... ";

    try {
        // Generate possible URLs
        $slugs = generateUrlSlugs($song['title'], $song['artist']);
        $found = false;
        $details = null;

        foreach ($slugs as $slug) {
            $url = "/songs/{$slug}/";

            try {
                $details = $scraper->getSongDetails($url);

                if (!empty($details['chord_chart'])) {
                    // Check if the chord chart actually has chords
                    $chordCount = preg_match_all('/\[[A-G][#b]?[^\]]*\]/', $details['chord_chart']);
                    if ($chordCount >= 5) {
                        $found = true;
                        break;
                    }
                }
            } catch (Exception $e) {
                // URL didn't work, try next
                continue;
            }

            usleep(200000); // Small delay between URL attempts
        }

        if (!$found || !$details || empty($details['chord_chart'])) {
            echo "NOT FOUND\n";
            $stats['not_found']++;
            $notFound[] = $song;
            continue;
        }

        echo "FOUND";

        if (!$dryRun) {
            // Save to database
            $key = $details['default_key'] ?: 'C';

            $insertStmt = $pdo->prepare("
                INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                VALUES (?, ?, 'chords', ?, 'worshiptogether', 1)
            ");
            $insertStmt->execute([$song['id'], $key, $details['chord_chart']]);

            // Update song with any missing metadata
            $updates = [];
            $params = [];

            if (empty($song['ccli_number']) && !empty($details['ccli_number'])) {
                $updates[] = "ccli_number = ?";
                $params[] = $details['ccli_number'];
            }

            if (!empty($updates)) {
                $params[] = $song['id'];
                $pdo->prepare("UPDATE songs SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            }

            echo " - SAVED";
        }

        echo "\n";
        $stats['found']++;

        // Rate limiting - be nice to the server
        usleep(500000); // 0.5 second delay between requests

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
    echo "\n=== SONGS NOT FOUND (need manual import) ===\n";
    foreach ($notFound as $song) {
        echo "- {$song['title']}";
        if ($song['artist']) {
            echo " - {$song['artist']}";
        }
        if ($song['ccli_number']) {
            echo " (CCLI: {$song['ccli_number']})";
        }
        echo "\n";
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
