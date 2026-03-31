<?php
/**
 * Music Stand Annotations API
 * Save and load personal annotations, drawings, and settings
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
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        // Load annotation
        $itemId = (int)($_GET['item_id'] ?? 0);
        $songId = (int)($_GET['song_id'] ?? 0);
        $loadDefault = isset($_GET['default']) && $_GET['default'] === '1';

        if ($itemId) {
            $stmt = $pdo->prepare("
                SELECT * FROM musicstand_annotations
                WHERE user_id = ? AND service_item_id = ?
            ");
            $stmt->execute([$userId, $itemId]);
        } elseif ($songId) {
            // Load song-level default (no service_item_id)
            $stmt = $pdo->prepare("
                SELECT * FROM musicstand_annotations
                WHERE user_id = ? AND song_id = ? AND service_item_id IS NULL
            ");
            $stmt->execute([$userId, $songId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'item_id or song_id required']);
            exit;
        }

        $annotation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($annotation) {
            // Parse JSON fields
            if ($annotation['drawing_data']) {
                $annotation['drawing_data'] = json_decode($annotation['drawing_data'], true);
            }
            echo json_encode(['success' => true, 'annotation' => $annotation]);
        } else {
            echo json_encode(['success' => true, 'annotation' => null]);
        }
        break;

    case 'POST':
        // Save annotation
        $itemId = isset($input['item_id']) ? (int)$input['item_id'] : null;
        $songId = isset($input['song_id']) ? (int)$input['song_id'] : null;
        $isDefault = !empty($input['is_default']);

        // If saving as default, force song_id only (no item_id)
        if ($isDefault) {
            $itemId = null;
        }

        if (!$itemId && !$songId) {
            echo json_encode(['success' => false, 'error' => 'item_id or song_id required']);
            exit;
        }

        $drawingData = isset($input['drawing_data']) ? json_encode($input['drawing_data']) : null;
        $textNotes = $input['text_notes'] ?? null;
        $chartEdits = $input['chart_edits'] ?? null;
        $chordSize = (int)($input['chord_size'] ?? 14);
        $lyricSize = (int)($input['lyric_size'] ?? 16);
        $transposeKey = $input['transpose_key'] ?? null;

        if ($itemId) {
            // Upsert for service item
            $stmt = $pdo->prepare("
                INSERT INTO musicstand_annotations
                    (user_id, service_item_id, drawing_data, text_notes, chart_edits, chord_size, lyric_size, transpose_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    drawing_data = VALUES(drawing_data),
                    text_notes = VALUES(text_notes),
                    chart_edits = VALUES(chart_edits),
                    chord_size = VALUES(chord_size),
                    lyric_size = VALUES(lyric_size),
                    transpose_key = VALUES(transpose_key),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $itemId, $drawingData, $textNotes, $chartEdits, $chordSize, $lyricSize, $transposeKey]);
        } else {
            // Upsert for song (library view)
            $stmt = $pdo->prepare("
                INSERT INTO musicstand_annotations
                    (user_id, song_id, drawing_data, text_notes, chart_edits, chord_size, lyric_size, transpose_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    drawing_data = VALUES(drawing_data),
                    text_notes = VALUES(text_notes),
                    chart_edits = VALUES(chart_edits),
                    chord_size = VALUES(chord_size),
                    lyric_size = VALUES(lyric_size),
                    transpose_key = VALUES(transpose_key),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $songId, $drawingData, $textNotes, $chartEdits, $chordSize, $lyricSize, $transposeKey]);
        }

        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        // Clear annotation
        $itemId = (int)($_GET['item_id'] ?? 0);
        $songId = (int)($_GET['song_id'] ?? 0);

        if ($itemId) {
            $stmt = $pdo->prepare("DELETE FROM musicstand_annotations WHERE user_id = ? AND service_item_id = ?");
            $stmt->execute([$userId, $itemId]);
        } elseif ($songId) {
            $stmt = $pdo->prepare("DELETE FROM musicstand_annotations WHERE user_id = ? AND song_id = ? AND service_item_id IS NULL");
            $stmt->execute([$userId, $songId]);
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
