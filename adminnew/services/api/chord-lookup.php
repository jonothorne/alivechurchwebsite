<?php
/**
 * Chord Lookup API
 *
 * Searches for chord charts across multiple sources.
 * Priority: SongSelect (if configured) -> WorshipTogether -> Essential Worship
 */

ob_start();
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

function json_response($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    require_once __DIR__ . '/../../../includes/Auth.php';
    require_once __DIR__ . '/../../../includes/db-config.php';
    require_once __DIR__ . '/../../../includes/services/SongSelectAPI.php';
    require_once __DIR__ . '/../../../includes/services/WorshipTogetherScraper.php';
    require_once __DIR__ . '/../../../includes/services/EssentialWorshipScraper.php';
} catch (Exception $e) {
    json_response(['error' => 'Server configuration error: ' . $e->getMessage()], 500);
}

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(['error' => 'Authentication required'], 401);
}

$title = trim($_GET['title'] ?? '');
$artist = trim($_GET['artist'] ?? '');
$ccli = trim($_GET['ccli'] ?? '');

if (empty($title)) {
    json_response(['error' => 'Song title required'], 400);
}

$searchQuery = $title;

$result = [
    'success' => false,
    'source' => null,
    'chord_chart' => '',
    'default_key' => '',
    'tempo' => null,
    'ccli_number' => '',
    'copyright' => '',
    'authors' => '',
    'lyrics' => '',
    'source_url' => '',
    'tried' => [],
];

// 1. Try SongSelect (if cookies are configured and we have a CCLI number)
if (!empty($ccli)) {
    try {
        $pdo = getDbConnection();
        $ssApi = new SongSelectAPI($pdo);

        if ($ssApi->isConfigured()) {
            $result['tried'][] = 'songselect';
            $songData = $ssApi->getFullSongData($ccli);

            if ($songData && !empty($songData['chord_chart'])) {
                $result['success'] = true;
                $result['source'] = 'songselect';
                $result['chord_chart'] = $songData['chord_chart'];
                $result['default_key'] = $songData['default_key'] ?? '';
                $result['tempo'] = $songData['tempo'];
                $result['ccli_number'] = $songData['ccli_number'] ?? $ccli;
                $result['copyright'] = $songData['copyright'] ?? '';
                $result['authors'] = $songData['authors'] ?? '';
                json_response($result);
            }
        }
    } catch (Exception $e) {
        $result['tried'][] = 'songselect_error: ' . $e->getMessage();
    }
}

// 2. Try WorshipTogether
try {
    $wt = new WorshipTogetherScraper();
    $wtResults = $wt->search($searchQuery, 5);
    $result['tried'][] = 'worshiptogether';

    $bestMatch = findBestMatch($wtResults, $title, $artist);

    if ($bestMatch) {
        $songData = $wt->getSongDetails($bestMatch['url']);

        if (!empty($songData['chord_chart'])) {
            $result['success'] = true;
            $result['source'] = 'worshiptogether';
            $result['chord_chart'] = $songData['chord_chart'];
            $result['default_key'] = $songData['default_key'] ?? '';
            $result['tempo'] = $songData['tempo'];
            $result['ccli_number'] = $songData['ccli_number'] ?? '';
            $result['copyright'] = $songData['copyright'] ?? '';
            $result['authors'] = $songData['authors'] ?? '';
            $result['lyrics'] = $songData['lyrics'] ?? '';
            $result['source_url'] = $bestMatch['url'];
            json_response($result);
        }
    }
} catch (Exception $e) {
    // WT failed, continue to fallback
    $result['tried'][] = 'worshiptogether_error: ' . $e->getMessage();
}

// 3. Fallback: Try Essential Worship
try {
    $ew = new EssentialWorshipScraper();
    $ewResults = $ew->search($searchQuery, 5);
    $result['tried'][] = 'essentialworship';

    $bestMatch = findBestMatch($ewResults, $title, $artist);

    if ($bestMatch) {
        $songData = $ew->getSongDetails($bestMatch['url']);

        if (!empty($songData['chord_chart'])) {
            $result['success'] = true;
            $result['source'] = 'essentialworship';
            $result['chord_chart'] = $songData['chord_chart'];
            $result['default_key'] = $songData['default_key'] ?? '';
            $result['tempo'] = $songData['tempo'];
            $result['ccli_number'] = $songData['ccli_number'] ?? '';
            $result['copyright'] = $songData['copyright'] ?? '';
            $result['authors'] = $songData['authors'] ?? '';
            $result['lyrics'] = $songData['lyrics'] ?? '';
            $result['source_url'] = $bestMatch['url'];
            json_response($result);
        }
    }
} catch (Exception $e) {
    $result['tried'][] = 'essentialworship_error: ' . $e->getMessage();
}

// Neither source had chords
json_response($result);

/**
 * Find the best matching song from search results
 */
function findBestMatch(array $results, string $title, string $artist): ?array
{
    if (empty($results)) return null;

    $normalizedTitle = normalizeForMatch($title);
    $normalizedArtist = normalizeForMatch($artist);

    $bestScore = 0;
    $bestMatch = null;

    foreach ($results as $result) {
        $score = 0;
        $resultTitle = normalizeForMatch($result['title'] ?? '');
        $resultArtist = normalizeForMatch($result['artist'] ?? '');

        // Exact title match
        if ($resultTitle === $normalizedTitle) {
            $score += 100;
        }
        // Title contains search or vice versa
        elseif (str_contains($resultTitle, $normalizedTitle) || str_contains($normalizedTitle, $resultTitle)) {
            $score += 70;
        }
        // Similar title (Levenshtein)
        else {
            $distance = levenshtein($resultTitle, $normalizedTitle);
            $maxLen = max(strlen($resultTitle), strlen($normalizedTitle));
            if ($maxLen > 0) {
                $similarity = 1 - ($distance / $maxLen);
                if ($similarity > 0.7) {
                    $score += (int)($similarity * 60);
                }
            }
        }

        // Artist match bonus
        if (!empty($normalizedArtist) && !empty($resultArtist)) {
            if ($resultArtist === $normalizedArtist) {
                $score += 30;
            } elseif (str_contains($resultArtist, $normalizedArtist) || str_contains($normalizedArtist, $resultArtist)) {
                $score += 15;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $result;
        }
    }

    // Require minimum score to avoid false matches
    return $bestScore >= 50 ? $bestMatch : null;
}

/**
 * Normalize a string for fuzzy matching
 */
function normalizeForMatch(string $str): string
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s]/', '', $str);
    $str = preg_replace('/\s+/', ' ', $str);
    return $str;
}
