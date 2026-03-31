<?php
/**
 * GroupRepository - Data access for groups module
 */

require_once __DIR__ . '/BaseRepository.php';

class GroupRepository extends BaseRepository {
    protected function getTableName(): string {
        return '`groups`';
    }

    /**
     * Get all groups with type info and member counts
     */
    public function getAllWithDetails(array $filters = [], int $page = 1, int $perPage = 25): array {
        $conditions = ['g.status != "archived"'];
        $params = [];

        if (!empty($filters['type_id'])) {
            $conditions[] = 'g.group_type_id = ?';
            $params[] = $filters['type_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'g.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['day'])) {
            $conditions[] = 'g.meeting_day = ?';
            $params[] = $filters['day'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(g.name LIKE ? OR g.description LIKE ? OR g.location_city LIKE ?)';
            $s = "%{$filters['search']}%";
            $params = array_merge($params, [$s, $s, $s]);
        }
        if (isset($filters['visibility'])) {
            $conditions[] = 'g.visibility = ?';
            $params[] = $filters['visibility'];
        }

        $where = implode(' AND ', $conditions);

        // Count
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `groups` g WHERE $where");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Data
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT g.*, gt.name as type_name, gt.color as type_color,
                   (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'active') as member_count,
                   (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.role IN ('leader','co-leader','admin')) as leader_count
            FROM `groups` g
            LEFT JOIN group_types gt ON g.group_type_id = gt.id
            WHERE $where
            ORDER BY g.name ASC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Get public groups for finder
     */
    public function getPublicGroups(array $filters = []): array {
        $filters['visibility'] = 'public';
        $filters['status'] = 'active';
        return $this->getAllWithDetails($filters, $filters['page'] ?? 1, $filters['per_page'] ?? 12);
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM `groups` WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get group with full details
     */
    public function getWithDetails(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT g.*, gt.name as type_name, gt.color as type_color,
                   (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'active') as member_count
            FROM `groups` g
            LEFT JOIN group_types gt ON g.group_type_id = gt.id
            WHERE g.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get group members
     */
    public function getMembers(int $groupId, ?string $role = null): array {
        $sql = "
            SELECT gm.*, u.first_name, u.last_name, u.email, u.profile_photo, u.nickname
            FROM group_members gm
            INNER JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ?
        ";
        $params = [$groupId];

        if ($role) {
            $sql .= " AND gm.role = ?";
            $params[] = $role;
        }
        $sql .= " ORDER BY FIELD(gm.role, 'leader', 'co-leader', 'admin', 'member'), u.last_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get leaders only
     */
    public function getLeaders(int $groupId): array {
        $stmt = $this->pdo->prepare("
            SELECT gm.*, u.first_name, u.last_name, u.email, u.profile_photo
            FROM group_members gm
            INNER JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ? AND gm.role IN ('leader', 'co-leader', 'admin')
            ORDER BY FIELD(gm.role, 'leader', 'co-leader', 'admin')
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    /**
     * Add member to group
     */
    public function addMember(int $groupId, int $userId, string $role = 'member', string $status = 'active'): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO group_members (group_id, user_id, role, status)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE role = VALUES(role), status = VALUES(status)
        ");
        return $stmt->execute([$groupId, $userId, $role, $status]);
    }

    /**
     * Remove member
     */
    public function removeMember(int $groupId, int $userId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        return $stmt->execute([$groupId, $userId]);
    }

    /**
     * Update member role
     */
    public function updateMemberRole(int $groupId, int $userId, string $role): bool {
        $stmt = $this->pdo->prepare("UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?");
        return $stmt->execute([$role, $groupId, $userId]);
    }

    /**
     * Check if user is member
     */
    public function isMember(int $groupId, int $userId): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'");
        $stmt->execute([$groupId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Check if user is leader
     */
    public function isLeader(int $groupId, int $userId): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND role IN ('leader','co-leader','admin')");
        $stmt->execute([$groupId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Get groups for a user
     */
    public function getGroupsForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT g.*, gm.role, gm.joined_at, gt.name as type_name, gt.color as type_color
            FROM group_members gm
            INNER JOIN `groups` g ON gm.group_id = g.id
            LEFT JOIN group_types gt ON g.group_type_id = gt.id
            WHERE gm.user_id = ? AND gm.status = 'active' AND g.status = 'active'
            ORDER BY g.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Create signup request
     */
    public function createSignupRequest(int $groupId, int $userId, ?string $message = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO group_signup_requests (group_id, user_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$groupId, $userId, $message]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get pending signup requests
     */
    public function getPendingRequests(int $groupId): array {
        $stmt = $this->pdo->prepare("
            SELECT gsr.*, u.first_name, u.last_name, u.email, u.profile_photo
            FROM group_signup_requests gsr
            INNER JOIN users u ON gsr.user_id = u.id
            WHERE gsr.group_id = ? AND gsr.status = 'pending'
            ORDER BY gsr.created_at
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    /**
     * Approve/deny signup request
     */
    public function handleSignupRequest(int $requestId, string $status, int $respondedBy, ?string $notes = null): bool {
        $stmt = $this->pdo->prepare("
            UPDATE group_signup_requests
            SET status = ?, responded_by = ?, responded_at = NOW(), response_notes = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([$status, $respondedBy, $notes, $requestId]);

        if ($result && $status === 'approved') {
            $stmt = $this->pdo->prepare("SELECT group_id, user_id FROM group_signup_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();
            if ($req) {
                $this->addMember($req['group_id'], $req['user_id']);
            }
        }
        return $result;
    }

    /**
     * Get all group types
     */
    public function getTypes(): array {
        $stmt = $this->pdo->query("SELECT * FROM group_types ORDER BY sort_order, name");
        return $stmt->fetchAll();
    }

    /**
     * Get stats
     */
    public function getStats(): array {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `groups` WHERE status = 'active'");
        $totalGroups = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT user_id) FROM group_members WHERE status = 'active'");
        $totalMembers = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM group_signup_requests WHERE status = 'pending'");
        $pendingRequests = (int)$stmt->fetchColumn();

        return compact('totalGroups', 'totalMembers', 'pendingRequests');
    }
}
