<?php
/**
 * Setlist AI API
 * Generate AI-powered setlist suggestions and learn from user choices
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';
require_once __DIR__ . '/../../../includes/services/SetlistAI.php';

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
$action = $_GET['action'] ?? '';

$setlistAI = new SetlistAI($pdo, $userId);

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'generate':
                // Generate a new setlist suggestion
                $length = min(15, max(1, (int)($_GET['length'] ?? 5)));
                $flowId = (int)($_GET['flow_id'] ?? $_GET['curve'] ?? 0);
                $serviceId = (int)($_GET['service_id'] ?? 0);
                $exclude = isset($_GET['exclude']) ? explode(',', $_GET['exclude']) : [];
                $startWithIntro = !empty($_GET['start_with_intro']);

                // Get the flow details
                $curve = 'standard';
                $flowPattern = null;

                if ($flowId > 0) {
                    $stmt = $pdo->prepare("SELECT pattern FROM custom_worship_flows WHERE id = ?");
                    $stmt->execute([$flowId]);
                    $flow = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($flow) {
                        $flowPattern = json_decode($flow['pattern'], true);
                    }
                }

                $options = [
                    'exclude' => array_map('intval', $exclude),
                    'flow_pattern' => $flowPattern,
                    'start_with_intro' => $startWithIntro,
                ];

                $result = $setlistAI->generateSetlist($length, $curve, $options);

                // Add most used key to each song and group by similar keys
                if (!empty($result['songs'])) {
                    foreach ($result['songs'] as &$song) {
                        $mostUsedKey = $setlistAI->getMostUsedKeyForSong($song['id']);
                        $song['most_used_key'] = $mostUsedKey;
                        $song['suggested_key'] = $mostUsedKey ?: $song['default_key'];
                        $song['key_reason'] = $mostUsedKey ? 'Most commonly used' : 'Default key';
                    }
                }

                // Save suggestion for feedback tracking
                if ($serviceId && !empty($result['songs'])) {
                    $suggestionId = $setlistAI->saveSuggestion(
                        $serviceId,
                        array_column($result['songs'], 'id')
                    );
                    $result['suggestion_id'] = $suggestionId;
                }

                echo json_encode([
                    'success' => true,
                    'data' => $result,
                ]);
                break;

            case 'suggest_next':
                // Suggest songs for a specific position
                $position = (int)($_GET['position'] ?? 0);
                $totalLength = (int)($_GET['total_length'] ?? 5);
                $previousSongId = isset($_GET['previous_song_id']) && $_GET['previous_song_id'] !== '' ? (int)$_GET['previous_song_id'] : null;
                $currentKey = $_GET['current_key'] ?? null;
                $exclude = isset($_GET['exclude']) && $_GET['exclude'] !== '' ? explode(',', $_GET['exclude']) : [];

                $suggestions = $setlistAI->getSuggestionsForPosition(
                    $position,
                    $totalLength,
                    $previousSongId,
                    $currentKey,
                    array_map('intval', $exclude)
                );

                // Add most used key to each suggestion
                foreach ($suggestions as &$suggestion) {
                    $mostUsedKey = $setlistAI->getMostUsedKeyForSong($suggestion['song']['id']);
                    $suggestion['song']['most_used_key'] = $mostUsedKey;
                    $suggestion['song']['suggested_key'] = $mostUsedKey ?: $suggestion['song']['default_key'];
                }

                echo json_encode([
                    'success' => true,
                    'suggestions' => $suggestions,
                ]);
                break;

            case 'curves':
            case 'flows':
                // Get available worship flows from database
                $stmt = $pdo->prepare("
                    SELECT id, name, description, pattern, is_default, start_with_intro
                    FROM custom_worship_flows
                    WHERE user_id IS NULL OR user_id = ?
                    ORDER BY is_default DESC, sort_order ASC, name ASC
                ");
                $stmt->execute([$userId]);
                $flows = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $flows[] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'pattern' => json_decode($row['pattern'], true),
                        'is_default' => (bool)$row['is_default'],
                        'start_with_intro' => (bool)$row['start_with_intro'],
                        'can_edit' => !$row['is_default'], // Can't edit defaults
                    ];
                }

                // Also get count of intro songs
                $introCount = $pdo->query("SELECT COUNT(*) FROM songs WHERE is_intro_song = 1")->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'flows' => $flows,
                    'intro_song_count' => (int)$introCount,
                ]);
                break;

            case 'stats':
                // Get learning statistics
                $stmt = $pdo->query("SELECT COUNT(*) FROM song_transition_patterns WHERE user_id = $userId OR user_id IS NULL");
                $transitionCount = (int)$stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM song_position_patterns WHERE user_id = $userId OR user_id IS NULL");
                $positionCount = (int)$stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM ai_setlist_suggestions WHERE user_id = $userId");
                $suggestionsGenerated = (int)$stmt->fetchColumn();

                $stmt = $pdo->query("SELECT AVG(acceptance_rate) FROM ai_setlist_suggestions WHERE user_id = $userId AND acceptance_rate IS NOT NULL");
                $avgAcceptance = (float)$stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(DISTINCT service_id) FROM service_items WHERE item_type = 'song' AND song_id IS NOT NULL");
                $servicesAnalyzed = (int)$stmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'transition_patterns' => $transitionCount,
                        'position_patterns' => $positionCount,
                        'suggestions_generated' => $suggestionsGenerated,
                        'average_acceptance_rate' => round($avgAcceptance * 100, 1),
                        'services_analyzed' => $servicesAnalyzed,
                    ],
                ]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? $action;

        switch ($action) {
            case 'learn':
                // Trigger learning from all historical setlists
                $stats = $setlistAI->learnFromHistory();
                echo json_encode([
                    'success' => true,
                    'message' => 'Learning complete',
                    'stats' => $stats,
                ]);
                break;

            case 'learn_service':
                // Learn from a specific service
                $serviceId = (int)($input['service_id'] ?? 0);
                if (!$serviceId) {
                    echo json_encode(['success' => false, 'error' => 'service_id required']);
                    exit;
                }
                $setlistAI->learnFromService($serviceId);
                echo json_encode(['success' => true, 'message' => 'Learned from service']);
                break;

            case 'feedback':
                // Record feedback on a suggestion
                $suggestionId = (int)($input['suggestion_id'] ?? 0);
                $finalSongs = $input['final_songs'] ?? [];
                $notes = $input['notes'] ?? null;

                if (!$suggestionId) {
                    echo json_encode(['success' => false, 'error' => 'suggestion_id required']);
                    exit;
                }

                $setlistAI->recordFeedback($suggestionId, $finalSongs, $notes);
                echo json_encode(['success' => true, 'message' => 'Feedback recorded']);
                break;

            case 'apply':
                // Apply AI suggestion to a service
                $serviceId = (int)($input['service_id'] ?? 0);
                $songs = $input['songs'] ?? [];
                $suggestionId = (int)($input['suggestion_id'] ?? 0);

                if (!$serviceId || empty($songs)) {
                    echo json_encode(['success' => false, 'error' => 'service_id and songs required']);
                    exit;
                }

                // Get max sort order
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM service_items WHERE service_id = ?");
                $stmt->execute([$serviceId]);
                $maxOrder = (int)$stmt->fetchColumn();

                $addedItems = [];
                foreach ($songs as $index => $song) {
                    $songId = (int)($song['id'] ?? $song);
                    $songKey = $song['key'] ?? null;
                    $isIntro = !empty($song['is_intro']) ? 1 : 0;

                    // Get song details
                    $stmt = $pdo->prepare("SELECT title, default_key, tempo FROM songs WHERE id = ?");
                    $stmt->execute([$songId]);
                    $songData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$songData) {
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO service_items (service_id, item_type, song_id, title, song_key, song_tempo, sort_order, is_intro, created_at)
                        VALUES (?, 'song', ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $serviceId,
                        $songId,
                        $songData['title'],
                        $songKey ?? $songData['default_key'],
                        $songData['tempo'],
                        $maxOrder + $index + 1,
                        $isIntro,
                    ]);

                    $addedItems[] = [
                        'id' => (int)$pdo->lastInsertId(),
                        'song_id' => $songId,
                        'title' => $songData['title'],
                        'is_intro' => $isIntro,
                    ];
                }

                // Record as feedback if we have a suggestion ID
                if ($suggestionId) {
                    $setlistAI->recordFeedback($suggestionId, array_column($songs, 'id'));
                }

                // Learn from this new setlist immediately
                $setlistAI->learnFromService($serviceId);

                echo json_encode([
                    'success' => true,
                    'message' => 'Songs added to service',
                    'items' => $addedItems,
                ]);
                break;

            case 'save_flow':
                // Create or update a custom worship flow
                $flowId = isset($input['id']) ? (int)$input['id'] : null;
                $name = trim($input['name'] ?? '');
                $description = trim($input['description'] ?? '');
                $pattern = $input['pattern'] ?? [];

                if (empty($name)) {
                    echo json_encode(['success' => false, 'error' => 'Name is required']);
                    exit;
                }

                if (empty($pattern) || !is_array($pattern)) {
                    echo json_encode(['success' => false, 'error' => 'Pattern is required']);
                    exit;
                }

                // Validate pattern values
                $validEnergies = ['very-high', 'high', 'medium', 'low', 'very-low'];
                foreach ($pattern as $energy) {
                    if (!in_array($energy, $validEnergies)) {
                        echo json_encode(['success' => false, 'error' => 'Invalid energy level: ' . $energy]);
                        exit;
                    }
                }

                if ($flowId) {
                    // Update existing (only if not a default and user owns it)
                    $stmt = $pdo->prepare("
                        UPDATE custom_worship_flows
                        SET name = ?, description = ?, pattern = ?, updated_at = NOW()
                        WHERE id = ? AND is_default = 0 AND (user_id = ? OR user_id IS NULL)
                    ");
                    $stmt->execute([$name, $description, json_encode($pattern), $flowId, $userId]);

                    if ($stmt->rowCount() === 0) {
                        echo json_encode(['success' => false, 'error' => 'Flow not found or cannot be edited']);
                        exit;
                    }
                } else {
                    // Create new
                    $stmt = $pdo->prepare("
                        INSERT INTO custom_worship_flows (name, description, pattern, user_id, sort_order)
                        VALUES (?, ?, ?, NULL, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM custom_worship_flows f2))
                    ");
                    $stmt->execute([$name, $description, json_encode($pattern)]);
                    $flowId = (int)$pdo->lastInsertId();
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Flow saved',
                    'flow_id' => $flowId,
                ]);
                break;

            case 'delete_flow':
                // Delete a custom worship flow (not defaults)
                $flowId = (int)($input['id'] ?? 0);

                if (!$flowId) {
                    echo json_encode(['success' => false, 'error' => 'Flow ID required']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    DELETE FROM custom_worship_flows
                    WHERE id = ? AND is_default = 0 AND (user_id = ? OR user_id IS NULL)
                ");
                $stmt->execute([$flowId, $userId]);

                if ($stmt->rowCount() === 0) {
                    echo json_encode(['success' => false, 'error' => 'Flow not found or cannot be deleted']);
                    exit;
                }

                echo json_encode(['success' => true, 'message' => 'Flow deleted']);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
