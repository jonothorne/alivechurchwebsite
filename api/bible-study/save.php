<?php
/**
 * Bible Study Inline Save API
 * Handles saving inline edits to Bible studies
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/BibleStudyTagger.php';
require_once __DIR__ . '/../../includes/CrossReferenceManager.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);

// Check authentication
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check if user is editor or admin
if (!$auth->isEditor()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['study_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing study_id']);
    exit;
}

$studyId = intval($input['study_id']);

// Verify study exists
$stmt = $pdo->prepare("SELECT id, author_id FROM bible_studies WHERE id = ?");
$stmt->execute([$studyId]);
$study = $stmt->fetch();

if (!$study) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Study not found']);
    exit;
}

// Check permission: must be admin or the author
$user = $auth->user();
if ($user['role'] !== 'admin' && $study['author_id'] !== $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only edit your own studies']);
    exit;
}

// Build update query based on provided fields
$allowedFields = ['title', 'summary', 'content', 'status'];
$updates = [];
$params = [];

foreach ($allowedFields as $field) {
    if (isset($input[$field])) {
        $value = $input[$field];

        // Sanitize based on field type
        if ($field === 'status') {
            $value = in_array($value, ['draft', 'published']) ? $value : 'draft';
        } elseif ($field === 'title' || $field === 'summary') {
            $value = strip_tags(trim($value));
        } elseif ($field === 'content') {
            // Allow safe HTML tags for content
            $value = trim($value);
            // Basic sanitization - allow common formatting tags
            $allowed = '<p><br><h2><h3><h4><strong><b><em><i><blockquote><ul><ol><li><a><span>';
            $value = strip_tags($value, $allowed);
        }

        $updates[] = "$field = ?";
        $params[] = $value;
    }
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
    exit;
}

// Add updated_at timestamp
$updates[] = "updated_at = NOW()";

// Execute update
$params[] = $studyId;
$sql = "UPDATE bible_studies SET " . implode(', ', $updates) . " WHERE id = ?";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $tagsCount = 0;
    $refsCount = 0;

    // If content was updated, re-run tagging and cross-reference detection
    if (isset($input['content'])) {
        $tagger = new BibleStudyTagger($pdo);
        $tagsCount = $tagger->tagStudy($studyId);

        $crossRefManager = new CrossReferenceManager($pdo);
        $refsCount = $crossRefManager->saveReferences($studyId);

        // Recalculate reading time
        $wordCount = str_word_count(strip_tags($input['content']));
        $readingTime = max(1, round($wordCount / 200));
        $pdo->prepare("UPDATE bible_studies SET reading_time = ? WHERE id = ?")
            ->execute([$readingTime, $studyId]);
    }

    // Log activity
    if (function_exists('log_activity')) {
        $changedFields = array_keys(array_filter($input, fn($k) => in_array($k, $allowedFields), ARRAY_FILTER_USE_KEY));
        log_activity(
            $user['id'],
            'update',
            'bible_study',
            $studyId,
            'Inline edit: ' . implode(', ', $changedFields)
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Study updated successfully',
        'tags_count' => $tagsCount,
        'references_count' => $refsCount
    ]);

} catch (PDOException $e) {
    error_log('Bible study save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
