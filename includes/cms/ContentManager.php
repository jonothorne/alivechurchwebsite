<?php
/**
 * ContentManager - Core class for managing editable content blocks
 *
 * Handles loading, saving, and rendering of inline-editable content.
 */

class ContentManager {
    private $pdo;
    private $pageSlug;
    private $isEditMode = false;
    private $loadedBlocks = [];

    public function __construct($pageSlug = null) {
        require_once __DIR__ . '/../db-config.php';
        $this->pdo = getDbConnection();
        $this->pageSlug = $pageSlug ?? $this->getCurrentPageSlug();
        $this->checkEditMode();
    }

    /**
     * Determine current page slug from URL
     */
    private function getCurrentPageSlug() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = trim($path, '/');
        return $path ?: 'home';
    }

    /**
     * Check if user is in edit mode
     */
    private function checkEditMode() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Check for admin login - supports both session formats
        $isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
                      isset($_SESSION['admin_user_id']);
        $this->isEditMode = $isLoggedIn &&
                           (!isset($_GET['preview']) || $_GET['preview'] !== 'true');
    }

    /**
     * Check if currently in edit mode
     */
    public function isEditMode() {
        return $this->isEditMode;
    }

    /**
     * Get content block - returns editable wrapper in edit mode
     *
     * @param string $key Block identifier
     * @param string $default Default content if block doesn't exist
     * @param string $type Content type: text, html, image, link
     * @param array $options Additional options (tag, class, raw, etc.)
     */
    public function block($key, $default = '', $type = 'html', $options = []) {
        $content = $this->getBlockContent($key, $default);

        // If 'raw' option is set, or if there's no tag specified and we're not in edit mode,
        // just return the content without wrapper
        if (!empty($options['raw']) || (!$this->isEditMode && !isset($options['tag']))) {
            return $type === 'text' ? htmlspecialchars($content) : $content;
        }

        if ($this->isEditMode) {
            return $this->renderEditableBlock($key, $content, $type, $options);
        }

        // Not in edit mode but tag specified - wrap content
        if (isset($options['tag'])) {
            $tag = $options['tag'];
            $class = isset($options['class']) ? ' class="' . htmlspecialchars($options['class']) . '"' : '';
            if ($type === 'text') {
                return "<{$tag}{$class}>" . htmlspecialchars($content) . "</{$tag}>";
            }
            return "<{$tag}{$class}>{$content}</{$tag}>";
        }

        return $content;
    }

    /**
     * Shorthand for text block (no HTML allowed)
     * By default returns raw content - the template element should have data-cms-* attributes
     */
    public function text($key, $default = '', $options = []) {
        // Default to raw output - template provides the wrapper element
        if (!isset($options['raw']) && !isset($options['tag'])) {
            $options['raw'] = true;
        }
        return $this->block($key, $default, 'text', $options);
    }

    /**
     * Shorthand for HTML block (rich text)
     * By default returns raw content - the template element should have data-cms-* attributes
     */
    public function html($key, $default = '', $options = []) {
        // Default to raw output - template provides the wrapper element
        if (!isset($options['raw']) && !isset($options['tag'])) {
            $options['raw'] = true;
        }
        return $this->block($key, $default, 'html', $options);
    }

    /**
     * Shorthand for image block
     */
    public function image($key, $default = '', $alt = '', $options = []) {
        $src = $this->getBlockContent($key, $default);
        $altText = $options['alt'] ?? $alt;
        $class = isset($options['class']) ? ' class="' . htmlspecialchars($options['class']) . '"' : '';
        $style = isset($options['style']) ? ' style="' . htmlspecialchars($options['style']) . '"' : '';

        if ($this->isEditMode) {
            $dataAttrs = $this->getEditableAttrs($key, 'image');
            return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($altText) . '"' . $class . $style . ' ' . $dataAttrs . '>';
        }

        return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($altText) . '"' . $class . $style . '>';
    }

    /**
     * Shorthand for link block (href is editable)
     */
    public function link($key, $text, $defaultHref = '#', $options = []) {
        $href = $this->getBlockContent($key, $defaultHref);

        if ($this->isEditMode) {
            $dataAttrs = $this->getEditableAttrs($key, 'link');
            $class = isset($options['class']) ? ' class="' . htmlspecialchars($options['class']) . '"' : '';
            return '<a href="' . htmlspecialchars($href) . '"' . $class . ' ' . $dataAttrs . '>' . $text . '</a>';
        }

        $class = isset($options['class']) ? ' class="' . htmlspecialchars($options['class']) . '"' : '';
        return '<a href="' . htmlspecialchars($href) . '"' . $class . '>' . $text . '</a>';
    }

    /**
     * Get raw block content from database
     */
    public function getBlockContent($key, $default = '') {
        // Check cache first
        if (isset($this->loadedBlocks[$key])) {
            return $this->loadedBlocks[$key];
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT content FROM content_blocks WHERE page_slug = ? AND block_key = ?"
            );
            $stmt->execute([$this->pageSlug, $key]);
            $result = $stmt->fetch();

            if ($result && $result['content'] !== null) {
                $this->loadedBlocks[$key] = $result['content'];
                return $result['content'];
            }
        } catch (Exception $e) {
            error_log('ContentManager error: ' . $e->getMessage());
        }

        return $default;
    }

    /**
     * Get global content (shared across all pages)
     */
    public function global($key, $default = '') {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT content FROM global_content WHERE block_key = ?"
            );
            $stmt->execute([$key]);
            $result = $stmt->fetch();

            if ($result && $result['content'] !== null) {
                $content = $result['content'];

                if ($this->isEditMode) {
                    return '<span data-cms-global="' . htmlspecialchars($key) . '" data-cms-type="text">' .
                           htmlspecialchars($content) . '</span>';
                }

                return htmlspecialchars($content);
            }
        } catch (Exception $e) {
            error_log('ContentManager global error: ' . $e->getMessage());
        }

        return $default;
    }

    /**
     * Save content block
     */
    public function saveBlock($key, $content, $type = 'html', $userId = null) {
        try {
            // Get current version for revision
            $stmt = $this->pdo->prepare(
                "SELECT id, content, version FROM content_blocks WHERE page_slug = ? AND block_key = ?"
            );
            $stmt->execute([$this->pageSlug, $key]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Save revision
                $revStmt = $this->pdo->prepare(
                    "INSERT INTO content_revisions (block_id, content, version, created_by) VALUES (?, ?, ?, ?)"
                );
                $revStmt->execute([$existing['id'], $existing['content'], $existing['version'], $userId]);

                // Update block
                $newVersion = $existing['version'] + 1;
                $updateStmt = $this->pdo->prepare(
                    "UPDATE content_blocks SET content = ?, version = ?, updated_by = ? WHERE id = ?"
                );
                $updateStmt->execute([$content, $newVersion, $userId, $existing['id']]);
            } else {
                // Create new block
                $insertStmt = $this->pdo->prepare(
                    "INSERT INTO content_blocks (page_slug, block_key, content_type, content, updated_by) VALUES (?, ?, ?, ?, ?)"
                );
                $insertStmt->execute([$this->pageSlug, $key, $type, $content, $userId]);
            }

            // Update cache
            $this->loadedBlocks[$key] = $content;

            return true;
        } catch (Exception $e) {
            error_log('ContentManager saveBlock error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save global content
     */
    public function saveGlobal($key, $content, $userId = null) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO global_content (block_key, content, updated_by) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by)"
            );
            $stmt->execute([$key, $content, $userId]);
            return true;
        } catch (Exception $e) {
            error_log('ContentManager saveGlobal error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Render editable block wrapper
     */
    private function renderEditableBlock($key, $content, $type, $options) {
        $tag = $options['tag'] ?? 'div';
        $class = $options['class'] ?? '';
        $dataAttrs = $this->getEditableAttrs($key, $type);

        $classAttr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';

        if ($type === 'text') {
            // Plain text - escape HTML
            return "<{$tag}{$classAttr} {$dataAttrs}>" . htmlspecialchars($content) . "</{$tag}>";
        }

        // HTML content - allow HTML
        return "<{$tag}{$classAttr} {$dataAttrs}>{$content}</{$tag}>";
    }

    /**
     * Get data attributes for editable elements
     */
    private function getEditableAttrs($key, $type) {
        return 'data-cms-editable="' . htmlspecialchars($key) . '" ' .
               'data-cms-page="' . htmlspecialchars($this->pageSlug) . '" ' .
               'data-cms-type="' . htmlspecialchars($type) . '"';
    }

    /**
     * Get all blocks for a page
     */
    public function getAllBlocks() {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT block_key, content, content_type FROM content_blocks WHERE page_slug = ?"
            );
            $stmt->execute([$this->pageSlug]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('ContentManager getAllBlocks error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revision history for a block
     */
    public function getRevisions($key, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT r.*, u.username
                 FROM content_revisions r
                 LEFT JOIN content_blocks b ON r.block_id = b.id
                 LEFT JOIN users u ON r.created_by = u.id
                 WHERE b.page_slug = ? AND b.block_key = ?
                 ORDER BY r.version DESC
                 LIMIT ?"
            );
            $stmt->execute([$this->pageSlug, $key, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('ContentManager getRevisions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Restore a revision
     */
    public function restoreRevision($key, $version, $userId = null) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT r.content
                 FROM content_revisions r
                 JOIN content_blocks b ON r.block_id = b.id
                 WHERE b.page_slug = ? AND b.block_key = ? AND r.version = ?"
            );
            $stmt->execute([$this->pageSlug, $key, $version]);
            $revision = $stmt->fetch();

            if ($revision) {
                return $this->saveBlock($key, $revision['content'], 'html', $userId);
            }

            return false;
        } catch (Exception $e) {
            error_log('ContentManager restoreRevision error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set page slug (useful for loading content for different pages)
     */
    public function setPageSlug($slug) {
        $this->pageSlug = $slug;
        $this->loadedBlocks = []; // Clear cache
    }

    /**
     * Get current page slug
     */
    public function getPageSlug() {
        return $this->pageSlug;
    }
}
