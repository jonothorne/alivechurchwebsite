<?php
/**
 * UserNoteRepository - Data access for user notes
 */

require_once __DIR__ . '/BaseRepository.php';

class UserNoteRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'user_notes';
    }

    /**
     * Get notes for a user
     */
    public function getForUser(int $userId, ?string $type = null): array {
        $sql = "
            SELECT n.*,
                   cb.full_name as created_by_name,
                   cb.first_name as created_by_first,
                   cb.last_name as created_by_last
            FROM user_notes n
            LEFT JOIN users cb ON n.created_by = cb.id
            WHERE n.user_id = ?
        ";
        $params = [$userId];

        if ($type) {
            $sql .= " AND n.note_type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY n.is_pinned DESC, n.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get pinned notes for a user
     */
    public function getPinnedForUser(int $userId): array {
        return $this->findAllBy(
            ['user_id' => $userId, 'is_pinned' => 1],
            'created_at DESC'
        );
    }

    /**
     * Get recent notes across all users (for dashboard)
     */
    public function getRecent(int $limit = 20, ?string $type = null): array {
        $sql = "
            SELECT n.*,
                   u.first_name, u.last_name, u.email,
                   cb.full_name as created_by_name
            FROM user_notes n
            INNER JOIN users u ON n.user_id = u.id
            LEFT JOIN users cb ON n.created_by = cb.id
        ";
        $params = [];

        if ($type) {
            $sql .= " WHERE n.note_type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get follow-up notes that need attention
     */
    public function getFollowUps(): array {
        return $this->pdo->query("
            SELECT n.*,
                   u.first_name, u.last_name, u.email, u.profile_photo,
                   cb.full_name as created_by_name
            FROM user_notes n
            INNER JOIN users u ON n.user_id = u.id
            LEFT JOIN users cb ON n.created_by = cb.id
            WHERE n.note_type = 'follow_up'
            ORDER BY n.created_at DESC
        ")->fetchAll();
    }

    /**
     * Create a new note
     */
    public function createNote(int $userId, string $note, string $type, int $createdBy, bool $pinned = false): int {
        return $this->create([
            'user_id' => $userId,
            'note' => $note,
            'note_type' => $type,
            'created_by' => $createdBy,
            'is_pinned' => $pinned ? 1 : 0
        ]);
    }

    /**
     * Toggle pin status
     */
    public function togglePin(int $noteId): bool {
        $note = $this->find($noteId);
        if (!$note) {
            return false;
        }

        return $this->update($noteId, [
            'is_pinned' => $note['is_pinned'] ? 0 : 1
        ]);
    }

    /**
     * Count notes by type for a user
     */
    public function countByTypeForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT note_type, COUNT(*) as count
            FROM user_notes
            WHERE user_id = ?
            GROUP BY note_type
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
