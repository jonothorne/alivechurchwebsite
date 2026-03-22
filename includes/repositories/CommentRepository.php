<?php
/**
 * CommentRepository - Data access for comments (blog and sermon)
 *
 * Consolidates comment queries from blog-post.php, sermon.php
 * Handles both blog_comments and sermon_comments tables with a unified interface.
 */

require_once __DIR__ . '/BaseRepository.php';

class CommentRepository extends BaseRepository {
    private string $type;

    /**
     * @param PDO $pdo
     * @param string $type 'blog' or 'sermon'
     */
    public function __construct(PDO $pdo, string $type = 'blog') {
        parent::__construct($pdo);
        $this->type = $type;
    }

    protected function getTableName(): string {
        return $this->type === 'sermon' ? 'sermon_comments' : 'blog_comments';
    }

    /**
     * Get foreign key column name
     */
    private function getForeignKey(): string {
        return $this->type === 'sermon' ? 'sermon_id' : 'post_id';
    }

    /**
     * Set comment type
     */
    public function setType(string $type): self {
        $this->type = $type;
        return $this;
    }

    /**
     * Get all approved comments for content with user info
     * Returns comments organized with replies nested under parents
     */
    public function getForContent(int $contentId): array {
        $table = $this->getTableName();
        $fk = $this->getForeignKey();

        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.full_name as user_full_name, u.username as user_username,
                    u.avatar as user_avatar, u.avatar_color as user_avatar_color
             FROM {$table} c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.{$fk} = ? AND c.status = 'approved'
             ORDER BY c.created_at ASC"
        );
        $stmt->execute([$contentId]);
        $allComments = $stmt->fetchAll();

        // Organize into parents and replies
        $comments = [];
        $repliesByParent = [];

        foreach ($allComments as $comment) {
            if ($comment['parent_id'] === null) {
                $comments[] = $comment;
            } else {
                $repliesByParent[$comment['parent_id']][] = $comment;
            }
        }

        // Attach replies to their parent comments
        foreach ($comments as &$comment) {
            $comment['replies'] = $repliesByParent[$comment['id']] ?? [];
        }

        return $comments;
    }

    /**
     * Get comment count for content
     */
    public function countForContent(int $contentId, string $status = 'approved'): int {
        $table = $this->getTableName();
        $fk = $this->getForeignKey();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as total FROM {$table} WHERE {$fk} = ? AND status = ?"
        );
        $stmt->execute([$contentId, $status]);
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Create a new comment
     */
    public function createComment(array $data): int {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'pending';
        return $this->create($data);
    }

    /**
     * Approve a comment
     */
    public function approve(int $id): bool {
        return $this->update($id, ['status' => 'approved']);
    }

    /**
     * Reject a comment
     */
    public function reject(int $id): bool {
        return $this->update($id, ['status' => 'rejected']);
    }

    /**
     * Get pending comments (for moderation)
     */
    public function getPending(int $limit = 50): array {
        $table = $this->getTableName();
        $fk = $this->getForeignKey();
        $contentTable = $this->type === 'sermon' ? 'sermons' : 'blog_posts';

        $stmt = $this->pdo->prepare(
            "SELECT c.*, ct.title as content_title, ct.slug as content_slug,
                    u.full_name as user_full_name
             FROM {$table} c
             LEFT JOIN {$contentTable} ct ON c.{$fk} = ct.id
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.status = 'pending'
             ORDER BY c.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get all pending comments (both blog and sermon)
     */
    public static function getAllPending(PDO $pdo, int $limit = 50): array {
        $blogRepo = new self($pdo, 'blog');
        $sermonRepo = new self($pdo, 'sermon');

        $blogComments = $blogRepo->getPending($limit);
        $sermonComments = $sermonRepo->getPending($limit);

        // Add type to each comment
        foreach ($blogComments as &$c) {
            $c['comment_type'] = 'blog';
        }
        foreach ($sermonComments as &$c) {
            $c['comment_type'] = 'sermon';
        }

        // Merge and sort by date
        $all = array_merge($blogComments, $sermonComments);
        usort($all, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return array_slice($all, 0, $limit);
    }

    /**
     * Get recent comments (for dashboard)
     */
    public function getRecent(int $limit = 10): array {
        $table = $this->getTableName();
        $fk = $this->getForeignKey();
        $contentTable = $this->type === 'sermon' ? 'sermons' : 'blog_posts';

        $stmt = $this->pdo->prepare(
            "SELECT c.*, ct.title as content_title, ct.slug as content_slug
             FROM {$table} c
             LEFT JOIN {$contentTable} ct ON c.{$fk} = ct.id
             WHERE c.status = 'approved'
             ORDER BY c.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Delete all comments for content
     */
    public function deleteForContent(int $contentId): int {
        $fk = $this->getForeignKey();
        return $this->deleteWhere([$fk => $contentId]);
    }

    /**
     * Check if user has already commented on content
     */
    public function hasUserCommented(int $contentId, int $userId): bool {
        $table = $this->getTableName();
        $fk = $this->getForeignKey();

        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM {$table} WHERE {$fk} = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$contentId, $userId]);
        return (bool)$stmt->fetch();
    }
}
