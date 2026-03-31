<?php
/**
 * Test URL generation for WorshipTogether
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/services/WorshipTogetherScraper.php';

function generateUrlSlugs(string $title, ?string $artist): array {
    $slugs = [];

    // Clean title for URL
    $titleSlug = strtolower(trim($title));
    $titleSlug = preg_replace('/[^a-z0-9\s-]/', '', $titleSlug);
    $titleSlug = preg_replace('/\s+/', '-', $titleSlug);
    $titleSlug = preg_replace('/-+/', '-', $titleSlug);
    $titleSlug = trim($titleSlug, '-');

    $slugs[] = $titleSlug;

    if ($artist) {
        $artistSlug = strtolower(trim($artist));
        $artistSlug = preg_replace('/\s+(and|&|,).*$/', '', $artistSlug);
        $artistSlug = preg_replace('/[^a-z0-9\s-]/', '', $artistSlug);
        $artistSlug = preg_replace('/\s+/', '-', $artistSlug);
        $artistSlug = preg_replace('/-+/', '-', $artistSlug);
        $artistSlug = trim($artistSlug, '-');

        $slugs[] = $titleSlug . '-' . $artistSlug;
    }

    return array_unique($slugs);
}

$testSongs = [
    ['title' => 'Battle Belongs', 'artist' => 'Brian Johnson and Phil Wickham'],
    ['title' => 'Build My Life', 'artist' => 'Brett Younker, Karl Martin, Kirby Kable, Pat Barrett, and Matt Redman'],
    ['title' => 'Great Things', 'artist' => 'Jonas Myrin and Phil Wickham'],
    ['title' => 'Egypt', 'artist' => 'Cory Asbury'],
    ['title' => 'Tremble', 'artist' => 'Hank Bentley'],
];

$scraper = new WorshipTogetherScraper();

foreach ($testSongs as $song) {
    echo "\n=== Testing: {$song['title']} ===\n";
    $slugs = generateUrlSlugs($song['title'], $song['artist']);
    echo "Generated slugs: " . implode(', ', $slugs) . "\n";

    foreach ($slugs as $slug) {
        $url = "https://www.worshiptogether.com/songs/{$slug}/";
        echo "  Trying: $url ... ";

        try {
            $details = $scraper->getSongDetails($url);
            if (!empty($details['chord_chart'])) {
                preg_match_all('/\[[A-G][#b]?[^\]]*\]/', $details['chord_chart'], $matches);
                $chordCount = count($matches[0]);
                if ($chordCount >= 5) {
                    echo "FOUND! ($chordCount chords)\n";
                    break;
                } else {
                    echo "Found but only $chordCount chords\n";
                }
            } else {
                echo "No chord chart\n";
            }
        } catch (Exception $e) {
            echo "Failed: " . $e->getMessage() . "\n";
        }

        usleep(200000); // Small delay
    }
}

echo "\n";
