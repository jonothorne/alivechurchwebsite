<?php
/**
 * User Studies API
 * Handles AJAX requests for saving studies, highlights, and reading progress
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/UserStudies.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$auth = new Auth($pdo);

// Require authentication
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$userStudies = new UserStudies($pdo, $auth->id());
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ==================== SAVED STUDIES ====================
    case 'save_study':
        $studyId = intval($_POST['study_id'] ?? 0);
        $notes = $_POST['notes'] ?? null;

        if (!$studyId) {
            echo json_encode(['error' => 'Study ID required']);
            exit;
        }

        $userStudies->saveStudy($studyId, $notes);
        echo json_encode(['success' => true, 'saved' => true]);
        break;

    case 'unsave_study':
        $studyId = intval($_POST['study_id'] ?? 0);

        if (!$studyId) {
            echo json_encode(['error' => 'Study ID required']);
            exit;
        }

        $userStudies->unsaveStudy($studyId);
        echo json_encode(['success' => true, 'saved' => false]);
        break;

    case 'toggle_save':
        $studyId = intval($_POST['study_id'] ?? 0);

        if (!$studyId) {
            echo json_encode(['error' => 'Study ID required']);
            exit;
        }

        if ($userStudies->isStudySaved($studyId)) {
            $userStudies->unsaveStudy($studyId);
            echo json_encode(['success' => true, 'saved' => false]);
        } else {
            $userStudies->saveStudy($studyId);
            echo json_encode(['success' => true, 'saved' => true]);
        }
        break;

    case 'check_saved':
        $studyId = intval($_GET['study_id'] ?? 0);
        $saved = $userStudies->isStudySaved($studyId);
        echo json_encode(['saved' => $saved]);
        break;

    // ==================== HIGHLIGHTS ====================
    case 'add_highlight':
        $studyId = intval($_POST['study_id'] ?? 0);
        $text = $_POST['text'] ?? '';
        $startOffset = intval($_POST['start_offset'] ?? 0);
        $endOffset = intval($_POST['end_offset'] ?? 0);
        $color = $_POST['color'] ?? 'yellow';
        $note = $_POST['note'] ?? null;

        if (!$studyId || !$text) {
            echo json_encode(['error' => 'Study ID and text required']);
            exit;
        }

        $highlightId = $userStudies->addHighlight($studyId, $text, $startOffset, $endOffset, $color, $note);
        echo json_encode(['success' => true, 'highlight_id' => $highlightId]);
        break;

    case 'update_highlight':
        $highlightId = intval($_POST['highlight_id'] ?? 0);
        $note = $_POST['note'] ?? '';

        if (!$highlightId) {
            echo json_encode(['error' => 'Highlight ID required']);
            exit;
        }

        $userStudies->updateHighlightNote($highlightId, $note);
        echo json_encode(['success' => true]);
        break;

    case 'delete_highlight':
        $highlightId = intval($_POST['highlight_id'] ?? 0);

        if (!$highlightId) {
            echo json_encode(['error' => 'Highlight ID required']);
            exit;
        }

        $userStudies->deleteHighlight($highlightId);
        echo json_encode(['success' => true]);
        break;

    case 'get_highlights':
        $studyId = intval($_GET['study_id'] ?? 0);

        if (!$studyId) {
            echo json_encode(['error' => 'Study ID required']);
            exit;
        }

        $highlights = $userStudies->getStudyHighlights($studyId);
        echo json_encode(['highlights' => $highlights]);
        break;

    // ==================== READING PROGRESS ====================
    case 'record_reading':
        $studyId = intval($_POST['study_id'] ?? 0);
        $timeSpent = intval($_POST['time_spent'] ?? 0);
        $scrollProgress = floatval($_POST['scroll_progress'] ?? 0);
        $completed = isset($_POST['completed']) && $_POST['completed'];

        if (!$studyId) {
            echo json_encode(['error' => 'Study ID required']);
            exit;
        }

        $userStudies->recordReading($studyId, $timeSpent, $scrollProgress, $completed);

        // Update streak if completed or significant progress
        if ($completed || $scrollProgress > 50) {
            $auth->updateReadingStreak();
        }

        // Track reading minutes (convert seconds to minutes)
        if ($timeSpent > 0) {
            $minutes = ceil($timeSpent / 60);
            $auth->addReadingMinutes($minutes);
        }

        echo json_encode(['success' => true]);
        break;

    // ==================== READING PLANS ====================
    case 'start_plan':
        $planId = intval($_POST['plan_id'] ?? 0);

        if (!$planId) {
            echo json_encode(['error' => 'Plan ID required']);
            exit;
        }

        $userStudies->startPlan($planId);

        // Get plan slug for redirect
        $stmt = $pdo->prepare("SELECT slug FROM reading_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $planSlug = $stmt->fetchColumn();

        // Redirect to plan overview page
        echo json_encode([
            'success' => true,
            'redirect' => '/reading-plan/' . $planSlug
        ]);
        break;

    case 'complete_day':
        $planId = intval($_POST['plan_id'] ?? 0);
        $dayNumber = intval($_POST['day_number'] ?? 0);
        $timeSpent = intval($_POST['time_spent'] ?? 0);
        $notes = $_POST['notes'] ?? null;

        if (!$planId || !$dayNumber) {
            echo json_encode(['error' => 'Plan ID and day number required']);
            exit;
        }

        $userStudies->completePlanDay($planId, $dayNumber, $timeSpent, $notes);
        $auth->updateReadingStreak();
        echo json_encode(['success' => true]);
        break;

    case 'get_active_plans':
        $plans = $userStudies->getActivePlans();
        echo json_encode(['plans' => $plans]);
        break;

    case 'get_todays_reading':
        $reading = $userStudies->getTodaysReading();
        echo json_encode(['reading' => $reading]);
        break;

    // ==================== USER SETTINGS ====================
    case 'save_font_settings':
        $fontSize = intval($_POST['font_size'] ?? 100);
        $fontFamily = $_POST['font_family'] ?? 'default';

        // Validate font size (70-150%)
        $fontSize = max(70, min(150, $fontSize));

        // Validate font family
        $allowedFonts = ['default', 'serif', 'sans-serif', 'georgia', 'times', 'arial', 'verdana', 'openDyslexic'];
        if (!in_array($fontFamily, $allowedFonts)) {
            $fontFamily = 'default';
        }

        // Get current preferences or create empty
        $user = $auth->user();
        $preferences = [];
        if (!empty($user['preferences'])) {
            $preferences = json_decode($user['preferences'], true) ?: [];
        }

        // Update font settings
        $preferences['study_font_size'] = $fontSize;
        $preferences['study_font_family'] = $fontFamily;

        // Save to database
        $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
        $stmt->execute([json_encode($preferences), $auth->id()]);

        echo json_encode(['success' => true]);
        break;

    case 'get_font_settings':
        $user = $auth->user();
        $preferences = [];
        if (!empty($user['preferences'])) {
            $preferences = json_decode($user['preferences'], true) ?: [];
        }

        echo json_encode([
            'fontSize' => $preferences['study_font_size'] ?? 100,
            'fontFamily' => $preferences['study_font_family'] ?? 'default'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
