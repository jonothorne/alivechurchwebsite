<?php
/**
 * Analyze Links API
 * Analyzes sermon transcript for Bible study link suggestions
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/SermonManager.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (empty($_SESSION['admin_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'analyze';

try {
    switch ($action) {
        case 'analyze':
            // Analyze transcript text directly (for preview before saving)
            $transcript = $_POST['transcript'] ?? '';

            if (empty($transcript)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Transcript is required']);
                exit;
            }

            $references = $sermonManager->analyzeTranscript($transcript);

            echo json_encode([
                'success' => true,
                'references' => $references
            ]);
            break;

        case 'suggest':
            // Get suggestions for a saved sermon
            $sermonId = intval($_POST['sermon_id'] ?? $_GET['sermon_id'] ?? 0);

            if (!$sermonId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sermon ID is required']);
                exit;
            }

            $suggestions = $sermonManager->suggestStudyLinks($sermonId);

            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions
            ]);
            break;

        case 'confirm':
            // Confirm a suggested link
            $sermonId = intval($_POST['sermon_id'] ?? 0);
            $studyId = intval($_POST['study_id'] ?? 0);

            if (!$sermonId || !$studyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sermon ID and Study ID are required']);
                exit;
            }

            $sermonManager->confirmStudyLink($sermonId, $studyId, $_SESSION['admin_user_id']);

            echo json_encode(['success' => true, 'message' => 'Link confirmed']);
            break;

        case 'add':
            // Manually add a link
            $sermonId = intval($_POST['sermon_id'] ?? 0);
            $studyId = intval($_POST['study_id'] ?? 0);
            $verseRef = $_POST['verse_reference'] ?? null;

            if (!$sermonId || !$studyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sermon ID and Study ID are required']);
                exit;
            }

            $sermonManager->addStudyLink($sermonId, $studyId, $_SESSION['admin_user_id'], $verseRef);

            echo json_encode(['success' => true, 'message' => 'Link added']);
            break;

        case 'remove':
            // Remove a link
            $sermonId = intval($_POST['sermon_id'] ?? 0);
            $studyId = intval($_POST['study_id'] ?? 0);

            if (!$sermonId || !$studyId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sermon ID and Study ID are required']);
                exit;
            }

            $sermonManager->removeStudyLink($sermonId, $studyId);

            echo json_encode(['success' => true, 'message' => 'Link removed']);
            break;

        case 'get_links':
            // Get current links for a sermon
            $sermonId = intval($_GET['sermon_id'] ?? 0);

            if (!$sermonId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sermon ID is required']);
                exit;
            }

            $links = $sermonManager->getStudyLinks($sermonId);

            echo json_encode([
                'success' => true,
                'links' => $links
            ]);
            break;

        case 'save_references':
            // Save detected scripture references for a sermon
            $sermonId = intval($_POST['sermon_id'] ?? 0);

            if (!$sermonId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sermon ID is required']);
                exit;
            }

            $references = $sermonManager->saveScriptureReferences($sermonId);

            echo json_encode([
                'success' => true,
                'references_saved' => count($references)
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
