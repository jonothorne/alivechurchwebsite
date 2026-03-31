<?php
/**
 * Music Stand Songs API
 * Search songs and add to service plans
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/db-config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$auth = new Auth($pdo);

if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $auth->id();
$user = $auth->user();
$isAdmin = in_array($user['role'], ['admin', 'editor']);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Search songs
        $search = $_GET['search'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 50), 100);

        $sql = "
            SELECT
                s.id,
                s.title,
                s.artist,
                s.default_key,
                (SELECT COUNT(*) FROM song_chord_charts scc WHERE scc.song_id = s.id) as has_chart
            FROM songs s
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $sql .= " AND (s.title LIKE ? OR s.artist LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY s.title ASC LIMIT ?";
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'songs' => $songs]);
        break;

    case 'POST':
        // Add song to service plan
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $serviceId = (int)($input['service_id'] ?? 0);
        $songId = (int)($input['song_id'] ?? 0);
        $position = isset($input['position']) ? (int)$input['position'] : null;

        if (!$serviceId || !$songId) {
            echo json_encode(['success' => false, 'error' => 'service_id and song_id required']);
            exit;
        }

        // Get song details
        $stmt = $pdo->prepare("SELECT * FROM songs WHERE id = ?");
        $stmt->execute([$songId]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$song) {
            echo json_encode(['success' => false, 'error' => 'Song not found']);
            exit;
        }

        if ($isAdmin) {
            // Admin: Actually add to service_items for everyone

            // Get max sort order
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM service_items WHERE service_id = ?");
            $stmt->execute([$serviceId]);
            $nextOrder = $stmt->fetchColumn();

            if ($position !== null) {
                // Shift existing items
                $stmt = $pdo->prepare("UPDATE service_items SET sort_order = sort_order + 1 WHERE service_id = ? AND sort_order >= ?");
                $stmt->execute([$serviceId, $position]);
                $nextOrder = $position;
            }

            // Insert the song
            $stmt = $pdo->prepare("
                INSERT INTO service_items (service_id, item_type, song_id, title, song_key, sort_order, created_at)
                VALUES (?, 'song', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$serviceId, $songId, $song['title'], $song['default_key'], $nextOrder]);
            $itemId = $pdo->lastInsertId();

            // Get the chord chart info
            $stmt = $pdo->prepare("
                SELECT scc.key_signature as chart_key
                FROM song_chord_charts scc
                WHERE scc.song_id = ?
                ORDER BY scc.is_primary DESC, scc.id ASC
                LIMIT 1
            ");
            $stmt->execute([$songId]);
            $chartInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'isPersonal' => false,
                'item' => [
                    'itemId' => (int)$itemId,
                    'songId' => $songId,
                    'title' => $song['title'],
                    'artist' => $song['artist'],
                    'originalKey' => $chartInfo['chart_key'] ?? $song['default_key'] ?? 'C',
                    'currentKey' => $song['default_key'] ?? 'C',
                    'sortOrder' => $nextOrder
                ]
            ]);
        } else {
            // Regular user: Store personal song addition
            // We'll use localStorage on the client side for simplicity
            // But we can also store in a table if needed

            // Get the chord chart info
            $stmt = $pdo->prepare("
                SELECT scc.key_signature as chart_key
                FROM song_chord_charts scc
                WHERE scc.song_id = ?
                ORDER BY scc.is_primary DESC, scc.id ASC
                LIMIT 1
            ");
            $stmt->execute([$songId]);
            $chartInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'isPersonal' => true,
                'item' => [
                    'songId' => $songId,
                    'title' => $song['title'],
                    'artist' => $song['artist'],
                    'originalKey' => $chartInfo['chart_key'] ?? $song['default_key'] ?? 'C',
                    'currentKey' => $song['default_key'] ?? 'C'
                ]
            ]);
        }
        break;

    case 'DELETE':
        // Remove song from service plan (admin only for real items)
        $itemId = (int)($_GET['item_id'] ?? 0);
        $serviceId = (int)($_GET['service_id'] ?? 0);

        if (!$isAdmin) {
            echo json_encode(['success' => false, 'error' => 'Admin required']);
            exit;
        }

        if ($itemId) {
            $stmt = $pdo->prepare("DELETE FROM service_items WHERE id = ? AND service_id = ?");
            $stmt->execute([$itemId, $serviceId]);
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
