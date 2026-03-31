<?php
/**
 * PeopleListRepository - Data access for people lists/segments
 *
 * Handles both static lists (manually managed) and dynamic lists (criteria-based).
 */

require_once __DIR__ . '/BaseRepository.php';

class PeopleListRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'people_lists';
    }

    /**
     * Get all lists with member counts
     */
    public function getAllWithCounts(): array {
        $stmt = $this->pdo->query("
            SELECT pl.*,
                   u.first_name as created_by_first_name,
                   u.last_name as created_by_last_name
            FROM people_lists pl
            LEFT JOIN users u ON pl.created_by = u.id
            ORDER BY pl.is_system DESC, pl.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get lists by type
     */
    public function getByType(string $type): array {
        return $this->findAllBy(['list_type' => $type], 'name ASC');
    }

    /**
     * Get a list by slug
     */
    public function findBySlug(string $slug): ?array {
        $lists = $this->findAllBy(['slug' => $slug], null, 1);
        return $lists[0] ?? null;
    }

    /**
     * Create a new list
     */
    public function createList(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO people_lists (name, slug, description, list_type, criteria, color, icon, visibility, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $criteria = isset($data['criteria']) ? json_encode($data['criteria']) : null;

        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['list_type'] ?? 'static',
            $criteria,
            $data['color'] ?? '#6B7280',
            $data['icon'] ?? null,
            $data['visibility'] ?? 'shared',
            $data['created_by'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update list member count (for static lists)
     */
    public function updateMemberCount(int $listId): void {
        $stmt = $this->pdo->prepare("
            UPDATE people_lists
            SET member_count = (
                SELECT COUNT(*) FROM people_list_members WHERE list_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$listId, $listId]);
    }

    /**
     * Get members of a static list
     */
    public function getStaticListMembers(int $listId, int $page = 1, int $perPage = 25): array {
        $offset = ($page - 1) * $perPage;

        // Get total count
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM people_list_members WHERE list_id = ?");
        $stmt->execute([$listId]);
        $total = (int) $stmt->fetchColumn();

        // Get members
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.nickname, u.profile_photo,
                   u.is_member, u.membership_status_id,
                   ms.name as status_name, ms.color as status_color,
                   plm.added_at, plm.notes as list_note,
                   added_by.first_name as added_by_first_name,
                   added_by.last_name as added_by_last_name
            FROM people_list_members plm
            INNER JOIN users u ON plm.user_id = u.id
            LEFT JOIN membership_statuses ms ON u.membership_status_id = ms.id
            LEFT JOIN users added_by ON plm.added_by = added_by.id
            WHERE plm.list_id = ?
            ORDER BY u.last_name ASC, u.first_name ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$listId, $perPage, $offset]);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage)
        ];
    }

    /**
     * Get members of a dynamic list based on criteria
     */
    public function getDynamicListMembers(array $criteria, int $page = 1, int $perPage = 25): array {
        $conditions = ['u.active = 1'];
        $params = [];

        // Build conditions based on criteria
        if (isset($criteria['is_member'])) {
            $conditions[] = 'u.is_member = ?';
            $params[] = $criteria['is_member'] ? 1 : 0;
        }

        if (isset($criteria['membership_status_id'])) {
            $conditions[] = 'u.membership_status_id = ?';
            $params[] = $criteria['membership_status_id'];
        }

        if (isset($criteria['created_within'])) {
            switch ($criteria['created_within']) {
                case 'week':
                    $conditions[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $conditions[] = 'u.created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")';
                    break;
                case '30_days':
                    $conditions[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
                case '90_days':
                    $conditions[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
                    break;
                case 'year':
                    $conditions[] = 'u.created_at >= DATE_FORMAT(NOW(), "%Y-01-01")';
                    break;
            }
        }

        if (isset($criteria['last_login_within'])) {
            switch ($criteria['last_login_within']) {
                case '7_days':
                    $conditions[] = 'u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case '30_days':
                    $conditions[] = 'u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                    break;
                case '90_days':
                    $conditions[] = 'u.last_login >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
                    break;
            }
        }

        if (isset($criteria['last_login_before'])) {
            switch ($criteria['last_login_before']) {
                case '30_days':
                    $conditions[] = '(u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL 30 DAY))';
                    break;
                case '90_days':
                    $conditions[] = '(u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL 90 DAY))';
                    break;
                case '180_days':
                    $conditions[] = '(u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL 180 DAY))';
                    break;
            }
        }

        if (isset($criteria['birthday_month'])) {
            if ($criteria['birthday_month'] === 'current') {
                $conditions[] = 'MONTH(u.birthdate) = MONTH(NOW())';
            } else {
                $conditions[] = 'MONTH(u.birthdate) = ?';
                $params[] = (int) $criteria['birthday_month'];
            }
        }

        if (isset($criteria['anniversary_month'])) {
            if ($criteria['anniversary_month'] === 'current') {
                $conditions[] = 'MONTH(u.anniversary) = MONTH(NOW())';
            } else {
                $conditions[] = 'MONTH(u.anniversary) = ?';
                $params[] = (int) $criteria['anniversary_month'];
            }
        }

        if (isset($criteria['tag_ids']) && !empty($criteria['tag_ids'])) {
            $tagPlaceholders = implode(',', array_fill(0, count($criteria['tag_ids']), '?'));
            $conditions[] = "u.id IN (SELECT user_id FROM user_tags WHERE tag_id IN ($tagPlaceholders))";
            $params = array_merge($params, $criteria['tag_ids']);
        }

        if (isset($criteria['household_id'])) {
            $conditions[] = 'u.household_id = ?';
            $params[] = $criteria['household_id'];
        }

        if (isset($criteria['gender'])) {
            $conditions[] = 'u.gender = ?';
            $params[] = $criteria['gender'];
        }

        if (isset($criteria['age_min'])) {
            $conditions[] = 'u.birthdate <= DATE_SUB(NOW(), INTERVAL ? YEAR)';
            $params[] = (int) $criteria['age_min'];
        }

        if (isset($criteria['age_max'])) {
            $conditions[] = 'u.birthdate >= DATE_SUB(NOW(), INTERVAL ? YEAR)';
            $params[] = (int) $criteria['age_max'] + 1;
        }

        $whereClause = implode(' AND ', $conditions);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM users u WHERE $whereClause";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get members
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.email, u.nickname, u.profile_photo,
                   u.is_member, u.membership_status_id, u.birthdate, u.created_at, u.last_login,
                   ms.name as status_name, ms.color as status_color
            FROM users u
            LEFT JOIN membership_statuses ms ON u.membership_status_id = ms.id
            WHERE $whereClause
            ORDER BY u.last_name ASC, u.first_name ASC
            LIMIT $perPage OFFSET $offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage)
        ];
    }

    /**
     * Add person to a static list
     */
    public function addMember(int $listId, int $userId, ?int $addedBy = null, ?string $notes = null): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO people_list_members (list_id, user_id, added_by, notes)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE notes = VALUES(notes)
            ");
            $stmt->execute([$listId, $userId, $addedBy, $notes]);
            $this->updateMemberCount($listId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Remove person from a static list
     */
    public function removeMember(int $listId, int $userId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM people_list_members WHERE list_id = ? AND user_id = ?");
        $stmt->execute([$listId, $userId]);
        $this->updateMemberCount($listId);
        return true;
    }

    /**
     * Add multiple people to a static list
     */
    public function addMembers(int $listId, array $userIds, ?int $addedBy = null): int {
        $added = 0;
        foreach ($userIds as $userId) {
            if ($this->addMember($listId, $userId, $addedBy)) {
                $added++;
            }
        }
        return $added;
    }

    /**
     * Remove multiple people from a static list
     */
    public function removeMembers(int $listId, array $userIds): int {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM people_list_members WHERE list_id = ? AND user_id IN ($placeholders)");
        $stmt->execute(array_merge([$listId], $userIds));
        $this->updateMemberCount($listId);
        return $stmt->rowCount();
    }

    /**
     * Check if person is in a list
     */
    public function isMember(int $listId, int $userId): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM people_list_members WHERE list_id = ? AND user_id = ?");
        $stmt->execute([$listId, $userId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Get lists that a person belongs to
     */
    public function getListsForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT pl.*, plm.added_at, plm.notes
            FROM people_lists pl
            INNER JOIN people_list_members plm ON pl.id = plm.list_id
            WHERE plm.user_id = ? AND pl.list_type = 'static'
            ORDER BY pl.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Refresh all dynamic list counts
     */
    public function refreshDynamicListCounts(): void {
        $lists = $this->getByType('dynamic');

        foreach ($lists as $list) {
            $criteria = json_decode($list['criteria'], true) ?? [];
            $result = $this->getDynamicListMembers($criteria, 1, 1);

            $stmt = $this->pdo->prepare("
                UPDATE people_lists
                SET member_count = ?, last_refreshed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['total'], $list['id']]);
        }
    }

    /**
     * Delete a list (only non-system lists)
     */
    public function deleteList(int $listId): bool {
        // Check if system list
        $list = $this->find($listId);
        if (!$list || $list['is_system']) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM people_lists WHERE id = ? AND is_system = FALSE");
        $stmt->execute([$listId]);
        return $stmt->rowCount() > 0;
    }
}
