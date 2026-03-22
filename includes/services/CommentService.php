<?php
/**
 * CommentService - Business logic for comments
 *
 * Handles comment submission, moderation, and notifications.
 */

require_once __DIR__ . '/../repositories/CommentRepository.php';

class CommentService {
    private CommentRepository $repository;
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->repository = new CommentRepository($pdo);
    }

    /**
     * Submit a new comment
     *
     * @param string $type 'blog' or 'sermon'
     * @param int $contentId Post or sermon ID
     * @param array $data Comment data
     * @param array|null $user Current user (null for guest)
     * @return array Result with success status
     */
    public function submit(string $type, int $contentId, array $data, ?array $user = null): array {
        $this->repository->setType($type);

        // Validate content
        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            return ['success' => false, 'error' => 'Comment content is required'];
        }

        if (strlen($content) > 5000) {
            return ['success' => false, 'error' => 'Comment is too long (max 5000 characters)'];
        }

        // For guests, require name and email
        $authorName = null;
        $authorEmail = null;

        if (!$user) {
            $authorName = trim($data['author_name'] ?? '');
            $authorEmail = trim($data['author_email'] ?? '');

            if (empty($authorName)) {
                return ['success' => false, 'error' => 'Name is required'];
            }

            if (empty($authorEmail) || !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Valid email is required'];
            }
        }

        // Check profanity if filter available
        if (function_exists('checkProfanity')) {
            $profanityResult = checkProfanity($content, $this->pdo);
            if ($profanityResult['flagged']) {
                return [
                    'success' => false,
                    'error' => 'Your comment contains inappropriate language',
                    'moderated' => true
                ];
            }
        }

        // Determine initial status
        // Logged-in users with good standing get auto-approved
        $status = 'pending';
        if ($user && in_array($user['role'] ?? '', ['admin', 'editor'])) {
            $status = 'approved';
        }

        // Build comment data
        $fk = $type === 'sermon' ? 'sermon_id' : 'post_id';
        $commentData = [
            $fk => $contentId,
            'content' => $content,
            'status' => $status,
            'user_id' => $user['id'] ?? null,
            'author_name' => $user ? $user['full_name'] : $authorName,
            'author_email' => $user ? $user['email'] : $authorEmail,
            'parent_id' => $data['parent_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        try {
            $commentId = $this->repository->createComment($commentData);

            // Notify moderators if pending
            if ($status === 'pending') {
                $this->notifyModerators($type, $contentId, $commentData);
            }

            return [
                'success' => true,
                'comment_id' => $commentId,
                'status' => $status,
                'message' => $status === 'approved'
                    ? 'Comment posted successfully'
                    : 'Comment submitted and awaiting moderation'
            ];
        } catch (Exception $e) {
            error_log('CommentService submit error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save comment'];
        }
    }

    /**
     * Get comments for content
     */
    public function getForContent(string $type, int $contentId): array {
        $this->repository->setType($type);
        return $this->repository->getForContent($contentId);
    }

    /**
     * Get comment count
     */
    public function countForContent(string $type, int $contentId): int {
        $this->repository->setType($type);
        return $this->repository->countForContent($contentId);
    }

    /**
     * Approve a comment
     */
    public function approve(string $type, int $commentId): bool {
        $this->repository->setType($type);
        return $this->repository->approve($commentId);
    }

    /**
     * Reject a comment
     */
    public function reject(string $type, int $commentId): bool {
        $this->repository->setType($type);
        return $this->repository->reject($commentId);
    }

    /**
     * Delete a comment
     */
    public function delete(string $type, int $commentId): bool {
        $this->repository->setType($type);
        return $this->repository->delete($commentId);
    }

    /**
     * Get pending comments for moderation
     */
    public function getPending(int $limit = 50): array {
        return CommentRepository::getAllPending($this->pdo, $limit);
    }

    /**
     * Notify moderators of new comment
     */
    private function notifyModerators(string $type, int $contentId, array $comment): void {
        // This could send an email or create a notification
        // For now, just log it
        error_log("New {$type} comment pending moderation on content #{$contentId}");
    }
}
