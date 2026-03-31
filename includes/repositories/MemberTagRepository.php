<?php
/**
 * MemberTagRepository - Data access for member tags
 */

require_once __DIR__ . '/BaseRepository.php';

class MemberTagRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'member_tags';
    }

    /**
     * Get all tags grouped by tag_group
     */
    public function getAllGrouped(): array {
        $tags = $this->all('tag_group ASC, name ASC');
        $grouped = [];

        foreach ($tags as $tag) {
            $group = $tag['tag_group'] ?? 'Other';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $tag;
        }

        return $grouped;
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?array {
        return $this->findBy('slug', $slug);
    }

    /**
     * Get tags for a user
     */
    public function getForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT mt.*, ut.added_at,
                   ab.full_name as added_by_name
            FROM member_tags mt
            INNER JOIN user_tags ut ON mt.id = ut.tag_id
            LEFT JOIN users ab ON ut.added_by = ab.id
            WHERE ut.user_id = ?
            ORDER BY mt.tag_group ASC, mt.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get users with a specific tag
     */
    public function getUsersWithTag(int $tagId, int $limit = 100): array {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.profile_photo,
                   ut.added_at
            FROM users u
            INNER JOIN user_tags ut ON u.id = ut.user_id
            WHERE ut.tag_id = ? AND u.active = 1
            ORDER BY u.last_name ASC, u.first_name ASC
            LIMIT ?
        ");
        $stmt->execute([$tagId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Add tag to user
     */
    public function addToUser(int $userId, int $tagId, ?int $addedBy = null): bool {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO user_tags (user_id, tag_id, added_by, added_at)
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $tagId, $addedBy]);
    }

    /**
     * Remove tag from user
     */
    public function removeFromUser(int $userId, int $tagId): bool {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_tags WHERE user_id = ? AND tag_id = ?
        ");
        return $stmt->execute([$userId, $tagId]);
    }

    /**
     * Set tags for user (replace all existing)
     */
    public function setForUser(int $userId, array $tagIds, ?int $addedBy = null): bool {
        $this->pdo->beginTransaction();
        try {
            // Remove existing tags
            $stmt = $this->pdo->prepare("DELETE FROM user_tags WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Add new tags
            if (!empty($tagIds)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_tags (user_id, tag_id, added_by, added_at)
                    VALUES (?, ?, ?, NOW())
                ");
                foreach ($tagIds as $tagId) {
                    $stmt->execute([$userId, $tagId, $addedBy]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get tag usage counts
     */
    public function getUsageCounts(): array {
        return $this->pdo->query("
            SELECT mt.id, mt.name, mt.slug, mt.tag_group, mt.color,
                   COUNT(ut.user_id) as user_count
            FROM member_tags mt
            LEFT JOIN user_tags ut ON mt.id = ut.tag_id
            GROUP BY mt.id
            ORDER BY mt.tag_group ASC, mt.name ASC
        ")->fetchAll();
    }
}
