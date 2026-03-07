<?php
/**
 * Block Builder API - Manage page blocks
 *
 * GET ?page=slug - Get all blocks for a page
 * POST - Save blocks (create/update/reorder)
 * DELETE ?uuid=xxx - Delete a block
 */

header('Content-Type: application/json');

// Start session and check auth
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cms/BlockBuilder.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$blockBuilder = new BlockBuilder();

// Get user ID - support both session formats
$userId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_user']['id'] ?? null;

switch ($method) {
    case 'GET':
        handleGet($blockBuilder);
        break;
    case 'POST':
        handlePost($blockBuilder, $userId);
        break;
    case 'DELETE':
        handleDelete($blockBuilder, $userId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

/**
 * GET - Retrieve blocks for a page
 */
function handleGet($blockBuilder) {
    $pageSlug = $_GET['page'] ?? null;

    if (!$pageSlug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing page parameter']);
        return;
    }

    $blocks = $blockBuilder->getPageBlocks($pageSlug);

    // Get block types for the editor
    $blockTypes = BlockBuilder::getBlockTypesForJS();
    $categories = BlockBuilder::getCategories();

    echo json_encode([
        'success' => true,
        'blocks' => $blocks,
        'blockTypes' => $blockTypes,
        'categories' => $categories
    ]);
}

/**
 * POST - Save blocks (create, update, or bulk save)
 */
function handlePost($blockBuilder, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $pageSlug = $input['page'] ?? null;

    if (!$pageSlug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing page slug']);
        return;
    }

    // Check for bulk save (array of blocks)
    if (isset($input['blocks']) && is_array($input['blocks'])) {
        $success = $blockBuilder->saveBlocks($pageSlug, $input['blocks'], $userId);

        if ($success) {
            // Log activity
            log_activity($userId, 'save_blocks', 'page', null,
                "Updated blocks on page '{$pageSlug}'");

            echo json_encode([
                'success' => true,
                'message' => 'Blocks saved successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save blocks']);
        }
        return;
    }

    // Check for reorder operation
    if (isset($input['reorder']) && is_array($input['reorder'])) {
        $success = $blockBuilder->reorderBlocks($pageSlug, $input['reorder'], $userId);

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Blocks reordered successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to reorder blocks']);
        }
        return;
    }

    // Single block save
    $blockUuid = $input['uuid'] ?? null;
    $blockType = $input['type'] ?? null;
    $blockData = $input['data'] ?? [];
    $displayOrder = $input['order'] ?? 0;

    if (!$blockUuid || !$blockType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing block uuid or type']);
        return;
    }

    $success = $blockBuilder->saveBlock($pageSlug, $blockUuid, $blockType, $blockData, $displayOrder, $userId);

    if ($success) {
        log_activity($userId, 'save_block', 'block', null,
            "Updated {$blockType} block on page '{$pageSlug}'");

        echo json_encode([
            'success' => true,
            'message' => 'Block saved successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save block']);
    }
}

/**
 * DELETE - Remove a block
 */
function handleDelete($blockBuilder, $userId) {
    $blockUuid = $_GET['uuid'] ?? null;

    if (!$blockUuid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing block uuid']);
        return;
    }

    $success = $blockBuilder->deleteBlock($blockUuid, $userId);

    if ($success) {
        log_activity($userId, 'delete_block', 'block', null,
            "Deleted block {$blockUuid}");

        echo json_encode([
            'success' => true,
            'message' => 'Block deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete block']);
    }
}
