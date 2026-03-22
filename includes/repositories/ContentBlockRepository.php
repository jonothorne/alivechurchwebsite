<?php
/**
 * ContentBlockRepository - Data access for CMS content blocks
 *
 * Consolidates content block queries from ContentManager.php
 */

require_once __DIR__ . '/BaseRepository.php';

class ContentBlockRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'content_blocks';
    }

    /**
     * Get all blocks for a page (preload)
     */
    public function getForPage(string $pageSlug): array {
        $stmt = $this->pdo->prepare(
            "SELECT block_key, content FROM content_blocks WHERE page_slug = ?"
        );
        $stmt->execute([$pageSlug]);

        $blocks = [];
        while ($row = $stmt->fetch()) {
            $blocks[$row['block_key']] = $row['content'];
        }
        return $blocks;
    }

    /**
     * Get a single block
     */
    public function getBlock(string $pageSlug, string $blockKey): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT content FROM content_blocks WHERE page_slug = ? AND block_key = ?"
        );
        $stmt->execute([$pageSlug, $blockKey]);
        $result = $stmt->fetch();

        return $result ? $result['content'] : null;
    }

    /**
     * Save a block (create or update)
     */
    public function saveBlock(string $pageSlug, string $blockKey, string $content, string $type = 'html', ?int $userId = null): bool {
        // Check if block exists
        $stmt = $this->pdo->prepare(
            "SELECT id, content, version FROM content_blocks WHERE page_slug = ? AND block_key = ?"
        );
        $stmt->execute([$pageSlug, $blockKey]);
        $existing = $stmt->fetch();

        try {
            if ($existing) {
                // Save revision first
                $this->saveRevision($existing['id'], $existing['content'], $existing['version'], $userId);

                // Update block
                $updateStmt = $this->pdo->prepare(
                    "UPDATE content_blocks SET content = ?, version = ?, updated_by = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                return $updateStmt->execute([$content, $existing['version'] + 1, $userId, $existing['id']]);
            } else {
                // Create new block
                $insertStmt = $this->pdo->prepare(
                    "INSERT INTO content_blocks (page_slug, block_key, content_type, content, updated_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
                );
                return $insertStmt->execute([$pageSlug, $blockKey, $type, $content, $userId]);
            }
        } catch (Exception $e) {
            error_log('ContentBlockRepository saveBlock error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save revision
     */
    private function saveRevision(int $blockId, string $content, int $version, ?int $userId): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO content_revisions (block_id, content, version, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$blockId, $content, $version, $userId]);
    }

    /**
     * Get revision history for a block
     */
    public function getRevisions(string $pageSlug, string $blockKey, int $limit = 10): array {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username
             FROM content_revisions r
             LEFT JOIN content_blocks b ON r.block_id = b.id
             LEFT JOIN users u ON r.created_by = u.id
             WHERE b.page_slug = ? AND b.block_key = ?
             ORDER BY r.version DESC
             LIMIT ?"
        );
        $stmt->execute([$pageSlug, $blockKey, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Restore a revision
     */
    public function restoreRevision(string $pageSlug, string $blockKey, int $version, ?int $userId = null): bool {
        $stmt = $this->pdo->prepare(
            "SELECT r.content
             FROM content_revisions r
             JOIN content_blocks b ON r.block_id = b.id
             WHERE b.page_slug = ? AND b.block_key = ? AND r.version = ?"
        );
        $stmt->execute([$pageSlug, $blockKey, $version]);
        $revision = $stmt->fetch();

        if ($revision) {
            return $this->saveBlock($pageSlug, $blockKey, $revision['content'], 'html', $userId);
        }

        return false;
    }

    /**
     * Get all blocks (for admin)
     */
    public function getAllBlocks(): array {
        return $this->pdo->query(
            "SELECT cb.*, u.username as updated_by_name
             FROM content_blocks cb
             LEFT JOIN users u ON cb.updated_by = u.id
             ORDER BY cb.page_slug, cb.block_key"
        )->fetchAll();
    }

    /**
     * Get all pages with content blocks
     */
    public function getAllPages(): array {
        return $this->pdo->query(
            "SELECT DISTINCT page_slug, COUNT(*) as block_count
             FROM content_blocks
             GROUP BY page_slug
             ORDER BY page_slug"
        )->fetchAll();
    }

    /**
     * Delete a block
     */
    public function deleteBlock(string $pageSlug, string $blockKey): bool {
        $stmt = $this->pdo->prepare(
            "DELETE FROM content_blocks WHERE page_slug = ? AND block_key = ?"
        );
        return $stmt->execute([$pageSlug, $blockKey]);
    }

    /**
     * Get global content
     */
    public function getGlobal(string $key): ?string {
        $stmt = $this->pdo->prepare("SELECT content FROM global_content WHERE block_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['content'] : null;
    }

    /**
     * Save global content
     */
    public function saveGlobal(string $key, string $content, ?int $userId = null): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO global_content (block_key, content, updated_by, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by), updated_at = NOW()"
        );
        return $stmt->execute([$key, $content, $userId]);
    }

    /**
     * Get all global content
     */
    public function getAllGlobal(): array {
        return $this->pdo->query("SELECT * FROM global_content ORDER BY block_key")->fetchAll();
    }
}
