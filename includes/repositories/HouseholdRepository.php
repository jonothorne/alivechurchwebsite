<?php
/**
 * HouseholdRepository - Data access for households
 */

require_once __DIR__ . '/BaseRepository.php';

class HouseholdRepository extends BaseRepository {
    protected function getTableName(): string {
        return 'households';
    }

    /**
     * Get household with its members
     */
    public function getWithMembers(int $id): ?array {
        $household = $this->find($id);
        if (!$household) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, nickname, profile_photo,
                   household_role, birthdate, email
            FROM users
            WHERE household_id = ? AND active = 1
            ORDER BY
                CASE household_role
                    WHEN 'head' THEN 1
                    WHEN 'spouse' THEN 2
                    WHEN 'child' THEN 3
                    ELSE 4
                END,
                birthdate ASC
        ");
        $stmt->execute([$id]);
        $household['members'] = $stmt->fetchAll();

        return $household;
    }

    /**
     * Get household's primary address
     */
    public function getPrimaryAddress(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM addresses
            WHERE household_id = ? AND is_primary = 1
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Search households by name
     */
    public function search(string $query, int $limit = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT h.*,
                   (SELECT COUNT(*) FROM users WHERE household_id = h.id AND active = 1) as member_count
            FROM households h
            WHERE h.name LIKE ?
            ORDER BY h.name ASC
            LIMIT ?
        ");
        $stmt->execute(["%{$query}%", $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get all households with member counts
     */
    public function getAllWithCounts(): array {
        return $this->pdo->query("
            SELECT h.*,
                   (SELECT COUNT(*) FROM users WHERE household_id = h.id AND active = 1) as member_count,
                   pc.first_name as primary_contact_first,
                   pc.last_name as primary_contact_last
            FROM households h
            LEFT JOIN users pc ON h.primary_contact_id = pc.id
            ORDER BY h.name ASC
        ")->fetchAll();
    }
}
