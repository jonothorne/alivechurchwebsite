<?php
/**
 * Songs API
 * Handles CRUD operations for songs library
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
} catch (Exception $e) {
    json_response(['error' => 'Server configuration error'], 500);
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

// Verify CSRF for POST/PUT/DELETE requests
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        json_response(['error' => 'Invalid CSRF token'], 403);
    }
}

try {
    switch ($action) {
        case 'get-chords':
            $songId = (int)($_GET['id'] ?? 0);
            if (!$songId) {
                json_response(['error' => 'Song ID required'], 400);
            }

            $stmt = $pdo->prepare("SELECT id, title, chord_chart_original, chord_chart_key FROM songs WHERE id = ?");
            $stmt->execute([$songId]);
            $song = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$song) {
                json_response(['error' => 'Song not found'], 404);
            }

            json_response([
                'id' => $song['id'],
                'title' => $song['title'],
                'key' => $song['chord_chart_key'],
                'chord_chart' => $song['chord_chart_original']
            ]);
            break;

        case 'list':
            $search = $_GET['q'] ?? '';
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $where = [];
            $params = [];

            if ($search) {
                $where[] = "(title LIKE ? OR artist LIKE ? OR ccli_number LIKE ?)";
                $searchParam = "%$search%";
                $params = [$searchParam, $searchParam, $searchParam];
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT id, title, artist, default_key, tempo, ccli_number, times_used
                    FROM songs $whereClause
                    ORDER BY title ASC
                    LIMIT $limit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) FROM songs $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            json_response([
                'songs' => $songs,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;

        case 'get':
            $songId = (int)($_GET['id'] ?? 0);
            if (!$songId) {
                json_response(['error' => 'Song ID required'], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM songs WHERE id = ?");
            $stmt->execute([$songId]);
            $song = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$song) {
                json_response(['error' => 'Song not found'], 404);
            }

            json_response($song);
            break;

        case 'save':
            if ($method !== 'POST') {
                json_response(['error' => 'POST method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            $id = (int)($input['id'] ?? 0);
            $title = trim($input['title'] ?? '');

            if (empty($title)) {
                json_response(['error' => 'Title is required'], 400);
            }

            $data = [
                'title' => $title,
                'artist' => trim($input['artist'] ?? ''),
                'authors' => trim($input['authors'] ?? ''),
                'ccli_number' => trim($input['ccli_number'] ?? ''),
                'default_key' => trim($input['default_key'] ?? ''),
                'default_tempo' => (int)($input['default_tempo'] ?? 0) ?: null,
                'tempo' => (int)($input['tempo'] ?? 0) ?: null,
                'time_signature' => trim($input['time_signature'] ?? ''),
                'lyrics' => trim($input['lyrics'] ?? ''),
                'notes' => trim($input['notes'] ?? ''),
                'tags' => trim($input['tags'] ?? ''),
                'copyright' => trim($input['copyright'] ?? ''),
            ];

            if ($id) {
                // Update
                $sql = "UPDATE songs SET
                    title = ?, artist = ?, authors = ?, ccli_number = ?,
                    default_key = ?, default_tempo = ?, tempo = ?, time_signature = ?,
                    lyrics = ?, notes = ?, tags = ?, copyright = ?,
                    updated_at = NOW()
                    WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['title'], $data['artist'], $data['authors'], $data['ccli_number'],
                    $data['default_key'], $data['default_tempo'], $data['tempo'], $data['time_signature'],
                    $data['lyrics'], $data['notes'], $data['tags'], $data['copyright'],
                    $id
                ]);

                json_response(['success' => true, 'id' => $id, 'action' => 'updated']);
            } else {
                // Insert
                $sql = "INSERT INTO songs (
                    title, artist, authors, ccli_number,
                    default_key, default_tempo, tempo, time_signature,
                    lyrics, notes, tags, copyright,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['title'], $data['artist'], $data['authors'], $data['ccli_number'],
                    $data['default_key'], $data['default_tempo'], $data['tempo'], $data['time_signature'],
                    $data['lyrics'], $data['notes'], $data['tags'], $data['copyright']
                ]);

                $newId = $pdo->lastInsertId();
                json_response(['success' => true, 'id' => $newId, 'action' => 'created']);
            }
            break;

        case 'delete':
            if ($method !== 'POST' && $method !== 'DELETE') {
                json_response(['error' => 'POST or DELETE method required'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $songId = (int)($input['id'] ?? $_GET['id'] ?? 0);

            if (!$songId) {
                json_response(['error' => 'Song ID required'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM songs WHERE id = ?");
            $stmt->execute([$songId]);

            json_response(['success' => true, 'deleted' => $songId]);
            break;

        default:
            json_response(['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 500);
}
