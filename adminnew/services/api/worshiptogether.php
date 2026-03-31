<?php
/**
 * WorshipTogether API
 * Handles fetching and importing songs from WorshipTogether.com
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
    require_once __DIR__ . '/../../../includes/services/WorshipTogetherScraper.php';
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

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    json_response(['error' => 'Database connection failed'], 500);
}

// Get action from request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $scraper = new WorshipTogetherScraper();

    switch ($action) {
        case 'status':
            json_response($scraper->getStatus());
            break;

        case 'fetch':
            // Fetch song details from a WorshipTogether URL
            $url = $_GET['url'] ?? '';
            if (empty($url)) {
                json_response(['error' => 'Song URL required'], 400);
            }

            // Validate URL is from worshiptogether.com
            if (!preg_match('/^https?:\/\/(www\.)?worshiptogether\.com\/songs\//', $url)) {
                json_response(['error' => 'Invalid WorshipTogether URL. Please provide a URL like: https://www.worshiptogether.com/songs/song-name/'], 400);
            }

            $songData = $scraper->getSongDetails($url);
            json_response($songData);
            break;

        case 'import':
            if ($method !== 'POST') {
                json_response(['error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $url = $input['url'] ?? '';

            if (empty($url)) {
                json_response(['error' => 'Song URL required'], 400);
            }

            // Validate URL
            if (!preg_match('/^https?:\/\/(www\.)?worshiptogether\.com\/songs\//', $url)) {
                json_response(['error' => 'Invalid WorshipTogether URL'], 400);
            }

            // Fetch song data
            $songData = $scraper->getSongDetails($url);

            if (empty($songData['title'])) {
                json_response(['error' => 'Could not extract song data from page'], 400);
            }

            // Check if song already exists by CCLI number or title+artist
            $existingId = null;

            if (!empty($songData['ccli_number'])) {
                $stmt = $pdo->prepare("SELECT id FROM songs WHERE ccli_number = ?");
                $stmt->execute([$songData['ccli_number']]);
                $existingId = $stmt->fetchColumn();
            }

            if (!$existingId && !empty($songData['title'])) {
                // Try matching by title and artist
                $stmt = $pdo->prepare("SELECT id FROM songs WHERE LOWER(title) = LOWER(?) AND LOWER(artist) = LOWER(?)");
                $stmt->execute([$songData['title'], $songData['artist'] ?? '']);
                $existingId = $stmt->fetchColumn();
            }

            if ($existingId) {
                // Update existing song
                $stmt = $pdo->prepare("
                    UPDATE songs SET
                        title = COALESCE(NULLIF(?, ''), title),
                        artist = COALESCE(NULLIF(?, ''), artist),
                        authors = COALESCE(NULLIF(?, ''), authors),
                        copyright = COALESCE(NULLIF(?, ''), copyright),
                        default_key = COALESCE(NULLIF(?, ''), default_key),
                        tempo = COALESCE(?, tempo),
                        ccli_number = COALESCE(NULLIF(?, ''), ccli_number),
                        chord_chart_original = COALESCE(NULLIF(?, ''), chord_chart_original),
                        chord_chart_key = COALESCE(NULLIF(?, ''), chord_chart_key),
                        worshiptogether_url = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $songData['title'],
                    $songData['artist'],
                    $songData['authors'] ?? '',
                    $songData['copyright'] ?? '',
                    $songData['default_key'] ?? '',
                    $songData['tempo'],
                    $songData['ccli_number'] ?? '',
                    $songData['chord_chart'] ?? '',
                    $songData['default_key'] ?? '',
                    $url,
                    $existingId
                ]);

                // Also save to song_chord_charts table if chord chart exists
                if (!empty($songData['chord_chart'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                        VALUES (?, ?, 'chords', ?, 'worshiptogether', 1)
                        ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()
                    ");
                    $stmt->execute([$existingId, $songData['default_key'] ?? 'C', $songData['chord_chart']]);
                }

                json_response([
                    'success' => true,
                    'message' => 'Song updated',
                    'song_id' => $existingId,
                    'action' => 'updated',
                    'song' => $songData
                ]);
            } else {
                // Insert new song
                $stmt = $pdo->prepare("
                    INSERT INTO songs (
                        title, artist, authors, ccli_number, copyright,
                        default_key, tempo,
                        chord_chart_original, chord_chart_key, worshiptogether_url,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $songData['title'],
                    $songData['artist'] ?? '',
                    $songData['authors'] ?? '',
                    $songData['ccli_number'] ?? '',
                    $songData['copyright'] ?? '',
                    $songData['default_key'] ?? '',
                    $songData['tempo'],
                    $songData['chord_chart'] ?? '',
                    $songData['default_key'] ?? '',
                    $url
                ]);

                $newId = $pdo->lastInsertId();

                // Also save to song_chord_charts table if chord chart exists
                if (!empty($songData['chord_chart'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO song_chord_charts (song_id, key_signature, chart_type, content, source, is_primary)
                        VALUES (?, ?, 'chords', ?, 'worshiptogether', 1)
                    ");
                    $stmt->execute([$newId, $songData['default_key'] ?? 'C', $songData['chord_chart']]);
                }

                json_response([
                    'success' => true,
                    'message' => 'Song imported',
                    'song_id' => $newId,
                    'action' => 'created',
                    'song' => $songData
                ]);
            }
            break;

        default:
            json_response(['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 500);
}
