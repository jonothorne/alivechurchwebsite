<?php
/**
 * SongSelect API
 * Handles searching and importing songs from SongSelect
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
    require_once __DIR__ . '/../../includes/Auth.php';
    require_once __DIR__ . '/../../includes/db-config.php';
    require_once __DIR__ . '/../../includes/services/SongSelectScraper.php';
    require_once __DIR__ . '/../../includes/services/CredentialEncryption.php';
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

/**
 * Get SongSelect credentials from database or environment
 */
function getSongSelectCredentials($pdo): array {
    // Try database first (songselect_config table)
    try {
        $stmt = $pdo->query("SELECT * FROM songselect_config WHERE is_active = 1 LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config && !empty($config['client_id'])) {
            // Credentials are encrypted in database
            return [
                'username' => CredentialEncryption::decrypt($config['client_id']),
                'password' => CredentialEncryption::decrypt($config['client_secret']),
            ];
        }
    } catch (PDOException $e) {
        // Table might not exist yet, fall through
    }

    // Try site_settings table
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");

        $stmt->execute(['songselect_username']);
        $username = $stmt->fetchColumn();

        $stmt->execute(['songselect_password']);
        $password = $stmt->fetchColumn();

        if ($username && $password) {
            return [
                'username' => CredentialEncryption::decrypt($username),
                'password' => CredentialEncryption::decrypt($password),
            ];
        }
    } catch (PDOException $e) {
        // Settings might not exist, fall through
    }

    // Fallback to environment variables
    $username = getenv('SONGSELECT_USERNAME') ?: '';
    $password = getenv('SONGSELECT_PASSWORD') ?: '';

    return [
        'username' => $username,
        'password' => $password,
    ];
}

// Get action from request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Verify CSRF for POST requests
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        json_response(['error' => 'Invalid CSRF token'], 403);
    }
}

try {
    switch ($action) {
        case 'status':
            // Check if scraper is configured
            $credentials = getSongSelectCredentials($pdo);
            $scraper = new SongSelectScraper($credentials['username'] ?: 'test', $credentials['password'] ?: 'test');
            $status = $scraper->getStatus();
            $status['has_credentials'] = !empty($credentials['username']) && !empty($credentials['password']);
            json_response($status);
            break;

        case 'search':
            $query = $_GET['q'] ?? '';
            if (empty($query)) {
                json_response(['error' => 'Search query required'], 400);
            }

            $credentials = getSongSelectCredentials($pdo);
            if (empty($credentials['username']) || empty($credentials['password'])) {
                json_response(['error' => 'SongSelect credentials not configured'], 400);
            }

            $scraper = new SongSelectScraper($credentials['username'], $credentials['password']);

            if (!$scraper->isConfigured()) {
                json_response(['error' => 'SongSelect scraper not properly configured', 'status' => $scraper->getStatus()], 500);
            }

            $results = $scraper->search($query, 20);
            json_response(['results' => $results]);
            break;

        case 'get-song':
            $songId = $_GET['id'] ?? '';
            if (empty($songId)) {
                json_response(['error' => 'Song ID required'], 400);
            }

            $credentials = getSongSelectCredentials($pdo);
            if (empty($credentials['username']) || empty($credentials['password'])) {
                json_response(['error' => 'SongSelect credentials not configured'], 400);
            }

            $scraper = new SongSelectScraper($credentials['username'], $credentials['password']);
            $songData = $scraper->getSongDetails($songId);
            json_response($songData);
            break;

        case 'import':
            if ($method !== 'POST') {
                json_response(['error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $songId = $input['song_id'] ?? '';

            if (empty($songId)) {
                json_response(['error' => 'Song ID required'], 400);
            }

            $credentials = getSongSelectCredentials($pdo);
            if (empty($credentials['username']) || empty($credentials['password'])) {
                json_response(['error' => 'SongSelect credentials not configured'], 400);
            }

            $scraper = new SongSelectScraper($credentials['username'], $credentials['password']);
            $songData = $scraper->getSongDetails($songId);

            // Check if song already exists
            $stmt = $pdo->prepare("SELECT id FROM songs WHERE ccli_number = ?");
            $stmt->execute([$songData['ccli_number']]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                // Update existing song
                $stmt = $pdo->prepare("
                    UPDATE songs SET
                        title = ?,
                        artist = ?,
                        authors = ?,
                        copyright = ?,
                        default_key = ?,
                        tempo = ?,
                        time_signature = ?,
                        chord_chart_original = ?,
                        chord_chart_key = ?,
                        songselect_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $songData['title'],
                    $songData['artist'],
                    $songData['authors'],
                    $songData['copyright'],
                    $songData['default_key'],
                    $songData['tempo'],
                    $songData['time_signature'],
                    $songData['chord_chart'],
                    $songData['default_key'],
                    $songData['songselect_id'],
                    $existingId
                ]);

                json_response([
                    'success' => true,
                    'message' => 'Song updated',
                    'song_id' => $existingId,
                    'action' => 'updated'
                ]);
            } else {
                // Insert new song
                $stmt = $pdo->prepare("
                    INSERT INTO songs (
                        title, artist, authors, ccli_number, copyright,
                        default_key, default_tempo, tempo, time_signature,
                        chord_chart_original, chord_chart_key, songselect_id,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $songData['title'],
                    $songData['artist'],
                    $songData['authors'],
                    $songData['ccli_number'],
                    $songData['copyright'],
                    $songData['default_key'],
                    $songData['tempo'],
                    $songData['tempo'],
                    $songData['time_signature'],
                    $songData['chord_chart'],
                    $songData['default_key'],
                    $songData['songselect_id']
                ]);

                $newId = $pdo->lastInsertId();

                json_response([
                    'success' => true,
                    'message' => 'Song imported',
                    'song_id' => $newId,
                    'action' => 'created'
                ]);
            }
            break;

        case 'save-credentials':
            if ($method !== 'POST') {
                json_response(['error' => 'POST method required'], 405);
            }

            // Only admins can save credentials
            if (($_SESSION['admin_role'] ?? '') !== 'admin') {
                json_response(['error' => 'Admin access required'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';

            if (empty($username) || empty($password)) {
                json_response(['error' => 'Username and password required'], 400);
            }

            // Encrypt and save to site_settings
            $encryptedUsername = CredentialEncryption::encrypt($username);
            $encryptedPassword = CredentialEncryption::encrypt($password);

            // Upsert songselect_username
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value, setting_type, setting_group, display_name, description)
                VALUES ('songselect_username', ?, 'text', 'integrations', 'SongSelect Username', 'CCLI SongSelect login email')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$encryptedUsername, $encryptedUsername]);

            // Upsert songselect_password
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value, setting_type, setting_group, display_name, description)
                VALUES ('songselect_password', ?, 'text', 'integrations', 'SongSelect Password', 'CCLI SongSelect login password (encrypted)')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$encryptedPassword, $encryptedPassword]);

            json_response(['success' => true, 'message' => 'Credentials saved']);
            break;

        default:
            json_response(['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 500);
}
