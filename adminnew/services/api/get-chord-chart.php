<?php
/**
 * Get Song Data API
 * Fetches chord chart and/or full song data by ID
 */

require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$songId = (int)($_GET['song_id'] ?? 0);
$includeFull = isset($_GET['full']); // Include lyrics, copyright, notes

if (!$songId) {
    echo json_encode(['success' => false, 'error' => 'Song ID required']);
    exit;
}

try {
    $pdo = getDbConnection();

    $response = ['success' => true];

    // Get the primary chord chart for this song
    $stmt = $pdo->prepare("
        SELECT content, key_signature
        FROM song_chord_charts
        WHERE song_id = ?
        ORDER BY is_primary DESC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$songId]);
    $chart = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($chart) {
        $response['chord_chart'] = $chart['content'];
        $response['key_signature'] = $chart['key_signature'];
    }

    // If full data requested, get lyrics, copyright, notes, key_notes
    if ($includeFull) {
        $stmt = $pdo->prepare("SELECT lyrics, copyright, notes, key_notes FROM songs WHERE id = ?");
        $stmt->execute([$songId]);
        $songData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($songData) {
            $response['lyrics'] = $songData['lyrics'];
            $response['copyright'] = $songData['copyright'];
            $response['notes'] = $songData['notes'];
            $response['key_notes'] = $songData['key_notes'];
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
