<?php
/**
 * Comments API Endpoint
 * Handles AJAX comment submissions for blog posts and sermons
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/profanity-filter.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$pdo = getDbConnection();
$auth = new Auth($pdo);
$currentUser = $auth->user();

// Get input (support both form data and JSON)
$input = $_POST;
if (empty($input)) {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true) ?? [];
}

// Required fields
$commentType = $input['comment_type'] ?? ''; // 'blog' or 'sermon'
$contentId = intval($input['content_id'] ?? 0);
$content = trim($input['content'] ?? '');
$parentId = !empty($input['parent_id']) ? intval($input['parent_id']) : null;

// Validate comment type
if (!in_array($commentType, ['blog', 'sermon'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid comment type']);
    exit;
}

// Validate content ID
if ($contentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid content ID']);
    exit;
}

// Validate comment content
if (empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a comment', 'field' => 'content']);
    exit;
}

// Set up table and ID column based on type
if ($commentType === 'blog') {
    $table = 'blog_comments';
    $idColumn = 'post_id';
    $contentTable = 'blog_posts';
} else {
    $table = 'sermon_comments';
    $idColumn = 'sermon_id';
    $contentTable = 'sermons';
}

// Verify the content exists
$checkStmt = $pdo->prepare("SELECT id FROM {$contentTable} WHERE id = ?");
$checkStmt->execute([$contentId]);
if (!$checkStmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Content not found']);
    exit;
}

// Check profanity
$profanityCheck = checkProfanity($content, $pdo);

try {
    if ($currentUser) {
        // Logged-in user
        $userId = $currentUser['id'];
        $authorName = $currentUser['full_name'] ?? $currentUser['username'];
        $authorEmail = $currentUser['email'];

        // Auto-approve if no profanity
        $status = $profanityCheck['has_profanity'] ? 'pending' : 'approved';

        $insertStmt = $pdo->prepare("INSERT INTO {$table} ({$idColumn}, user_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$contentId, $userId, $parentId, $authorName, $authorEmail, $content, $status]);

        $commentId = $pdo->lastInsertId();

        if ($status === 'approved') {
            echo json_encode([
                'success' => true,
                'approved' => true,
                'message' => 'Your comment has been posted!',
                'comment' => buildCommentHtml($commentId, $authorName, $content, $currentUser, $parentId !== null)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'approved' => false,
                'message' => 'Your comment has been submitted and is awaiting moderation.'
            ]);
        }
    } else {
        // Guest user - require name and email
        $authorName = trim($input['author_name'] ?? '');
        $authorEmail = trim($input['author_email'] ?? '');

        if (empty($authorName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Name is required', 'field' => 'author_name']);
            exit;
        }

        if (empty($authorEmail) || !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid email is required', 'field' => 'author_email']);
            exit;
        }

        // Guest comments always go to moderation
        $insertStmt = $pdo->prepare("INSERT INTO {$table} ({$idColumn}, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $insertStmt->execute([$contentId, $parentId, $authorName, $authorEmail, $content]);

        echo json_encode([
            'success' => true,
            'approved' => false,
            'message' => 'Thank you! Your comment has been submitted and will appear after moderation.'
        ]);
    }
} catch (PDOException $e) {
    error_log("Comment submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit comment. Please try again.']);
}

/**
 * Build HTML for a newly posted comment (for instant display)
 */
function buildCommentHtml($id, $authorName, $content, $user, $isReply = false): string
{
    $avatarHtml = '';
    if ($user) {
        if (!empty($user['avatar'])) {
            $avatarHtml = '<img src="' . htmlspecialchars($user['avatar']) . '" alt="" class="comment-avatar">';
        } else {
            $color = htmlspecialchars($user['avatar_color'] ?? '#4b2679');
            $initial = strtoupper(substr($authorName, 0, 1));
            $avatarHtml = '<div class="comment-avatar comment-avatar-initials" style="background-color: ' . $color . '">' . $initial . '</div>';
        }
    }

    $date = date('M j, Y \a\t g:ia');
    $class = $isReply ? 'comment reply new-comment' : 'comment new-comment';

    return '<div class="' . $class . '" id="comment-' . $id . '">
        <div class="comment-header">
            ' . $avatarHtml . '
            <strong class="comment-author">' . htmlspecialchars($authorName) . '</strong>
            <span class="comment-date">' . $date . '</span>
        </div>
        <div class="comment-content">' . nl2br(htmlspecialchars($content)) . '</div>
    </div>';
}
