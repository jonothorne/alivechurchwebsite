<?php
/**
 * Example API Endpoint using new utilities
 *
 * This file demonstrates how to use the new scalability improvements:
 * - ApiResponse for consistent JSON responses
 * - Validator for input validation
 * - Repository for data access
 * - AuthMiddleware for authentication
 * - Pagination for paginated results
 *
 * BEFORE (old pattern):
 *   - 50+ lines of boilerplate
 *   - Direct PDO queries
 *   - Manual JSON encoding
 *   - Duplicated validation
 *
 * AFTER (new pattern):
 *   - ~20 lines of clean code
 *   - Repository pattern
 *   - Standardized responses
 *   - Reusable validation
 */

// Example: GET /api/sermons (list sermons with pagination)

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../Pagination.php';
require_once __DIR__ . '/../repositories/SermonRepository.php';

// Require GET method
ApiResponse::requireGet();

// Get pagination from request
$pagination = Pagination::fromRequest(maxLimit: 50, defaultLimit: 20);

// Get sermons using repository
$sermonRepo = new SermonRepository($pdo);
$sermons = $sermonRepo->getVisibleSermons($pagination->getLimit());

// Send success response
ApiResponse::success([
    'sermons' => $sermons,
    'pagination' => $pagination->setTotal($sermonRepo->countVisible())->toArray()
]);


// ============================================================================
// Example: POST /api/comments (submit comment with validation)
// ============================================================================

/*
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/CommentService.php';

// Require POST method
ApiResponse::requirePost();

// Get input
$input = ApiResponse::getJsonInput(required: true);

// Validate input
$data = Validator::make($input)
    ->required('content', 'Comment is required')
    ->minLength('content', 3, 'Comment must be at least 3 characters')
    ->maxLength('content', 5000, 'Comment is too long')
    ->required('content_id', 'Content ID is required')
    ->in('type', ['blog', 'sermon'], 'Invalid comment type')
    ->validateOrFail();

// Submit comment using service
$commentService = new CommentService($pdo);
$result = $commentService->submit(
    $data['type'],
    (int)$data['content_id'],
    $data,
    AuthMiddleware::user() // May be null for guests
);

if ($result['success']) {
    ApiResponse::success($result);
} else {
    ApiResponse::error($result['error']);
}
*/


// ============================================================================
// Example: Admin endpoint with auth
// ============================================================================

/*
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

// Require POST and admin auth
ApiResponse::requirePost();
AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$input = ApiResponse::getJsonInput(required: true);

$userRepo = new UserRepository($pdo);
$user = $userRepo->find($input['user_id']);

if (!$user) {
    ApiResponse::notFound('User not found');
}

$userRepo->deactivate($input['user_id']);
ApiResponse::success(['message' => 'User deactivated']);
*/


// ============================================================================
// Comparison: Old vs New Pattern
// ============================================================================

/*
// OLD PATTERN (30+ lines):

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();
require_once __DIR__ . '/../../includes/Auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content is required']);
    exit;
}

// ... more validation ...
// ... direct PDO queries ...

echo json_encode(['success' => true, 'data' => $result]);


// NEW PATTERN (10 lines):

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

ApiResponse::requirePost();
AuthMiddleware::requireAuth();

$data = Validator::make(ApiResponse::getJsonInput(true))
    ->required('content')
    ->validateOrFail();

// Use repository/service...
ApiResponse::success($result);
*/
